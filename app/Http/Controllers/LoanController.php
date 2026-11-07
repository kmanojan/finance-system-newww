<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogService;
use App\Services\LedgerPostingService;
use App\Services\LoanBalanceService;
use App\Services\LoanFlowService;
use App\Services\LoanInterestPaymentService;
use App\Services\LoanPrincipalCorrectionService;
use App\Services\PeriodLockService;
use App\Services\TransactionPostingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LoanController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('loans')->orderBy('created_at', 'desc');

        if ($request->filled('party_id')) {
            $query->where('party_id', $request->input('party_id'));
        }

        $startDate = $request->input('start_date') ?: $request->input('from');
        $endDate = $request->input('end_date') ?: $request->input('to');

        if (! empty($startDate)) {
            $query->where(function ($q) use ($startDate) {
                $q->where('claimed_date', '>=', $startDate)
                    ->orWhere('start_date', '>=', $startDate);
            });
        }
        if (! empty($endDate)) {
            $query->where(function ($q) use ($endDate) {
                $q->where('claimed_date', '<=', $endDate)
                    ->orWhere('start_date', '<=', $endDate);
            });
        }
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', '=', $request->input('status'));
        }
        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $hasLoanCode = Schema::hasColumn('loans', 'loan_code');
            $query->where(function ($q) use ($search, $hasLoanCode) {
                $q->where('lender_name', 'LIKE', $search)
                    ->orWhere('purpose', 'LIKE', $search);
                if ($hasLoanCode) {
                    $q->orWhere('loan_code', 'LIKE', $search);
                }
            });
        }

        $loans = $query->get();

        $totalBorrowed = 0;
        $totalPrincipalRepaid = 0;
        $totalOutstandingPrincipal = 0;
        $totalInterestPaid = 0;
        $thisMonthPayable = 0;
        $totalOutstandingInterest = 0;

        foreach ($loans as $loan) {
            $schedules = DB::table('loan_interest_schedule')->where('loan_id', $loan->id)->get();
            $balance = LoanBalanceService::calculate($loan, $schedules);
            foreach ($balance as $field => $value) {
                $loan->$field = $value;
            }
            $outstandingPrincipal = $balance['outstanding_principal'];
            $loanPendingInterest = $balance['pending_interest'];
            $totalBorrowed += $balance['total_borrowed'];
            $totalPrincipalRepaid += $balance['principal_repaid'];
            $totalInterestPaid += $balance['interest_paid'];
            if ($loan->status === 'active') {
                $thisMonthPayable += $schedules->whereIn('status', ['pending', 'partially_paid', 'overdue'])
                    ->filter(fn ($row) => Carbon::parse($row->due_date)->isCurrentMonth())
                    ->sum(fn ($row) => max(0, $row->interest_amount - ($row->paid_amount ?? 0)));
            }

            $loan->net_disbursed = ! empty($loan->is_upfront_interest ?? null)
                ? max(0, ($loan->principal_amount ?? 0) - (($loan->upfront_interest_amount ?? null) ?: ($loan->interest_amount ?? 0)))
                : ($loan->principal_amount ?? 0);

            $settledRecord = DB::table('loan_principal_records')
                ->where('loan_id', $loan->id)
                ->where('record_type', 'repayment')
                ->orderBy('record_date', 'desc')
                ->first();
            $loan->settled_date = $settledRecord ? $settledRecord->record_date : ($loan->status === 'settled' ? date('Y-m-d', strtotime($loan->updated_at)) : null);

            if (in_array($loan->status, ['active', 'pending', 'defaulted'])) {
                $totalOutstandingPrincipal += $outstandingPrincipal;
                $totalOutstandingInterest += $loanPendingInterest;

                // Check next due date
                $nextDue = DB::table('loan_interest_schedule')
                    ->where('loan_id', $loan->id)
                    ->whereIn('status', ['pending', 'partially_paid'])
                    ->orderBy('due_date', 'asc')
                    ->first();

                $loan->next_due_date = $nextDue ? $nextDue->due_date : (($loan->maturity_date ?? null) ?: 'N/A');
            } else {
                $loan->next_due_date = 'N/A';
            }
            // Attach linked bank account details
            if (! empty($loan->bank_account_id)) {
                $bank = DB::table('bank_accounts')->where('id', $loan->bank_account_id)->first();
                $loan->bank_name = $bank ? $bank->bank_name : null;
                $loan->account_no = $bank ? $bank->account_no : null;
            } else {
                $loan->bank_name = null;
                $loan->account_no = null;
            }
        }

        $totalWantToPaid = $loans->whereIn('status', ['active', 'pending', 'defaulted'])->sum('total_outstanding');
        $totalToSettle = $loans->whereIn('status', ['active', 'pending', 'defaulted'])->sum('full_settlement_amount');
        $totalPaidAll = $totalPrincipalRepaid + $totalInterestPaid;
        $totalLoansCount = $loans->count();
        $activeLoansCount = $loans->where('status', 'active')->count();
        $settledLoansCount = $loans->where('status', 'settled')->count();

        $parties = DB::table('parties')->orderBy('name')->get();

        // Load Bank Accounts with live balances
        $bankAccounts = DB::table('bank_accounts')->get();
        foreach ($bankAccounts as $acc) {
            $inflow = DB::table('transactions')->where('bank_account_id', $acc->id)->where('type', 'income')->sum('amount');
            $outflow = DB::table('transactions')->where('bank_account_id', $acc->id)->where('type', 'expense')->sum('amount');
            $acc->current_balance = ($acc->opening_balance ?? 0) + $inflow - $outflow;
        }

        // Paginate loans collection
        $currentPage = Paginator::resolveCurrentPage() ?: 1;
        $perPage = 15;
        $currentItems = $loans->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $loans = new LengthAwarePaginator($currentItems, $loans->count(), $perPage, $currentPage, [
            'path' => Paginator::resolveCurrentPath(),
            'query' => $request->query(),
        ]);

        return view('loans', compact(
            'loans',
            'parties',
            'bankAccounts',
            'totalBorrowed',
            'totalPrincipalRepaid',
            'totalInterestPaid',
            'totalPaidAll',
            'totalOutstandingPrincipal',
            'totalOutstandingInterest',
            'totalWantToPaid',
            'totalToSettle',
            'thisMonthPayable',
            'totalLoansCount',
            'activeLoansCount',
            'settledLoansCount'
        ));
    }

    public function schedules(Request $request)
    {
        $query = DB::table('loan_interest_schedule')
            ->join('loans', 'loan_interest_schedule.loan_id', '=', 'loans.id')
            ->select('loan_interest_schedule.*', 'loans.lender_name', 'loans.currency', 'loans.party_id');

        if ($request->filled('party_id') && $request->input('party_id') !== 'all') {
            $query->where('loans.party_id', $request->input('party_id'));
        }

        if ($request->filled('start_date')) {
            $query->where('loan_interest_schedule.due_date', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->where('loan_interest_schedule.due_date', '<=', $request->input('end_date'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            if ($request->input('status') === 'overdue') {
                $query->where('loan_interest_schedule.due_date', '<', now()->format('Y-m-d'))
                    ->whereIn('loan_interest_schedule.status', ['pending', 'partially_paid']);
            } else {
                $query->where('loan_interest_schedule.status', $request->input('status'));
            }
        }

        $schedules = $query->orderBy('loan_interest_schedule.due_date', 'asc')->get();

        $parties = DB::table('parties')->orderBy('name')->get();

        $totalScheduledInterest = $schedules->sum('interest_amount');
        $totalPaidInterest = $schedules->sum('paid_amount');
        $totalPendingInterest = 0;
        foreach ($schedules as $s) {
            if (in_array($s->status, ['pending', 'partially_paid', 'overdue'])) {
                $totalPendingInterest += max(0, $s->interest_amount - ($s->paid_amount ?? 0));
            }
        }

        // Paginate schedules collection
        $currentPage = Paginator::resolveCurrentPage() ?: 1;
        $perPage = 15;
        $currentItems = $schedules->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $schedules = new LengthAwarePaginator($currentItems, $schedules->count(), $perPage, $currentPage, [
            'path' => Paginator::resolveCurrentPath(),
            'query' => $request->query(),
        ]);

        return view('loans-schedules', compact(
            'schedules',
            'parties',
            'totalScheduledInterest',
            'totalPaidInterest',
            'totalPendingInterest'
        ));
    }

    public function settlements(Request $request)
    {
        $queryPrincipal = DB::table('loan_principal_records')
            ->join('loans', 'loan_principal_records.loan_id', '=', 'loans.id')
            ->select(
                'loan_principal_records.id',
                'loan_principal_records.loan_id',
                'loan_principal_records.record_date as date',
                'loan_principal_records.record_type as category',
                'loan_principal_records.amount',
                'loan_principal_records.payment_mode',
                'loan_principal_records.reference_no',
                'loan_principal_records.notes',
                'loans.lender_name',
                'loans.currency'
            );

        if ($request->filled('start_date')) {
            $queryPrincipal->where('loan_principal_records.record_date', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $queryPrincipal->where('loan_principal_records.record_date', '<=', $request->input('end_date'));
        }

        $principalRecords = $queryPrincipal->get();

        $queryInterest = DB::table('loan_interest_schedule')
            ->join('loans', 'loan_interest_schedule.loan_id', '=', 'loans.id')
            ->where('loan_interest_schedule.paid_amount', '>', 0)
            ->select(
                'loan_interest_schedule.id',
                'loan_interest_schedule.loan_id',
                DB::raw('COALESCE(loan_interest_schedule.paid_date, loan_interest_schedule.due_date) as date'),
                DB::raw("'interest' as category"),
                'loan_interest_schedule.paid_amount as amount',
                DB::raw("'Normal' as payment_mode"),
                DB::raw('NULL as reference_no'),
                DB::raw("'Interest Settlement' as notes"),
                'loans.lender_name',
                'loans.currency'
            );

        if ($request->filled('start_date')) {
            $queryInterest->where(DB::raw('COALESCE(loan_interest_schedule.paid_date, loan_interest_schedule.due_date)'), '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $queryInterest->where(DB::raw('COALESCE(loan_interest_schedule.paid_date, loan_interest_schedule.due_date)'), '<=', $request->input('end_date'));
        }

        $interestRecords = $queryInterest->get();

        // Calculate total loan summary totals per loan
        $loans = DB::table('loans')->get();
        $loanTotals = [];
        foreach ($loans as $l) {
            $pPaid = DB::table('loan_principal_records')->where('loan_id', $l->id)->where('record_type', 'repayment')->sum('amount');
            $iPaid = DB::table('loan_interest_schedule')->where('loan_id', $l->id)->sum('paid_amount');
            $loanTotals[$l->id] = [
                'principal_paid' => $pPaid,
                'interest_paid' => $iPaid,
                'total_paid' => $pPaid + $iPaid,
            ];
        }

        // Merge records
        $allRecords = collect();
        foreach ($principalRecords as $rec) {
            $rec->loan_total_paid = $loanTotals[$rec->loan_id]['total_paid'] ?? 0;
            $rec->loan_principal_paid = $loanTotals[$rec->loan_id]['principal_paid'] ?? 0;
            $rec->loan_interest_paid = $loanTotals[$rec->loan_id]['interest_paid'] ?? 0;
            $allRecords->push($rec);
        }

        foreach ($interestRecords as $rec) {
            $rec->loan_total_paid = $loanTotals[$rec->loan_id]['total_paid'] ?? 0;
            $rec->loan_principal_paid = $loanTotals[$rec->loan_id]['principal_paid'] ?? 0;
            $rec->loan_interest_paid = $loanTotals[$rec->loan_id]['interest_paid'] ?? 0;
            $allRecords->push($rec);
        }

        $settlements = $allRecords->sortByDesc('date')->values();

        // Calculate KPI totals
        $totalPrincipalRepaid = $principalRecords->where('category', 'repayment')->sum('amount');
        $totalDraws = $principalRecords->where('category', 'draw')->sum('amount');
        $totalInterestPaid = $interestRecords->sum('amount');
        $totalPaidAll = $totalPrincipalRepaid + $totalInterestPaid;

        // Paginate settlements collection
        $currentPage = Paginator::resolveCurrentPage() ?: 1;
        $perPage = 15;
        $currentItems = $settlements->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $settlements = new LengthAwarePaginator($currentItems, $settlements->count(), $perPage, $currentPage, [
            'path' => Paginator::resolveCurrentPath(),
            'query' => $request->query(),
        ]);

        return view('loans-settlements', compact(
            'settlements',
            'totalPrincipalRepaid',
            'totalInterestPaid',
            'totalPaidAll',
            'totalDraws'
        ));
    }

    public function store(Request $request)
    {
        $this->validateTerms($request);
        $data = $request->only(['loan_type', 'party_id', 'lender_name', 'principal_amount', 'currency', 'purpose', 'claimed_date', 'term_months', 'interest_method', 'interest_amount', 'interest_rate', 'rate_basis', 'total_interest', 'due_day', 'frequency', 'guarantor', 'collateral', 'status', 'maturity_date', 'bank_account_id', 'reminder_days', 'loan_code', 'is_upfront_interest', 'upfront_interest_amount']);
        $data['loan_type'] = $request->input('loan_type') ?: LoanBalanceService::INTEREST_SEPARATE;
        $data['status'] = 'pending';
        $data['created_at'] = now();
        $data['updated_at'] = now();

        if ($request->filled('party_id')) {
            $party = DB::table('parties')->where('id', $request->input('party_id'))->first();
            if ($party) {
                $data['party_id'] = $party->id;
                if (empty($data['lender_name'])) {
                    $data['lender_name'] = $party->name;
                }
            }
        }

        if (($data['interest_method'] ?? null) === 'no_interest') {
            $data['interest_amount'] = 0;
            $data['total_interest'] = 0;
            $data['interest_rate'] = null;
            $data['is_upfront_interest'] = 0;
            $data['upfront_interest_amount'] = null;
        } else {
            $data['is_upfront_interest'] = $request->has('is_upfront_interest') ? 1 : 0;
            if ($data['is_upfront_interest']) {
                if (empty($data['upfront_interest_amount'])) {
                    $data['upfront_interest_amount'] = $data['interest_amount'] ?? 0;
                }
            } else {
                $data['upfront_interest_amount'] = null;
            }
        }

        if (! empty($data['maturity_date']) && ! empty($data['claimed_date'])) {
            $d1 = Carbon::parse($data['claimed_date']);
            $d2 = Carbon::parse($data['maturity_date']);
            $diffMonths = $d1->diffInMonths($d2);
            if ($diffMonths > 0 && empty($request->input('term_months'))) {
                $data['term_months'] = $diffMonths;
            }
        } else {
            $data['maturity_date'] = null;
        }

        if ($request->filled('bank_account_id')) {
            $data['bank_account_id'] = (int) $request->input('bank_account_id');
        } else {
            $data['bank_account_id'] = null;
        }

        if (empty($data['reminder_days'])) {
            $data['reminder_days'] = 3;
        }

        if (empty($data['due_day']) && ! empty($data['claimed_date'])) {
            $data['due_day'] = Carbon::parse($data['claimed_date'])->day;
        }

        if (empty($data['loan_code'])) {
            $lastLoan = DB::table('loans')->orderBy('id', 'desc')->first();
            $nextId = ($lastLoan ? $lastLoan->id : 0) + 1;
            $data['loan_code'] = 'LN-'.str_pad($nextId, 4, '0', STR_PAD_LEFT);
        }

        if (empty($data['status'])) {
            $data['status'] = 'pending';
        }

        $loanId = DB::table('loans')->insertGetId($data);

        // Ensure loan_code is set
        $currentCode = DB::table('loans')->where('id', $loanId)->value('loan_code');
        if (empty($currentCode)) {
            DB::table('loans')->where('id', $loanId)->update(['loan_code' => 'LN-'.str_pad($loanId, 4, '0', STR_PAD_LEFT)]);
        }

        ActivityLogService::logCreate('Loan', $loanId, [
            'lender_name' => $data['lender_name'] ?? null,
            'loan_code' => $data['loan_code'] ?? null,
            'principal_amount' => $data['principal_amount'] ?? null,
            'purpose' => $data['purpose'] ?? null,
        ]);

        $this->storeAttachments($request, (int) $loanId);

        // For custom schedule, we save the schedule immediately during store
        if ($data['interest_method'] === 'custom_schedule') {
            $customDates = $request->input('custom_dates', []);
            $customAmounts = $request->input('custom_amounts', []);
            $inserts = [];
            for ($i = 0; $i < count($customDates); $i++) {
                if (! empty($customDates[$i]) && ! empty($customAmounts[$i])) {
                    $inserts[] = [
                        'loan_id' => $loanId,
                        'due_date' => $customDates[$i],
                        'interest_amount' => $customAmounts[$i],
                        'status' => 'pending',
                        'created_at' => now(),
                    ];
                }
            }
            if (! empty($inserts)) {
                DB::table('loan_interest_schedule')->insert($inserts);
            }
        }

        // Schedule is generated later when loan is activated (for non-custom)
        if ($data['status'] === 'active') {
            if ($data['interest_method'] !== 'custom_schedule') {
                $this->generateInterestSchedule($loanId, $data['claimed_date'], $data);
            }
            $this->syncLoanMaturityReminder($loanId);
        }

        return back()->with('success', 'Loan created successfully! Please activate it to generate the schedule.');
    }

    public function update(Request $request, $id)
    {
        $loan = DB::table('loans')->where('id', $id)->lockForUpdate()->first();
        if (! $loan) {
            abort(404);
        }

        $this->validateAttachments($request);

        if ($loan->status !== 'pending') {
            $financial = ['currency', 'claimed_date', 'interest_method', 'interest_rate', 'interest_amount', 'bank_account_id', 'term_months', 'frequency', 'total_interest', 'upfront_interest_amount', 'is_upfront_interest', 'due_day', 'rate_basis'];
            foreach ($financial as $field) {
                if ($request->has($field) && (string) $request->input($field) !== (string) $loan->$field && (! is_numeric($loan->$field) || (float) $request->input($field) !== (float) $loan->$field)) {
                    throw ValidationException::withMessages([$field => 'Activated financial terms are locked. Record a draw, repayment, or settlement instead.']);
                }
            }
            $metadata = $request->validate([
                'loan_type' => 'sometimes|required|in:interest_separate,principal_and_interest',
                'party_id' => 'sometimes|nullable|exists:parties,id', 'lender_name' => 'sometimes|nullable|string|max:255',
                'principal_amount' => 'sometimes|required|numeric|min:0.01|max:9999999999999.99|decimal:0,2',
                'maturity_date' => 'sometimes|nullable|date_format:Y-m-d',
                'reminder_days' => 'sometimes|nullable|integer|min:0|max:365',
                'purpose' => 'nullable|string|max:5000', 'guarantor' => 'nullable|string|max:255', 'collateral' => 'nullable|string|max:5000',
            ]);
            $partyId = array_key_exists('party_id', $metadata) ? $metadata['party_id'] : $loan->party_id;
            if (! empty($metadata['maturity_date']) && $loan->claimed_date && $metadata['maturity_date'] < substr($loan->claimed_date, 0, 10)) {
                throw ValidationException::withMessages(['maturity_date' => 'The principal due date must be on or after the claimed date.']);
            }
            $lender = array_key_exists('lender_name', $metadata) ? $metadata['lender_name'] : $loan->lender_name;
            if (blank($lender)) {
                $lender = $partyId ? DB::table('parties')->where('id', $partyId)->value('name') : null;
                if (blank($lender)) {
                    throw ValidationException::withMessages(['lender_name' => 'Select a party or enter a lender name.']);
                }
                $metadata['lender_name'] = $lender;
            }
            if (isset($metadata['principal_amount']) && round((float) $metadata['principal_amount'] * 100) !== round((float) $loan->principal_amount * 100)) {
                $request->validate(['correction_reason' => 'required|string|max:1000']);
                LoanPrincipalCorrectionService::correct($loan, (float) $metadata['principal_amount'], $request->input('correction_reason'));
            }
            DB::table('loans')->where('id', $id)->update($metadata + ['updated_at' => now()]);
            ActivityLogService::logUpdate('Loan', $id, (array) $loan, $metadata);
            $this->storeAttachments($request, (int) $id);
            $this->syncLoanMaturityReminder($id);

            return back()->with('success', 'Loan updated. Recorded repayments and the existing interest schedule are preserved.');
        }
        $this->validateTerms($request);
        $data = $request->only(['loan_type', 'party_id', 'lender_name', 'principal_amount', 'currency', 'purpose', 'claimed_date', 'term_months', 'interest_method', 'interest_amount', 'interest_rate', 'rate_basis', 'total_interest', 'due_day', 'frequency', 'guarantor', 'collateral', 'status', 'maturity_date', 'bank_account_id', 'reminder_days', 'loan_code', 'is_upfront_interest', 'upfront_interest_amount']);
        $data['status'] = 'pending';
        $data['updated_at'] = now();

        if ($request->filled('party_id')) {
            $party = DB::table('parties')->where('id', $request->input('party_id'))->first();
            if ($party) {
                $data['party_id'] = $party->id;
                if (empty($data['lender_name'])) {
                    $data['lender_name'] = $party->name;
                }
            }
        }

        if (($data['interest_method'] ?? null) === 'no_interest') {
            $data['interest_amount'] = 0;
            $data['total_interest'] = 0;
            $data['interest_rate'] = null;
            $data['is_upfront_interest'] = 0;
            $data['upfront_interest_amount'] = null;
        } else {
            $data['is_upfront_interest'] = $request->has('is_upfront_interest') ? 1 : 0;
            if ($data['is_upfront_interest']) {
                if (empty($data['upfront_interest_amount'])) {
                    $data['upfront_interest_amount'] = $data['interest_amount'] ?? 0;
                }
            } else {
                $data['upfront_interest_amount'] = null;
            }
        }

        if (! empty($data['maturity_date']) && ! empty($data['claimed_date'])) {
            $d1 = Carbon::parse($data['claimed_date']);
            $d2 = Carbon::parse($data['maturity_date']);
            $diffMonths = $d1->diffInMonths($d2);
            if ($diffMonths > 0 && empty($request->input('term_months'))) {
                $data['term_months'] = $diffMonths;
            }
        } else {
            $data['maturity_date'] = null;
        }

        if ($request->filled('bank_account_id')) {
            $data['bank_account_id'] = (int) $request->input('bank_account_id');
        } else {
            $data['bank_account_id'] = null;
        }

        if (empty($data['reminder_days'])) {
            $data['reminder_days'] = 3;
        }

        if (empty($data['due_day']) && ! empty($data['claimed_date'])) {
            $data['due_day'] = Carbon::parse($data['claimed_date'])->day;
        }

        DB::table('loans')->where('id', $id)->update($data);

        ActivityLogService::logUpdate('Loan', $id, (array) $loan, $data);

        $this->storeAttachments($request, (int) $id);

        // Pending loans have no posted movements. Only their custom schedule is editable here.
        DB::table('loan_interest_schedule')->where('loan_id', $id)->delete();
        if (($data['interest_method'] ?? $loan->interest_method) === 'custom_schedule') {
            foreach ($request->input('custom_dates', []) as $index => $date) {
                DB::table('loan_interest_schedule')->insert(['loan_id' => $id, 'due_date' => $date, 'interest_amount' => $request->input('custom_amounts.'.$index), 'status' => 'pending', 'created_at' => now()]);
            }
        }

        $this->syncLoanMaturityReminder($id);

        return back()->with('success', 'Loan updated successfully!');
    }

    private function generateInterestSchedule($loanId, $startDate, $loanData, $startFromDate = null)
    {
        if ($loanData['interest_method'] === 'no_interest') {
            return;
        }

        $termMonths = ! empty($loanData['term_months']) ? (int) $loanData['term_months'] : 1;
        $claimedCarbon = Carbon::parse($startDate);
        $dueDay = ! empty($loanData['due_day']) ? (int) $loanData['due_day'] : $claimedCarbon->day;
        $isUpfront = ! empty($loanData['is_upfront_interest']);
        $upfrontAmount = ! empty($loanData['upfront_interest_amount']) ? (float) $loanData['upfront_interest_amount'] : null;

        $inserts = [];
        $outstanding = (float) ($loanData['principal_amount'] ?? 0);

        $computePeriodicInterest = function () use ($loanData, $outstanding, $termMonths) {
            if ($loanData['interest_method'] === 'fixed_amount') {
                return (float) ($loanData['interest_amount'] ?? 0);
            } elseif ($loanData['interest_method'] === 'percentage_rate') {
                return $outstanding * ((float) ($loanData['interest_rate'] ?? 0) / 100);
            } elseif (in_array($loanData['interest_method'], ['equal_installments', 'total_interest'])) {
                $totalToPay = (float) ($loanData['total_interest'] ?? 0);

                return $totalToPay / max(1, $termMonths);
            }

            return 0;
        };

        $periodicAmount = $computePeriodicInterest();
        $firstAmount = ($isUpfront && $upfrontAmount !== null && $upfrontAmount > 0) ? $upfrontAmount : $periodicAmount;

        // If interest repayment amount is 0, do not generate schedule rows
        if ($periodicAmount <= 0 && $firstAmount <= 0) {
            return;
        }

        if ($isUpfront) {
            // Period 1: Upfront interest paid on Claimed Date
            $inserts[] = [
                'loan_id' => $loanId,
                'due_date' => $claimedCarbon->format('Y-m-d'),
                'interest_amount' => $firstAmount,
                'paid_amount' => $firstAmount,
                'paid_date' => $claimedCarbon->format('Y-m-d'),
                'status' => 'paid',
                'created_at' => now(),
            ];

            // For subsequent periods (if term > 1)
            for ($i = 2; $i <= $termMonths; $i++) {
                $monthsToAdd = (isset($loanData['frequency']) && $loanData['frequency'] === 'quarterly') ? (($i - 1) * 3) : ($i - 1);
                $targetMonth = $claimedCarbon->copy()->addMonths($monthsToAdd);
                $dueDate = $targetMonth->copy()->setDay(min($dueDay, $targetMonth->daysInMonth));

                if ($startFromDate && $dueDate->lt(Carbon::parse($startFromDate))) {
                    continue;
                }

                $inserts[] = [
                    'loan_id' => $loanId,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'interest_amount' => $computePeriodicInterest(),
                    'paid_amount' => 0.00,
                    'paid_date' => null,
                    'status' => 'pending',
                    'created_at' => now(),
                ];
            }
        } else {
            for ($i = 1; $i <= $termMonths; $i++) {
                $monthsToAdd = (isset($loanData['frequency']) && $loanData['frequency'] === 'quarterly') ? ($i * 3) : $i;
                $targetMonth = $claimedCarbon->copy()->addMonths($monthsToAdd);
                $dueDate = $targetMonth->copy()->setDay(min($dueDay, $targetMonth->daysInMonth));

                if ($startFromDate && $dueDate->lt(Carbon::parse($startFromDate))) {
                    continue;
                }

                $inserts[] = [
                    'loan_id' => $loanId,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'interest_amount' => $computePeriodicInterest(),
                    'paid_amount' => 0.00,
                    'paid_date' => null,
                    'status' => 'pending',
                    'created_at' => now(),
                ];
            }
        }

        if (! empty($inserts)) {
            DB::table('loan_interest_schedule')->insert($inserts);
        }
    }

    public function show($id)
    {
        $loan = DB::table('loans')->where('id', $id)->first();
        if (! $loan) {
            abort(404);
        }

        $schedules = DB::table('loan_interest_schedule')
            ->where('loan_id', $id)
            ->orderBy('due_date', 'asc')
            ->get();

        $interestPayments = DB::table('transactions')
            ->where('type', 'expense')
            ->whereIn('reference_no', $schedules->map(fn ($schedule) => "LOAN-INT-{$id}-{$schedule->id}"))
            ->orderBy('transaction_date')->orderBy('id')
            ->get(['id', 'reference_no', 'transaction_date', 'amount', 'currency', 'description', 'bank_account_id'])
            ->groupBy('reference_no');

        $principalRecords = DB::table('loan_principal_records')
            ->where('loan_id', $id)
            ->orderBy('record_date', 'desc')
            ->get();

        $balance = LoanBalanceService::calculate($loan, $schedules, $principalRecords);
        $loan->outstanding_principal = $balance['outstanding_principal'];

        $loan->net_disbursed = ! empty($loan->is_upfront_interest ?? null)
            ? max(0, ($loan->principal_amount ?? 0) - (($loan->upfront_interest_amount ?? null) ?: ($loan->interest_amount ?? 0)))
            : ($loan->principal_amount ?? 0);

        $settledRecord = $principalRecords->where('record_type', 'repayment')->first();
        $loan->settled_date = $settledRecord ? $settledRecord->record_date : ($loan->status === 'settled' ? date('Y-m-d', strtotime($loan->updated_at)) : null);

        $attachments = DB::table('attachments')
            ->where('model_type', 'Loan')
            ->where('model_id', $id)
            ->get();

        $parties = DB::table('parties')->orderBy('name')->get();

        // Load Bank Accounts with live balances
        $bankAccounts = DB::table('bank_accounts')->get();
        foreach ($bankAccounts as $acc) {
            $inflow = DB::table('transactions')->where('bank_account_id', $acc->id)->where('type', 'income')->sum('amount');
            $outflow = DB::table('transactions')->where('bank_account_id', $acc->id)->where('type', 'expense')->sum('amount');
            $acc->current_balance = ($acc->opening_balance ?? 0) + $inflow - $outflow;
        }
        $linkedBankAccount = ! empty($loan->bank_account_id) ? $bankAccounts->firstWhere('id', $loan->bank_account_id) : null;

        $loanFlow = LoanFlowService::graph((int) $id);
        $fundingLoans = DB::table('loans')->where('id', '!=', $id)->where('currency', $loan->currency)
            ->whereIn('status', ['active', 'defaulted', 'settled', 'closed'])->orderByDesc('claimed_date')->get();

        return view('loans-show', compact('loan', 'balance', 'schedules', 'interestPayments', 'principalRecords', 'attachments', 'parties', 'bankAccounts', 'linkedBankAccount', 'loanFlow', 'fundingLoans'));
    }

    public function activate($id)
    {
        $loan = DB::table('loans')->where('id', $id)->lockForUpdate()->first();
        abort_unless($loan, 404);
        if ($loan->status === 'pending') {
            DB::table('loans')->where('id', $id)->update(['status' => 'active']);

            // convert to array for generateInterestSchedule
            $loanData = (array) $loan;
            if ($loanData['interest_method'] !== 'custom_schedule') {
                $this->generateInterestSchedule($id, $loan->claimed_date ?: now()->format('Y-m-d'), $loanData);
            }

            // Auto-post Income Transaction for Loan Disbursement
            $catId = $this->getCategoryId('Loan Disbursement', 'income');

            $isUpfront = ! empty($loan->is_upfront_interest);
            $upfrontAmt = $isUpfront ? ($loan->upfront_interest_amount ?: $loan->interest_amount) : 0;
            $disbursedAmount = ($isUpfront && $upfrontAmt > 0) ? max(0, $loan->principal_amount - $upfrontAmt) : $loan->principal_amount;

            $desc = "Loan Disbursement from {$loan->lender_name}";
            if ($isUpfront && $upfrontAmt > 0) {
                $desc .= ' (Net received: '.number_format($disbursedAmount, 2).', deducted '.number_format($upfrontAmt, 2).' for upfront interest on '.number_format($loan->principal_amount, 2).' principal)';
                if ($loan->purpose) {
                    $desc .= " - {$loan->purpose}";
                }
            } elseif ($loan->purpose) {
                $desc .= " ({$loan->purpose})";
            }

            TransactionPostingService::create([
                'type' => 'income',
                'category_id' => $catId,
                'department_id' => $this->getDepartmentId(),
                'bank_account_id' => $loan->bank_account_id ?? null,
                'amount' => $disbursedAmount,
                'currency' => $loan->currency,
                'transaction_date' => $loan->claimed_date ?: now()->format('Y-m-d'),
                'description' => $desc,
                'reference_no' => "LOAN-ACT-{$loan->id}",
                'payment_method' => 'Normal',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($upfrontAmt > 0) {
                LedgerPostingService::postJournal($loan->claimed_date ?: date('Y-m-d'), 'Interest withheld on loan '.$loan->id, [['account_code' => '5200', 'debit' => $upfrontAmt, 'currency' => $loan->currency], ['account_code' => '2200', 'credit' => $upfrontAmt, 'currency' => $loan->currency]], $loan->id, 'LoanWithheldInterest');
            }
            $this->syncLoanMaturityReminder($id);

            return back()->with('success', 'Loan activated, interest schedule generated, and disbursement posted to bank account ledger!');
        }

        return back()->with('error', 'Loan cannot be activated.');
    }

    public function syncLoanMaturityReminder($loanId)
    {
        $loan = DB::table('loans')->where('id', $loanId)->first();
        if (! $loan || empty($loan->maturity_date)) {
            if (Schema::hasTable('reminders')) {
                DB::table('reminders')
                    ->where('type', 'loan')
                    ->where('reference_id', $loanId)
                    ->delete();
            }

            return;
        }
        if (! Schema::hasTable('reminders')) {
            return;
        }

        $remTitle = "Loan Principal Repayment Due: {$loan->lender_name} ({$loan->currency} ".number_format($loan->principal_amount, 2).')';
        $existing = DB::table('reminders')
            ->where('type', 'loan')
            ->where('reference_id', $loanId)
            ->first();

        $status = in_array($loan->status, ['settled', 'closed']) ? 'completed' : 'pending';

        if ($existing) {
            DB::table('reminders')->where('id', $existing->id)->update([
                'title' => $remTitle,
                'due_date' => $loan->maturity_date,
                'notify_before_days' => $loan->reminder_days ?? 3,
                'status' => $status,
                'notes' => "Full principal repayment of {$loan->currency} ".number_format($loan->principal_amount, 2)." due for {$loan->lender_name}",
                'updated_at' => now(),
            ]);
        } else {
            DB::table('reminders')->insert([
                'title' => $remTitle,
                'type' => 'loan',
                'reference_id' => $loanId,
                'reference_type' => 'Loan',
                'due_date' => $loan->maturity_date,
                'notify_before_days' => $loan->reminder_days ?? 3,
                'status' => $status,
                'notes' => "Full principal repayment of {$loan->currency} ".number_format($loan->principal_amount, 2)." due for {$loan->lender_name}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:active,settled,defaulted']);
        $loan = DB::table('loans')->where('id', $id)->lockForUpdate()->first();
        abort_unless($loan, 404);
        $status = $request->status;
        if ($status === 'active' && $loan->status === 'pending') {
            return $this->activate($id);
        }
        if ($status === 'settled') {
            $principal = $loan->principal_amount + DB::table('loan_principal_records')->where('loan_id', $id)->where('record_type', 'draw')->sum('amount') - DB::table('loan_principal_records')->where('loan_id', $id)->where('record_type', 'repayment')->sum('amount');
            $interest = DB::table('loan_interest_schedule')->where('loan_id', $id)->whereNotIn('status', ['paid', 'skipped'])->sum(DB::raw('interest_amount - paid_amount'));
            if ($principal > 0.005 || $interest > 0.005) {
                throw ValidationException::withMessages(['status' => 'Use full settlement to record outstanding principal and interest before closing this loan.']);
            }
        }
        abort_unless(in_array($loan->status, ['active', 'defaulted']) && $loan->status !== $status, 422);
        DB::table('loans')->where('id', $id)->update(['status' => $status, 'updated_at' => now()]);
        ActivityLogService::logUpdate('Loan', $id, ['status' => $loan->status], ['status' => $status]);
        $this->syncLoanMaturityReminder($id);

        return back()->with('success', 'Loan status updated.');
    }

    private function validateAttachments(Request $request): void
    {
        $request->validate([
            'attachments' => 'nullable|array',
            'attachments.*' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx',
        ]);
    }

    private function storeAttachments(Request $request, int $loanId): void
    {
        $disk = Storage::disk(config('filesystems.documents_disk', 'local'));
        $stored = [];
        try {
            foreach ($request->file('attachments', []) as $file) {
                $path = $disk->putFile('attachments/loans', $file);
                if (! $path) {
                    abort(503, 'The attachments could not be saved. Please try again.');
                }
                $stored[] = $path;
                DB::table('attachments')->insert([
                    'model_id' => $loanId,
                    'model_type' => 'Loan',
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'created_at' => now(),
                ]);
            }
        } catch (\Throwable $error) {
            $disk->delete($stored);
            throw $error;
        }
    }

    private function validateTerms(Request $request): void
    {
        $this->validateAttachments($request);
        $request->validate([
            'lender_name' => 'required_without:party_id|nullable|string|max:255', 'party_id' => 'nullable|exists:parties,id',
            'principal_amount' => 'required|numeric|gt:0', 'currency' => 'required|string|size:3', 'claimed_date' => 'required|date_format:Y-m-d',
            'loan_type' => 'sometimes|required|in:interest_separate,principal_and_interest',
            'interest_method' => 'required|in:no_interest,fixed_amount,percentage_rate,total_interest,equal_installments,custom_schedule',
            'interest_amount' => 'nullable|numeric|min:0', 'interest_rate' => 'nullable|numeric|min:0', 'total_interest' => 'nullable|numeric|min:0',
            'upfront_interest_amount' => 'nullable|numeric|min:0|lt:principal_amount', 'term_months' => 'nullable|integer|min:1|max:600',
            'due_day' => 'nullable|integer|min:1|max:31', 'maturity_date' => 'nullable|date_format:Y-m-d|after_or_equal:claimed_date',
            'bank_account_id' => 'nullable|exists:bank_accounts,id', 'custom_dates' => 'nullable|array', 'custom_dates.*' => 'required|date_format:Y-m-d',
            'custom_amounts' => 'nullable|array', 'custom_amounts.*' => 'required|numeric|gt:0',
        ]);
        if (count($request->input('custom_dates', [])) !== count($request->input('custom_amounts', []))) {
            throw ValidationException::withMessages(['custom_dates' => 'Provide an amount for each custom schedule date.']);
        }
    }

    public function settleInterestPeriod(Request $request, $id, $scheduleId)
    {
        $request->validate(['paid_amount' => 'required|numeric|gt:0', 'paid_date' => 'nullable|date_format:Y-m-d', 'notes' => 'nullable|string|max:1000']);
        PeriodLockService::checkLockedDate($request->paid_date ?: date('Y-m-d'));
        $loan = DB::table('loans')->where('id', $id)->lockForUpdate()->first();
        abort_unless($loan, 404);
        $period = DB::table('loan_interest_schedule')->where('id', $scheduleId)->where('loan_id', $id)->lockForUpdate()->first();
        abort_unless($period, 404);
        if (round($request->paid_amount + $period->paid_amount, 2) > round($period->interest_amount, 2)) {
            throw ValidationException::withMessages(['paid_amount' => 'Payment cannot exceed the outstanding interest.']);
        }
        $paidAmount = floatval($request->input('paid_amount'));
        $paidDate = $request->input('paid_date') ?: now()->format('Y-m-d');
        $notes = trim((string) $request->input('notes', ''));

        $schedule = $period;
        $bankAccountId = $request->input('bank_account_id') ?: ($loan->bank_account_id ?? null);

        $newPaidTotal = ($schedule->paid_amount ?? 0) + $paidAmount;
        $status = 'partially_paid';
        if ($newPaidTotal >= $schedule->interest_amount) {
            $status = 'paid';
        }

        DB::table('loan_interest_schedule')->where('id', $scheduleId)->update([
            'paid_amount' => $newPaidTotal,
            'paid_date' => $paidDate,
            'status' => $status,
        ]);

        // Auto-post Expense Transaction for Interest Payment
        if ($paidAmount > 0) {
            $catId = $this->getCategoryId('Interest Expense', 'expense');
            TransactionPostingService::create([
                'type' => 'expense',
                'category_id' => $catId,
                'department_id' => $this->getDepartmentId(),
                'bank_account_id' => $bankAccountId,
                'amount' => $paidAmount,
                'currency' => $loan->currency,
                'transaction_date' => $paidDate,
                'description' => "Interest Payment to {$loan->lender_name}".($notes !== '' ? "\nNote: {$notes}" : ''),
                'reference_no' => "LOAN-INT-{$loan->id}-{$scheduleId}",
                'payment_method' => 'Normal',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('success', 'Interest settled and posted to ledger.');
    }

    public function updateInterestPayment(Request $request, $id, $scheduleId, $paymentId)
    {
        $data = $request->validate([
            'paid_amount' => 'required|numeric|min:0.01|max:9999999999999.99|decimal:0,2',
            'paid_date' => 'required|date_format:Y-m-d', 'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'notes' => 'nullable|string|max:1000',
        ]);
        $data['bank_account_id'] ??= null;
        LoanInterestPaymentService::change((int) $id, (int) $scheduleId, (int) $paymentId, $data);
        $this->syncLoanMaturityReminder($id);

        return back()->with('success', 'Interest payment updated. Balances and ledger entries have been adjusted.');
    }

    public function deleteInterestPayment($id, $scheduleId, $paymentId)
    {
        LoanInterestPaymentService::change((int) $id, (int) $scheduleId, (int) $paymentId, null);
        $this->syncLoanMaturityReminder($id);

        return back()->with('success', 'Interest payment deleted and ledger entry reversed. Unpaid interest has been restored.');
    }

    public function markInterestNotNeeded($id, $scheduleId)
    {
        DB::table('loan_interest_schedule')->where('id', $scheduleId)->update(['status' => 'skipped']);

        return back()->with('success', 'Interest period skipped.');
    }

    public function editInterestAmount(Request $request, $id, $scheduleId)
    {
        $updateData = [
            'interest_amount' => $request->input('interest_amount'),
            'updated_at' => now(),
        ];
        if ($request->filled('due_date')) {
            $updateData['due_date'] = $request->input('due_date');
        }

        DB::table('loan_interest_schedule')
            ->where('id', $scheduleId)
            ->where('loan_id', $id)
            ->update($updateData);

        return back()->with('success', 'Interest schedule updated successfully.');
    }

    public function addInterestSchedule(Request $request, $id)
    {
        $request->validate([
            'due_date' => 'required|date',
            'interest_amount' => 'required|numeric|min:0',
        ]);

        DB::table('loan_interest_schedule')->insert([
            'loan_id' => $id,
            'due_date' => $request->input('due_date'),
            'interest_amount' => $request->input('interest_amount'),
            'paid_amount' => 0.00,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Additional interest schedule added successfully.');
    }

    public function recordPrincipalRepayment(Request $request, $id)
    {
        $request->validate(['source_loan_id' => 'nullable|integer|exists:loans,id', 'amount' => 'required|numeric|min:0.01|decimal:0,2', 'record_date' => 'nullable|date_format:Y-m-d', 'bank_account_id' => 'nullable|exists:bank_accounts,id']);
        PeriodLockService::checkLockedDate($request->record_date ?: date('Y-m-d'));
        $current = DB::table('loans')->where('id', $id)->lockForUpdate()->first();
        abort_unless($current && $current->status === 'active', 422);
        $outstanding = $current->principal_amount + DB::table('loan_principal_records')->where('loan_id', $id)->where('record_type', 'draw')->sum('amount') - DB::table('loan_principal_records')->where('loan_id', $id)->where('record_type', 'repayment')->sum('amount');
        if (round($request->amount, 2) > round($outstanding, 2)) {
            throw ValidationException::withMessages(['amount' => 'Repayment cannot exceed the outstanding principal.']);
        }
        $loan = DB::table('loans')->where('id', $id)->first();
        $amount = floatval($request->input('amount'));
        $recordDate = $request->input('record_date') ?: now()->format('Y-m-d');
        $pmMode = is_array($request->input('pm_mode')) ? current($request->input('pm_mode')) : ($request->input('pm_mode') ?: 'Normal');
        $bankAccountId = $request->input('bank_account_id') ?: ($loan->bank_account_id ?? null);

        $recordId = DB::table('loan_principal_records')->insertGetId([
            'loan_id' => $id,
            'record_type' => 'repayment',
            'amount' => $amount,
            'record_date' => $recordDate,
            'payment_mode' => $pmMode,
            'reference_no' => is_array($request->input('pm_reference')) ? current($request->input('pm_reference')) : $request->input('pm_reference'),
            'notes' => $request->input('notes'),
            'created_at' => now(),
        ]);

        if ($request->filled('source_loan_id')) {
            LoanFlowService::link((int) $id, $recordId, (int) $request->source_loan_id, $amount);
        }

        $this->recalculateReducingBalance($id);

        ActivityLogService::logCreate('LoanRepayment', $id, [
            'loan_id' => $id,
            'amount' => $amount,
            'record_date' => $recordDate,
        ], 'Recorded Loan Repayment');

        // Auto-post Expense Transaction for Principal Repayment
        if ($amount > 0) {
            $catId = $this->getCategoryId('Loan Principal Repayment', 'expense');
            TransactionPostingService::create([
                'type' => 'expense',
                'category_id' => $catId,
                'department_id' => $this->getDepartmentId(),
                'bank_account_id' => $bankAccountId,
                'amount' => $amount,
                'currency' => $loan->currency,
                'transaction_date' => $recordDate,
                'description' => "Principal Repayment to {$loan->lender_name}".($request->input('notes') ? " ({$request->input('notes')})" : ''),
                'reference_no' => "LOAN-PRIN-{$loan->id}",
                'payment_method' => $pmMode ?: 'Normal',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('success', 'Principal repayment recorded and posted to ledger.');
    }

    public function addPrincipalDraw(Request $request, $id)
    {
        $request->validate(['amount' => 'required|numeric|gt:0', 'record_date' => 'nullable|date_format:Y-m-d', 'bank_account_id' => 'nullable|exists:bank_accounts,id']);
        PeriodLockService::checkLockedDate($request->record_date ?: date('Y-m-d'));
        abort_unless(DB::table('loans')->where('id', $id)->where('status', 'active')->exists(), 422);
        $loan = DB::table('loans')->where('id', $id)->first();
        $amount = floatval($request->input('amount'));
        $recordDate = $request->input('record_date') ?: now()->format('Y-m-d');
        $bankAccountId = $request->input('bank_account_id') ?: ($loan->bank_account_id ?? null);

        DB::table('loan_principal_records')->insert([
            'loan_id' => $id,
            'record_type' => 'draw',
            'amount' => $amount,
            'record_date' => $recordDate,
            'notes' => $request->input('notes'),
            'created_at' => now(),
        ]);

        $this->recalculateReducingBalance($id);

        // Auto-post Income Transaction for Additional Draw
        if ($amount > 0) {
            $catId = $this->getCategoryId('Loan Disbursement', 'income');
            TransactionPostingService::create([
                'type' => 'income',
                'category_id' => $catId,
                'department_id' => $this->getDepartmentId(),
                'bank_account_id' => $bankAccountId,
                'amount' => $amount,
                'currency' => $loan->currency,
                'transaction_date' => $recordDate,
                'description' => "Additional Loan Draw from {$loan->lender_name}".($request->input('notes') ? " ({$request->input('notes')})" : ''),
                'reference_no' => "LOAN-DRAW-{$loan->id}",
                'payment_method' => 'Normal',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('success', 'Additional draw recorded and posted to ledger.');
    }

    public function settleFully(Request $request, $id)
    {
        $request->validate(['source_loan_id' => 'nullable|integer|exists:loans,id', 'settlement_date' => 'nullable|date_format:Y-m-d', 'paid_date' => 'nullable|date_format:Y-m-d', 'bank_account_id' => 'nullable|exists:bank_accounts,id']);
        $loan = DB::table('loans')->where('id', $id)->lockForUpdate()->first();
        abort_unless($loan && in_array($loan->status, ['active', 'defaulted']), 422);
        $repayments = DB::table('loan_principal_records')->where('loan_id', $id)->where('record_type', 'repayment')->sum('amount');
        $draws = DB::table('loan_principal_records')->where('loan_id', $id)->where('record_type', 'draw')->sum('amount');

        $outstanding = $loan->principal_amount + $draws - $repayments;
        $settlementDate = $request->input('settlement_date') ?: ($request->input('paid_date') ?: now()->format('Y-m-d'));
        $paymentMethod = is_array($request->input('payment_method')) ? current($request->input('payment_method')) : ($request->input('payment_method') ?: 'Normal');
        $referenceNo = $request->input('reference_no') ?: "LOAN-SETTLE-{$loan->id}";
        $notes = $request->input('notes') ?: 'Full Principal Settlement';
        $bankAccountId = $request->input('bank_account_id') ?: ($loan->bank_account_id ?? null);

        if ($request->filled('source_loan_id') && $outstanding <= 0) {
            throw ValidationException::withMessages(['source_loan_id' => 'There is no principal left to link. Record interest payments separately.']);
        }
        PeriodLockService::checkLockedDate($settlementDate);
        if ($outstanding > 0) {
            $recordId = DB::table('loan_principal_records')->insertGetId([
                'loan_id' => $id,
                'record_type' => 'repayment',
                'amount' => $outstanding,
                'record_date' => $settlementDate,
                'payment_mode' => $paymentMethod,
                'reference_no' => $referenceNo,
                'notes' => $notes,
                'created_at' => now(),
            ]);

            if ($request->filled('source_loan_id')) {
                LoanFlowService::link((int) $id, $recordId, (int) $request->source_loan_id, (float) $outstanding);
            }

            // Auto-post Expense Transaction for Full Principal Settlement
            $catId = $this->getCategoryId('Loan Principal Repayment', 'expense');
            TransactionPostingService::create([
                'type' => 'expense',
                'category_id' => $catId,
                'department_id' => $this->getDepartmentId(),
                'bank_account_id' => $bankAccountId,
                'amount' => $outstanding,
                'currency' => $loan->currency,
                'transaction_date' => $settlementDate,
                'description' => "Full Principal Settlement to {$loan->lender_name}",
                'reference_no' => $referenceNo,
                'payment_method' => $paymentMethod,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Also settle any remaining pending interest schedules for this loan
        $pendingInterestSchedules = DB::table('loan_interest_schedule')
            ->where('loan_id', $id)
            ->whereIn('status', ['pending', 'partially_paid', 'overdue'])
            ->get();

        foreach ($pendingInterestSchedules as $sched) {
            $unpaidInt = max(0, $sched->interest_amount - ($sched->paid_amount ?? 0));
            DB::table('loan_interest_schedule')->where('id', $sched->id)->update([
                'paid_amount' => $sched->interest_amount,
                'paid_date' => $settlementDate,
                'status' => 'paid',
                'updated_at' => now(),
            ]);

            if ($unpaidInt > 0) {
                // Post transaction for interest payment
                $intCatId = $this->getCategoryId('Interest Expense', 'expense');
                TransactionPostingService::create([
                    'type' => 'expense',
                    'category_id' => $intCatId,
                    'department_id' => $this->getDepartmentId(),
                    'bank_account_id' => $bankAccountId,
                    'amount' => $unpaidInt,
                    'currency' => $loan->currency,
                    'transaction_date' => $settlementDate,
                    'description' => "Interest Settlement for Loan {$loan->id} ({$loan->lender_name})",
                    'reference_no' => "LOAN-INT-{$loan->id}-{$sched->id}",
                    'payment_method' => $paymentMethod,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('loans')->where('id', $id)->update(['status' => 'settled', 'updated_at' => now()]);

        return back()->with('success', 'Loan fully settled on '.$settlementDate.' and transaction posted to ledger!');
    }

    private function getCategoryId($name, $type)
    {
        $cat = DB::table('categories')->where('name', $name)->first();
        if ($cat) {
            return $cat->id;
        }

        $companyId = DB::table('companies')->value('id') ?? 1;

        return DB::table('categories')->insertGetId([
            'company_id' => $companyId,
            'name' => $name,
            'type' => $type,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function getDepartmentId()
    {
        return DB::table('departments')->value('id') ?? 1;
    }

    private function recalculateReducingBalance($id)
    {
        $loan = DB::table('loans')->where('id', $id)->first();
        if ($loan->interest_method !== 'percentage_rate' || $loan->rate_basis !== 'reducing') {
            return;
        }

        $repayments = DB::table('loan_principal_records')->where('loan_id', $id)->where('record_type', 'repayment')->sum('amount');
        $draws = DB::table('loan_principal_records')->where('loan_id', $id)->where('record_type', 'draw')->sum('amount');
        $outstanding = $loan->principal_amount + $draws - $repayments;

        $newInterestAmount = $outstanding * ($loan->interest_rate / 100);

        // Update all future pending schedules
        DB::table('loan_interest_schedule')
            ->where('loan_id', $id)
            ->where('due_date', '>', now()->format('Y-m-d'))
            ->whereIn('status', ['pending'])
            ->update(['interest_amount' => $newInterestAmount]);
    }

    public function destroy($id)
    {
        $oldLoan = DB::table('loans')->where('id', $id)->first();
        abort_unless($oldLoan, 404);
        if ($oldLoan->status !== 'pending') {
            throw ValidationException::withMessages(['loan' => 'Activated loans cannot be deleted. Settle the facility to preserve financial history.']);
        }
        DB::transaction(function () use ($id) {
            // Delete associated interest schedule rows
            DB::table('loan_interest_schedule')->where('loan_id', $id)->delete();

            // Delete associated principal records
            DB::table('loan_principal_records')->where('loan_id', $id)->delete();

            // Delete associated reminders
            DB::table('reminders')
                ->where(function ($q) {
                    $q->where('type', 'loan')
                        ->orWhere('type', 'loan_interest')
                        ->orWhere('reference_type', 'Loan');
                })
                ->where('reference_id', $id)
                ->delete();

            // Delete associated attachments
            $attachments = DB::table('attachments')->where('model_type', 'Loan')->where('model_id', $id)->get();
            foreach ($attachments as $att) {
                foreach (array_unique([config('filesystems.documents_disk', 'local'), 'public']) as $disk) {
                    if (! empty($att->file_path) && Storage::disk($disk)->exists($att->file_path)) {
                        Storage::disk($disk)->delete($att->file_path);
                    }
                }
            }
            DB::table('attachments')->where('model_type', 'Loan')->where('model_id', $id)->delete();

            // Delete associated auto-transactions
            DB::table('transactions')
                ->where(function ($q) use ($id) {
                    $q->where('reference_no', "LOAN-ACT-{$id}")
                        ->orWhere('reference_no', "LOAN-PRIN-{$id}")
                        ->orWhere('reference_no', "LOAN-DRAW-{$id}")
                        ->orWhere('reference_no', "LOAN-SETTLE-{$id}")
                        ->orWhere('reference_no', 'LIKE', "LOAN-INT-{$id}-%")
                        ->orWhere('reference_no', 'LIKE', "LOAN-%-{$id}");
                })
                ->delete();

            // Delete the main loan record
            DB::table('loans')->where('id', $id)->delete();
        });

        return redirect('/loans')->with('success', 'Loan and all associated records deleted successfully!');
    }

    public function partyReport(Request $request)
    {
        $query = DB::table('loans');
        if ($request->filled('search')) {
            $search = '%'.$request->input('search').'%';
            $partyIds = DB::table('parties')->where('name', 'LIKE', $search)->pluck('id');
            $query->where(function ($q) use ($search, $partyIds) {
                $q->where('lender_name', 'LIKE', $search)
                    ->orWhereIn('party_id', $partyIds);
            });
        }
        $loans = $query->get();

        // Group loans by party_id or lender_name
        $grouped = $loans->groupBy(function ($item) {
            return $item->party_id ? 'party_'.$item->party_id : 'lender_'.preg_replace('/[^a-z0-9]/i', '_', strtolower($item->lender_name));
        });

        $allPartyReports = collect();
        foreach ($grouped as $key => $partyLoans) {
            $firstLoan = $partyLoans->first();
            $partyId = $firstLoan->party_id;
            $partyName = $firstLoan->lender_name;

            if ($partyId) {
                $p = DB::table('parties')->where('id', $partyId)->first();
                if ($p) {
                    $partyName = $p->name;
                }
            }

            $balances = $partyLoans->map(fn ($loan) => LoanBalanceService::calculate($loan));
            $totalBorrowed = $balances->sum('total_borrowed');
            $totalPrincipalRepaid = $balances->sum('principal_repaid');
            $totalInterestPaid = $balances->sum('interest_paid');
            $totalPendingInterest = $balances->sum('pending_interest');
            $outstandingPrincipal = $balances->sum('outstanding_principal');
            $totalPayables = $balances->sum('total_outstanding');
            $totalPaids = $balances->sum('total_paid');
            $activeCount = $partyLoans->where('status', 'active')->count();
            $settledCount = $partyLoans->where('status', 'settled')->count();
            $pendingCount = $partyLoans->where('status', 'pending')->count();

            $allPartyReports->push((object) [
                'key' => $key,
                'party_id' => $partyId,
                'lender_name' => $firstLoan->lender_name,
                'party_name' => $partyName,
                'loan_count' => $partyLoans->count(),
                'active_count' => $activeCount,
                'settled_count' => $settledCount,
                'pending_count' => $pendingCount,
                'total_borrowed' => $totalBorrowed,
                'total_principal_repaid' => $totalPrincipalRepaid,
                'total_interest_paid' => $totalInterestPaid,
                'total_paids' => $totalPaids,
                'outstanding_principal' => $outstandingPrincipal,
                'total_pending_interest' => $totalPendingInterest,
                'total_payables' => $totalPayables,
                'currency' => $firstLoan->currency ?? 'LKR',
            ]);
        }

        $overallBorrowed = $allPartyReports->sum('total_borrowed');
        $overallPaids = $allPartyReports->sum('total_paids');
        $overallPayables = $allPartyReports->sum('total_payables');
        $totalFacilitiesCount = $allPartyReports->sum('loan_count');

        // Paginate results (15 per page matching party master index)
        $currentPage = Paginator::resolveCurrentPage() ?: 1;
        $perPage = 15;
        $currentItems = $allPartyReports->slice(($currentPage - 1) * $perPage, $perPage)->values();
        $partyReports = new LengthAwarePaginator($currentItems, $allPartyReports->count(), $perPage, $currentPage, [
            'path' => Paginator::resolveCurrentPath(),
            'query' => $request->query(),
        ]);

        return view('loans-party-report', compact('partyReports', 'overallBorrowed', 'overallPaids', 'overallPayables', 'totalFacilitiesCount'));
    }

    public function partyFacilities(Request $request)
    {
        $query = DB::table('loans');

        if ($request->filled('party_id') && $request->input('party_id') !== 'null') {
            $query->where('party_id', $request->input('party_id'));
        } elseif ($request->filled('lender_name')) {
            $query->where('lender_name', $request->input('lender_name'));
        } else {
            return response()->json(['facilities' => []]);
        }

        $loans = $query->orderBy('created_at', 'desc')->get();

        $facilities = [];
        foreach ($loans as $loan) {
            $balance = LoanBalanceService::calculate($loan);

            $facilities[] = $balance + [
                'id' => $loan->id,
                'loan_code' => $loan->loan_code ?: ('LN-'.str_pad($loan->id, 4, '0', STR_PAD_LEFT)),
                'lender_name' => $loan->lender_name,
                'status' => $loan->status,
                'principal_amount' => (float) $loan->principal_amount,
                'claimed_date' => $loan->claimed_date,
                'maturity_date' => $loan->maturity_date,
                'term_months' => $loan->term_months,
                'currency' => $loan->currency ?: 'LKR',
                'purpose' => $loan->purpose ? strip_tags($loan->purpose) : null,
                'interest_method' => $loan->interest_method,
            ];
        }

        return response()->json(['facilities' => $facilities]);
    }
}
