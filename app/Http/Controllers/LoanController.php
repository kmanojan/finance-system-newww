<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LoanController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('loans')->orderBy('created_at', 'desc');

        if ($request->filled('start_date')) {
            $query->where('claimed_date', '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $query->where('claimed_date', '<=', $request->input('end_date'));
        }
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', '=', $request->input('status'));
        }
        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function($q) use ($search) {
                $q->where('lender_name', 'LIKE', $search)
                  ->orWhere('purpose', 'LIKE', $search);
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
            // Calculate repayments and draws
            $repayments = DB::table('loan_principal_records')
                            ->where('loan_id', $loan->id)
                            ->where('record_type', 'repayment')
                            ->sum('amount');
            $draws = DB::table('loan_principal_records')
                            ->where('loan_id', $loan->id)
                            ->where('record_type', 'draw')
                            ->sum('amount');
            
            $outstandingPrincipal = max(0, $loan->principal_amount + $draws - $repayments);
            $loan->outstanding_principal = $outstandingPrincipal;
            $loan->principal_repaid = $repayments;
            
            // Total borrowed and interest paid apply to all loans
            $totalBorrowed += $loan->principal_amount + $draws;
            $totalPrincipalRepaid += $repayments;
            
            $interestPaid = DB::table('loan_interest_schedule')
                                    ->where('loan_id', $loan->id)
                                    ->sum('paid_amount');
            $totalInterestPaid += $interestPaid;
            $loan->interest_paid = $interestPaid;
            
            // Pending interest due calculation
            $pendingSchedules = DB::table('loan_interest_schedule')
                                    ->where('loan_id', $loan->id)
                                    ->whereIn('status', ['pending', 'partially_paid', 'overdue'])
                                    ->get();
                                    
            $loanPendingInterest = 0;
            foreach($pendingSchedules as $sched) {
                $dueAmt = max(0, $sched->interest_amount - ($sched->paid_amount ?? 0));
                $loanPendingInterest += $dueAmt;
                
                if ($loan->status === 'active' && \Carbon\Carbon::parse($sched->due_date)->isCurrentMonth()) {
                    $thisMonthPayable += $dueAmt;
                }
            }

            // Fallback for loans without generated schedule rows yet
            if ($loanPendingInterest == 0 && DB::table('loan_interest_schedule')->where('loan_id', $loan->id)->count() == 0) {
                if ($loan->interest_method === 'fixed_amount' && !empty($loan->interest_amount)) {
                    $loanPendingInterest = $loan->interest_amount;
                } elseif (!empty($loan->total_interest)) {
                    $loanPendingInterest = $loan->total_interest;
                }
            }

            $loan->pending_interest = $loanPendingInterest;
            $loan->total_outstanding = $outstandingPrincipal + $loanPendingInterest;
            $loan->total_paid = $loan->principal_repaid + $loan->interest_paid;

            if ($loan->status === 'active' || $loan->status === 'pending') {
                $totalOutstandingPrincipal += $outstandingPrincipal;
                $totalOutstandingInterest += $loanPendingInterest;

                // Check next due date
                $nextDue = DB::table('loan_interest_schedule')
                    ->where('loan_id', $loan->id)
                    ->whereIn('status', ['pending', 'partially_paid'])
                    ->orderBy('due_date', 'asc')
                    ->first();
                    
                $loan->next_due_date = $nextDue ? $nextDue->due_date : 'N/A';
            } else {
                $loan->next_due_date = 'N/A';
            }
        }
        
        $totalWantToPaid = $totalOutstandingPrincipal + $totalOutstandingInterest;
        $totalPaidAll = $totalPrincipalRepaid + $totalInterestPaid;
        $totalLoansCount = $loans->count();
        $activeLoansCount = $loans->where('status', 'active')->count();
        $settledLoansCount = $loans->where('status', 'settled')->count();
        
        $parties = DB::table('parties')->orderBy('name')->get();

        return view('loans', compact(
            'loans', 
            'parties',
            'totalBorrowed', 
            'totalPrincipalRepaid', 
            'totalInterestPaid',
            'totalPaidAll',
            'totalWantToPaid', 
            'thisMonthPayable',
            'totalLoansCount',
            'activeLoansCount',
            'settledLoansCount'
        ));
    }


    public function schedules()
    {
        $schedules = DB::table('loan_interest_schedule')
            ->join('loans', 'loan_interest_schedule.loan_id', '=', 'loans.id')
            ->select('loan_interest_schedule.*', 'loans.lender_name', 'loans.currency')
            ->orderBy('due_date', 'asc')
            ->get();

        return view('loans-schedules', compact('schedules'));
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
                DB::raw("COALESCE(loan_interest_schedule.paid_date, loan_interest_schedule.due_date) as date"),
                DB::raw("'interest' as category"),
                'loan_interest_schedule.paid_amount as amount',
                DB::raw("'Normal' as payment_mode"),
                DB::raw("NULL as reference_no"),
                DB::raw("'Interest Settlement' as notes"),
                'loans.lender_name',
                'loans.currency'
            );

        if ($request->filled('start_date')) {
            $queryInterest->where(DB::raw("COALESCE(loan_interest_schedule.paid_date, loan_interest_schedule.due_date)"), '>=', $request->input('start_date'));
        }
        if ($request->filled('end_date')) {
            $queryInterest->where(DB::raw("COALESCE(loan_interest_schedule.paid_date, loan_interest_schedule.due_date)"), '<=', $request->input('end_date'));
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
                'total_paid' => $pPaid + $iPaid
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
        $data = $request->except(['_token', 'attachments', 'custom_dates', 'custom_amounts']);
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
        
        if(empty($data['status'])) $data['status'] = 'pending';

        $loanId = DB::table('loans')->insertGetId($data);


        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments/loans', 'public');
                DB::table('attachments')->insert([
                    'model_id' => $loanId,
                    'model_type' => 'Loan',
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'created_at' => now()
                ]);
            }
        }

        // For custom schedule, we save the schedule immediately during store
        if ($data['interest_method'] === 'custom_schedule') {
            $customDates = $request->input('custom_dates', []);
            $customAmounts = $request->input('custom_amounts', []);
            $inserts = [];
            for($i = 0; $i < count($customDates); $i++) {
                if(!empty($customDates[$i]) && !empty($customAmounts[$i])) {
                    $inserts[] = [
                        'loan_id' => $loanId,
                        'due_date' => $customDates[$i],
                        'interest_amount' => $customAmounts[$i],
                        'status' => 'pending',
                        'created_at' => now(),
                    ];
                }
            }
            if (!empty($inserts)) {
                DB::table('loan_interest_schedule')->insert($inserts);
            }
        }

        // Schedule is generated later when loan is activated (for non-custom)
        if ($data['status'] === 'active' && $data['interest_method'] !== 'custom_schedule') {
            $this->generateInterestSchedule($loanId, $data['claimed_date'], $data);
        }

        return back()->with('success', 'Loan created successfully! Please activate it to generate the schedule.');
    }

    private function generateInterestSchedule($loanId, $startDate, $loanData, $startFromDate = null)
    {
        if ($loanData['interest_method'] === 'no_interest') return;

        $termMonths = !empty($loanData['term_months']) ? $loanData['term_months'] : 12; // Default to 12 if open-ended
        
        $dueDay = $loanData['due_day'] ?? Carbon::parse($startDate)->day;
        
        $inserts = [];
        $currentDate = Carbon::parse($startDate);
        
        $outstanding = $loanData['principal_amount'];
        
        for ($i = 1; $i <= $termMonths; $i++) {
            if (isset($loanData['frequency']) && $loanData['frequency'] === 'quarterly') {
                $currentDate->addMonths(3);
            } else {
                $currentDate->addMonth(); // Monthly
            }
            
            // Adjust day
            $dueDate = $currentDate->copy()->setDay(min($dueDay, $currentDate->daysInMonth));
            
            // Skip dates before startFromDate if regenerating
            if ($startFromDate && $dueDate->lt(Carbon::parse($startFromDate))) {
                continue;
            }
            
            $interestAmount = 0;
            if ($loanData['interest_method'] === 'fixed_amount') {
                $interestAmount = $loanData['interest_amount'] ?? 0;
            } elseif ($loanData['interest_method'] === 'percentage_rate') {
                if (isset($loanData['rate_basis']) && $loanData['rate_basis'] === 'reducing') {
                    $interestAmount = $outstanding * (($loanData['interest_rate'] ?? 0) / 100);
                } else {
                    $interestAmount = $loanData['principal_amount'] * (($loanData['interest_rate'] ?? 0) / 100);
                }
            } elseif ($loanData['interest_method'] === 'equal_installments') {
                $totalToPay = $loanData['principal_amount'] + ($loanData['total_interest'] ?? 0);
                $interestAmount = $totalToPay / $termMonths;
            }

            $inserts[] = [
                'loan_id' => $loanId,
                'due_date' => $dueDate->format('Y-m-d'),
                'interest_amount' => $interestAmount,
                'status' => 'pending',
                'created_at' => now(),
            ];
        }

        if (!empty($inserts)) {
            DB::table('loan_interest_schedule')->insert($inserts);
        }
    }

    public function show($id)
    {
        $loan = DB::table('loans')->where('id', $id)->first();
        if (!$loan) abort(404);

        $schedules = DB::table('loan_interest_schedule')
                        ->where('loan_id', $id)
                        ->orderBy('due_date', 'asc')
                        ->get();
                        
        $principalRecords = DB::table('loan_principal_records')
                                ->where('loan_id', $id)
                                ->orderBy('record_date', 'desc')
                                ->get();
                                
        $repayments = $principalRecords->where('record_type', 'repayment')->sum('amount');
        $draws = $principalRecords->where('record_type', 'draw')->sum('amount');
        $outstanding = $loan->principal_amount + $draws - $repayments;
        $loan->outstanding_principal = $outstanding;

        $attachments = DB::table('attachments')
                        ->where('model_type', 'Loan')
                        ->where('model_id', $id)
                        ->get();
                        
        return view('loans-show', compact('loan', 'schedules', 'principalRecords', 'attachments'));
    }

    public function activate($id)
    {
        $loan = DB::table('loans')->where('id', $id)->first();
        if ($loan && $loan->status === 'pending') {
            DB::table('loans')->where('id', $id)->update(['status' => 'active']);
            
            // convert to array for generateInterestSchedule
            $loanData = (array) $loan;
            if ($loanData['interest_method'] !== 'custom_schedule') {
                $this->generateInterestSchedule($id, $loan->claimed_date, $loanData);
            }
            
            // Auto-post Income Transaction for Loan Disbursement
            $catId = $this->getCategoryId('Loan Disbursement', 'income');
            DB::table('transactions')->insert([
                'type' => 'income',
                'category_id' => $catId,
                'department_id' => $this->getDepartmentId(),
                'amount' => $loan->principal_amount,
                'currency' => $loan->currency,
                'transaction_date' => $loan->claimed_date ?: now()->format('Y-m-d'),
                'description' => "Loan Disbursement from {$loan->lender_name}" . ($loan->purpose ? " ({$loan->purpose})" : ""),
                'reference_no' => "LOAN-ACT-{$loan->id}",
                'payment_method' => 'Normal',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return back()->with('success', 'Loan activated, interest schedule generated, and disbursement posted to ledger!');
        }
        return back()->with('error', 'Loan cannot be activated.');
    }

    public function updateStatus(Request $request, $id)
    {
        $newStatus = $request->input('status');
        DB::table('loans')->where('id', $id)->update([
            'status' => $newStatus,
            'updated_at' => now()
        ]);

        if ($newStatus === 'active') {
            $loan = DB::table('loans')->where('id', $id)->first();
            $hasSchedule = DB::table('loan_interest_schedule')->where('loan_id', $id)->exists();
            if (!$hasSchedule && $loan && $loan->interest_method !== 'custom_schedule') {
                $this->generateInterestSchedule($id, $loan->claimed_date ?: now()->format('Y-m-d'), (array)$loan);
            }
        }

        return back()->with('success', 'Loan status updated successfully.');
    }

    public function settleInterestPeriod(Request $request, $id, $scheduleId)
    {
        $paidAmount = floatval($request->input('paid_amount'));
        $paidDate = $request->input('paid_date') ?: now()->format('Y-m-d');
        
        $schedule = DB::table('loan_interest_schedule')->where('id', $scheduleId)->first();
        $loan = DB::table('loans')->where('id', $id)->first();
        
        $newPaidTotal = ($schedule->paid_amount ?? 0) + $paidAmount;
        $status = 'partially_paid';
        if ($newPaidTotal >= $schedule->interest_amount) {
            $status = 'paid';
        }
        
        DB::table('loan_interest_schedule')->where('id', $scheduleId)->update([
            'paid_amount' => $newPaidTotal,
            'paid_date' => $paidDate,
            'status' => $status
        ]);

        // Auto-post Expense Transaction for Interest Payment
        if ($paidAmount > 0) {
            $catId = $this->getCategoryId('Interest Expense', 'expense');
            DB::table('transactions')->insert([
                'type' => 'expense',
                'category_id' => $catId,
                'department_id' => $this->getDepartmentId(),
                'amount' => $paidAmount,
                'currency' => $loan->currency,
                'transaction_date' => $paidDate,
                'description' => "Interest Payment to {$loan->lender_name}",
                'reference_no' => "LOAN-INT-{$loan->id}-{$scheduleId}",
                'payment_method' => 'Normal',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        return back()->with('success', 'Interest settled and posted to ledger.');
    }

    public function markInterestNotNeeded($id, $scheduleId)
    {
        DB::table('loan_interest_schedule')->where('id', $scheduleId)->update(['status' => 'skipped']);
        return back()->with('success', 'Interest period skipped.');
    }

    public function editInterestAmount(Request $request, $id, $scheduleId)
    {
        DB::table('loan_interest_schedule')->where('id', $scheduleId)->update([
            'interest_amount' => $request->input('interest_amount')
        ]);
        return back()->with('success', 'Interest amount updated.');
    }

    public function addInterestSchedule(Request $request, $id)
    {
        $request->validate([
            'due_date' => 'required|date',
            'interest_amount' => 'required|numeric|min:0'
        ]);

        DB::table('loan_interest_schedule')->insert([
            'loan_id' => $id,
            'due_date' => $request->input('due_date'),
            'interest_amount' => $request->input('interest_amount'),
            'paid_amount' => 0.00,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return back()->with('success', 'Additional interest schedule added successfully.');
    }

    public function recordPrincipalRepayment(Request $request, $id)
    {
        $loan = DB::table('loans')->where('id', $id)->first();
        $amount = floatval($request->input('amount'));
        $recordDate = $request->input('record_date') ?: now()->format('Y-m-d');
        $pmMode = is_array($request->input('pm_mode')) ? current($request->input('pm_mode')) : ($request->input('pm_mode') ?: 'Normal');

        DB::table('loan_principal_records')->insert([
            'loan_id' => $id,
            'record_type' => 'repayment',
            'amount' => $amount,
            'record_date' => $recordDate,
            'payment_mode' => $pmMode,
            'reference_no' => is_array($request->input('pm_reference')) ? current($request->input('pm_reference')) : $request->input('pm_reference'),
            'notes' => $request->input('notes'),
            'created_at' => now()
        ]);
        
        $this->recalculateReducingBalance($id);

        // Auto-post Expense Transaction for Principal Repayment
        if ($amount > 0) {
            $catId = $this->getCategoryId('Loan Principal Repayment', 'expense');
            DB::table('transactions')->insert([
                'type' => 'expense',
                'category_id' => $catId,
                'department_id' => $this->getDepartmentId(),
                'amount' => $amount,
                'currency' => $loan->currency,
                'transaction_date' => $recordDate,
                'description' => "Principal Repayment to {$loan->lender_name}" . ($request->input('notes') ? " ({$request->input('notes')})" : ""),
                'reference_no' => "LOAN-PRIN-{$loan->id}",
                'payment_method' => $pmMode ?: 'Normal',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        return back()->with('success', 'Principal repayment recorded and posted to ledger.');
    }

    public function addPrincipalDraw(Request $request, $id)
    {
        $loan = DB::table('loans')->where('id', $id)->first();
        $amount = floatval($request->input('amount'));
        $recordDate = $request->input('record_date') ?: now()->format('Y-m-d');

        DB::table('loan_principal_records')->insert([
            'loan_id' => $id,
            'record_type' => 'draw',
            'amount' => $amount,
            'record_date' => $recordDate,
            'notes' => $request->input('notes'),
            'created_at' => now()
        ]);
        
        $this->recalculateReducingBalance($id);

        // Auto-post Income Transaction for Additional Draw
        if ($amount > 0) {
            $catId = $this->getCategoryId('Loan Disbursement', 'income');
            DB::table('transactions')->insert([
                'type' => 'income',
                'category_id' => $catId,
                'department_id' => $this->getDepartmentId(),
                'amount' => $amount,
                'currency' => $loan->currency,
                'transaction_date' => $recordDate,
                'description' => "Additional Loan Draw from {$loan->lender_name}" . ($request->input('notes') ? " ({$request->input('notes')})" : ""),
                'reference_no' => "LOAN-DRAW-{$loan->id}",
                'payment_method' => 'Normal',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        return back()->with('success', 'Additional draw recorded and posted to ledger.');
    }
    
    public function settleFully(Request $request, $id)
    {
        $loan = DB::table('loans')->where('id', $id)->first();
        $repayments = DB::table('loan_principal_records')->where('loan_id', $id)->where('record_type', 'repayment')->sum('amount');
        $draws = DB::table('loan_principal_records')->where('loan_id', $id)->where('record_type', 'draw')->sum('amount');
        
        $outstanding = $loan->principal_amount + $draws - $repayments;
        
        if ($outstanding > 0) {
            DB::table('loan_principal_records')->insert([
                'loan_id' => $id,
                'record_type' => 'repayment',
                'amount' => $outstanding,
                'record_date' => now()->format('Y-m-d'),
                'notes' => 'Full Settlement',
                'created_at' => now()
            ]);

            // Auto-post Expense Transaction for Full Principal Settlement
            $catId = $this->getCategoryId('Loan Principal Repayment', 'expense');
            DB::table('transactions')->insert([
                'type' => 'expense',
                'category_id' => $catId,
                'department_id' => $this->getDepartmentId(),
                'amount' => $outstanding,
                'currency' => $loan->currency,
                'transaction_date' => now()->format('Y-m-d'),
                'description' => "Full Principal Settlement to {$loan->lender_name}",
                'reference_no' => "LOAN-SETTLE-{$loan->id}",
                'payment_method' => 'Normal',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        DB::table('loans')->where('id', $id)->update(['status' => 'settled']);
        
        return back()->with('success', 'Loan fully settled and transaction posted to ledger!');
    }
    
    private function getCategoryId($name, $type)
    {
        $cat = DB::table('categories')->where('name', $name)->first();
        if ($cat) return $cat->id;

        $companyId = DB::table('companies')->value('id') ?? 1;
        return DB::table('categories')->insertGetId([
            'company_id' => $companyId,
            'name' => $name,
            'type' => $type,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function getDepartmentId()
    {
        return DB::table('departments')->value('id') ?? 1;
    }
    
    private function recalculateReducingBalance($id)
    {
        $loan = DB::table('loans')->where('id', $id)->first();
        if ($loan->interest_method !== 'percentage_rate' || $loan->rate_basis !== 'reducing') return;
        
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
        DB::transaction(function() use ($id) {
            // Delete associated interest schedule rows
            DB::table('loan_interest_schedule')->where('loan_id', $id)->delete();

            // Delete associated principal records
            DB::table('loan_principal_records')->where('loan_id', $id)->delete();

            // Delete associated reminders
            DB::table('reminders')->where('type', 'loan_interest')->where('reference_id', $id)->delete();

            // Delete associated attachments
            $attachments = DB::table('attachments')->where('model_type', 'Loan')->where('model_id', $id)->get();
            foreach ($attachments as $att) {
                if (!empty($att->file_path) && \Illuminate\Support\Facades\Storage::disk('public')->exists($att->file_path)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($att->file_path);
                }
            }
            DB::table('attachments')->where('model_type', 'Loan')->where('model_id', $id)->delete();

            // Delete associated auto-transactions
            DB::table('transactions')->where('reference_no', 'LIKE', "LOAN-%-{$id}")->delete();
            DB::table('transactions')->where('reference_no', 'LIKE', "LOAN-ACT-{$id}")->delete();
            DB::table('transactions')->where('reference_no', 'LIKE', "LOAN-PRIN-{$id}")->delete();
            DB::table('transactions')->where('reference_no', 'LIKE', "LOAN-DRAW-{$id}")->delete();
            DB::table('transactions')->where('reference_no', 'LIKE', "LOAN-SETTLE-{$id}")->delete();

            // Delete the main loan record
            DB::table('loans')->where('id', $id)->delete();
        });

        return redirect('/loans')->with('success', 'Loan and all associated records deleted successfully!');
    }

    public function partyReport(Request $request)
    {
        $query = DB::table('loans');
        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where('lender_name', 'LIKE', $search);
        }
        $loans = $query->get();

        // Group loans by party_id or lender_name
        $grouped = $loans->groupBy(function($item) {
            return $item->party_id ? 'party_' . $item->party_id : 'lender_' . preg_replace('/[^a-z0-9]/i', '_', strtolower($item->lender_name));
        });

        $partyReports = collect();
        foreach ($grouped as $key => $partyLoans) {
            $firstLoan = $partyLoans->first();
            $partyId = $firstLoan->party_id;
            $partyName = $firstLoan->lender_name;

            if ($partyId) {
                $p = DB::table('parties')->where('id', $partyId)->first();
                if ($p) $partyName = $p->name;
            }

            $totalBorrowed = 0;
            $totalPrincipalRepaid = 0;
            $totalInterestPaid = 0;
            $totalPendingInterest = 0;
            $activeCount = 0;

            foreach ($partyLoans as $loan) {
                if ($loan->status === 'active') $activeCount++;

                $repayments = DB::table('loan_principal_records')
                    ->where('loan_id', $loan->id)
                    ->where('record_type', 'repayment')
                    ->sum('amount');
                $draws = DB::table('loan_principal_records')
                    ->where('loan_id', $loan->id)
                    ->where('record_type', 'draw')
                    ->sum('amount');

                $totalBorrowed += ($loan->principal_amount + $draws);
                $totalPrincipalRepaid += $repayments;

                $interestPaid = DB::table('loan_interest_schedule')
                    ->where('loan_id', $loan->id)
                    ->sum('paid_amount');
                $totalInterestPaid += $interestPaid;

                $pendingSchedules = DB::table('loan_interest_schedule')
                    ->where('loan_id', $loan->id)
                    ->whereIn('status', ['pending', 'partially_paid', 'overdue'])
                    ->get();

                $pendingInt = 0;
                foreach ($pendingSchedules as $s) {
                    $pendingInt += max(0, $s->interest_amount - ($s->paid_amount ?? 0));
                }
                $totalPendingInterest += $pendingInt;
            }

            $outstandingPrincipal = max(0, $totalBorrowed - $totalPrincipalRepaid);
            $totalPayables = $outstandingPrincipal + $totalPendingInterest;
            $totalPaids = $totalPrincipalRepaid + $totalInterestPaid;

            $partyReports->push((object)[
                'party_id' => $partyId,
                'party_name' => $partyName,
                'loan_count' => $partyLoans->count(),
                'active_count' => $activeCount,
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

        $overallBorrowed = $partyReports->sum('total_borrowed');
        $overallPaids = $partyReports->sum('total_paids');
        $overallPayables = $partyReports->sum('total_payables');

        return view('loans-party-report', compact('partyReports', 'overallBorrowed', 'overallPaids', 'overallPayables'));
    }
}

