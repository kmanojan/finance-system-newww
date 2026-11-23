<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PartyLedgerReport
{
    public function build(string $asOf, bool $canViewPayroll): array
    {
        $parties = DB::table('parties')->orderBy('name')->orderBy('id')
            ->get(['id', 'name', 'types', 'contact_person', 'deleted_at'])->keyBy('id');

        $links = DB::table('project_party')->where('role', 'client')->get()->groupBy('project_id');
        $projects = DB::table('projects')->get()->keyBy('id');
        $invoices = FinancialReportingService::issuedInvoices($asOf)->get()->keyBy('id');
        $payments = DB::table('payments')->whereDate('payment_date', '<=', $asOf)->get()->keyBy('id');
        $allocations = DB::table('payment_allocations')->join('payments as p', 'p.id', '=', 'payment_allocations.payment_id')
            ->whereDate('p.payment_date', '<=', $asOf)->get(['payment_allocations.*']);
        $byInvoice = $allocations->groupBy('invoice_id');
        $byPayment = $allocations->groupBy('payment_id');
        $entries = collect();

        foreach ($invoices as $invoice) {
            $allocated = $byInvoice->get($invoice->id, collect())
                ->filter(fn ($row) => $payments[$row->payment_id]->currency === $invoice->currency);
            $row = self::row('receivable', $invoice->client_id, $invoice->invoice_no, $invoice->currency,
                self::cents($invoice->amount), self::cents($allocated->sum('amount')),
                $invoice->issue_date ?? $invoice->invoice_date ?? substr($invoice->created_at ?? '', 0, 10),
                '/invoices/'.$invoice->id.'/view');
            $row->project = $projects->get($invoice->project_id);
            $row->due = $invoice->due_date;
            $row->activity = $allocated->map(fn ($allocation) => (object) [
                'date' => $payments[$allocation->payment_id]->payment_date,
                'label' => 'Receipt PAY-'.$allocation->payment_id,
                'amount' => self::cents($allocation->amount),
                'url' => '/payments/'.$allocation->payment_id,
            ])->sortByDesc('date');
            $entries->push($row);
        }

        $directInvoiceIds = $payments->pluck('invoice_id')->filter()->diff($invoices->keys())->unique();
        $linkedInvoices = $invoices->union(DB::table('invoices')->whereIn('id', $directInvoiceIds)->get(['id', 'project_id', 'client_id'])->keyBy('id'));
        $unassignedReceipts = collect();
        foreach ($payments as $payment) {
            $allocated = $byPayment->get($payment->id, collect());
            $unapplied = max(0, self::cents($payment->total_amount) - self::cents($allocated->sum('amount')));
            if (! $unapplied) {
                continue;
            }
            $direct = $linkedInvoices->get($payment->invoice_id);
            $projectId = $payment->project_id ?? $direct?->project_id;
            $clientIds = $allocated->map(fn ($row) => $linkedInvoices->get($row->invoice_id)?->client_id)->filter()->unique();
            $projectClients = $links->get($projectId, collect());
            $partyId = $direct?->client_id ?? ($clientIds->count() === 1 ? $clientIds->first() : null)
                ?? ($clientIds->isEmpty() && $projectClients->count() === 1 ? $projectClients->first()->party_id : null);
            $row = self::row('unapplied', $partyId, 'PAY-'.$payment->id, $payment->currency,
                self::cents($payment->total_amount), self::cents($allocated->sum('amount')), $payment->payment_date, '/payments/'.$payment->id);
            $row->project = $projects->get($projectId);
            if ($parties->has($partyId)) {
                $entries->push($row);
            } else {
                $unassignedReceipts->push($row);
            }
        }

        $loans = DB::table('loans')->where(fn ($query) => $query->whereNull('claimed_date')->orWhereDate('claimed_date', '<=', $asOf))->get();
        $loanIds = $loans->pluck('id');
        $principal = DB::table('loan_principal_records')->whereIn('loan_id', $loanIds)
            ->where(fn ($query) => $query->whereNull('record_date')->orWhereDate('record_date', '<=', $asOf))
            ->orderByDesc('record_date')->get()->groupBy('loan_id');
        $schedules = DB::table('loan_interest_schedule')->whereIn('loan_id', $loanIds)->orderBy('due_date')->get()->groupBy('loan_id');
        $partiesByName = $parties->whereNull('deleted_at')->groupBy(fn ($party) => mb_strtolower(trim($party->name)));
        $unlinkedLoans = collect();
        foreach ($loans as $loan) {
            $records = $principal->get($loan->id, collect());
            $periods = $schedules->get($loan->id, collect());
            $balance = LoanBalanceService::calculate($loan, $periods, $records);
            $matches = $partiesByName->get(mb_strtolower(trim($loan->lender_name)), collect());
            $partyId = $loan->party_id ?? ($matches->count() === 1 ? $matches->first()->id : null);
            $date = $loan->claimed_date ?? $loan->start_date;
            $active = $loan->status !== 'pending' && (! $date || $date <= $asOf);
            $row = self::row('loan', $partyId, ($loan->loan_code ?? null) ?: 'LN-'.$loan->id, $loan->currency,
                self::cents($balance['total_obligation']), self::cents($balance['paid_towards_payable']), $date, '/loans/'.$loan->id);
            $row->balance = $active ? self::cents($balance['total_outstanding']) : 0;
            $row->context = $balance['loan_type_label'];
            $row->status = $active ? self::state($row->balance, $row->paid, 'Settled') : 'Pending';
            $row->principal = self::cents($balance['outstanding_principal']);
            $row->interest = self::cents($balance['pending_interest']);
            $row->interestPaid = self::cents($balance['interest_paid']);
            $row->periods = $periods;
            $row->records = $records;
            if ($parties->has($partyId)) {
                $entries->push($row);
            } else {
                $row->context = $loan->lender_name;
                $unlinkedLoans->push($row);
            }
        }

        if (Schema::hasTable('vendor_bills')) {
            $vendorPaid = Schema::hasTable('vendor_payments')
                ? DB::table('vendor_payments')->whereDate('payment_date', '<=', $asOf)->groupBy('vendor_bill_id')
                    ->selectRaw('vendor_bill_id, SUM(amount) as paid')->pluck('paid', 'vendor_bill_id')
                : collect();
            foreach (DB::table('vendor_bills')->whereDate('issue_date', '<=', $asOf)->get() as $bill) {
                $paid = self::cents($vendorPaid->get($bill->id, 0));
                $row = self::row('vendor', $bill->vendor_id, $bill->bill_number, $bill->currency, self::cents($bill->amount), $paid, $bill->issue_date, '/payables/vendor-bills');
                $row->due = $bill->due_date;
                if ($bill->status === 'paid' && ! $paid) {
                    $row->balance = 0;
                    $row->status = 'Paid · legacy status';
                }
                $entries->push($row);
            }
        }

        if (Schema::hasTable('project_commissions')) {
            $entries = $entries->concat($this->commissions($projects, $invoices->groupBy('project_id'), $payments->groupBy('project_id'), $asOf));
        }

        $unlinkedStaff = collect();
        $statutory = $employeeCodes = collect();
        if ($canViewPayroll) {
            $employees = DB::table('employees')->get(['id', 'party_id', 'employee_code', 'full_name']);
            $employeeCodes = $employees->whereNotNull('party_id')->groupBy('party_id');
            $employeesByCode = $employees->filter(fn ($employee) => filled($employee->employee_code))
                ->groupBy(fn ($employee) => mb_strtolower(trim($employee->employee_code)));
            foreach ($this->staffReceivables($asOf, $employeesByCode) as $row) {
                if ($parties->has($row->party_id)) {
                    $entries->push($row);
                } else {
                    $unlinkedStaff->push($row);
                }
            }
            $salaryPayments = PayrollPayablesService::payments($asOf)->groupBy('item_id');
            foreach (PayrollPayablesService::items($asOf) as $item) {
                $row = self::row('salary', $item->party_id, CarbonImmutable::parse($item->month)->format('F Y').' net salary',
                    $item->currency, $item->net_cents, $item->paid_cents, $item->month, '/payroll/'.$item->run_id.'/employees/'.$item->id);
                $row->context = $item->company_name.' · '.$item->employee_code;
                $row->due = $item->salary_due_date;
                $row->epf = (int) $item->epf_cents;
                $row->etf = (int) $item->etf_cents;
                $row->apit = (int) $item->apit_cents;
                $row->payUrl = '/payroll/'.$item->run_id.'/salary-batches/create?employee_id='.$item->employee_id;
                $row->activity = $salaryPayments->get($item->id, collect())->map(fn ($payment) => (object) [
                    'date' => $payment->paid_on,
                    'label' => $payment->reference ?: 'Salary payment #'.$payment->id,
                    'amount' => (int) $payment->amount_cents,
                    'url' => '/payroll/'.$item->run_id.'#payments',
                ])->sortByDesc('date');
                $entries->push($row);
            }
            $statutory = PayrollStatutoryService::register(['status' => 'all', 'include_other' => true])->whereDate('month', '<=', $asOf)
                ->orderByDesc('month')->orderBy('company_name')->get()->each(function ($row) {
                    $row->balance_cents = max(0, (int) $row->balance_cents);
                    $row->payment_status = self::state($row->balance_cents, (int) $row->paid_cents, 'Paid');
                    $row->submission = match ($row->action) {
                        'submitted' => 'Submitted',
                        'reopened' => 'Reopened',
                        'prepared' => 'Prepared',
                        default => 'Not recorded',
                    };
                });
        }

        $unlinkedPending = collect();
        foreach (DB::table('transactions')->where('payment_status', 'pending')->whereIn('type', ['income', 'expense'])
            ->get(['id', 'party_id', 'type', 'amount', 'currency', 'transaction_date', 'description', 'reference_no', 'due_date']) as $tx) {
            $row = self::row($tx->type === 'income' ? 'pending_in' : 'pending_out', $tx->party_id,
                $tx->reference_no ?: 'TX-'.$tx->id, $tx->currency, self::cents($tx->amount), 0,
                $tx->transaction_date, '/transactions?transaction_id='.$tx->id);
            $row->context = $tx->description;
            $row->due = $tx->due_date;
            $row->status = 'Pending ledger entry';
            if ($parties->has($row->party_id)) {
                $entries->push($row);
            } else {
                $unlinkedPending->push($row);
            }
        }

        $contracts = $this->contracts($projects, $links);
        $byParty = $entries->groupBy('party_id');
        $parties->each(function ($party) use ($byParty, $employeeCodes, $contracts) {
            $party->entries = $byParty->get($party->id, collect())->sortByDesc('date')->groupBy('category');
            $party->balances = self::balances($byParty->get($party->id, collect()));
            $party->contracts = $contracts->get($party->id, collect());
            $party->roles = array_values(array_filter(array_map('trim', explode(',', $party->types ?? ''))));
            $party->employeeCodes = $employeeCodes->get($party->id, collect())->pluck('employee_code')->filter()->unique()->implode(', ');
        });

        $workspaceTotals = self::balances($entries->concat($unlinkedLoans)->concat($unassignedReceipts)->concat($unlinkedStaff)->concat($unlinkedPending));

        return compact('parties', 'statutory', 'unlinkedLoans', 'unassignedReceipts', 'unlinkedStaff', 'unlinkedPending', 'workspaceTotals');
    }

    public static function balances(Collection $rows): Collection
    {
        return $rows->groupBy('currency')->sortKeys()->map(fn ($currency) => [
            'receivable' => $currency->where('category', 'receivable')->sum('balance'),
            'staff_receivable' => $currency->whereIn('category', ['advance', 'staff_loan'])->sum('balance'),
            'loan' => $currency->where('category', 'loan')->sum('balance'),
            'salary' => $currency->where('category', 'salary')->sum('balance'),
            'other' => $currency->whereIn('category', ['vendor', 'commission'])->sum('balance'),
            'unapplied' => $currency->where('category', 'unapplied')->sum('balance'),
            'pending_in' => $currency->where('category', 'pending_in')->sum('balance'),
            'pending_out' => $currency->where('category', 'pending_out')->sum('balance'),
        ]);
    }

    private function staffReceivables(string $asOf, Collection $employeesByCode): Collection
    {
        $advances = SalaryAdvanceService::payments()->join('employees as e', 'e.id', '=', 'p.employee_id')
            ->addSelect('e.party_id', 'e.full_name', 'e.employee_code')
            ->where('p.status', 'posted')->whereDate('p.paid_on', '<=', $asOf)->get()
            ->map(function ($advance) {
                $row = self::row('advance', $advance->party_id, $advance->reference ?: 'ADV-'.$advance->id, $advance->currency,
                    (int) $advance->amount_cents, (int) $advance->recovered_cents, $advance->paid_on,
                    '/payroll/advances?'.http_build_query(['q' => $advance->employee_code ?: $advance->full_name]));
                $row->context = $advance->full_name;
                $row->due = $advance->recovery_month ?? null;

                return $row;
            });
        $loans = HistoricalEmployeeLoanService::receivables($asOf)->map(function ($entry) use ($employeesByCode) {
            $matching = $employeesByCode->get(mb_strtolower($entry->employee_code), collect());
            $employee = $matching->count() === 1 ? $matching->first() : null;
            $row = self::row('staff_loan', $employee?->party_id, $entry->entry_no, $entry->currency,
                self::cents($entry->debit), (int) $entry->recovered_cents, $entry->entry_date,
                $employee ? '/employees/'.$employee->id : '/journal-entries');
            $row->context = $employee?->full_name ?? $entry->employee_code;

            return $row;
        });

        return $advances->concat($loans);
    }

    private function contracts(Collection $projects, Collection $links): Collection
    {
        $changes = DB::table('change_requests')->whereIn('status', ['approved', 'invoiced'])->get()->groupBy('project_id');

        return $links->flatten(1)->filter(fn ($link) => $projects->has($link->project_id))->map(function ($link) use ($projects, $changes) {
            $project = $projects->get($link->project_id);

            return (object) [
                'party_id' => $link->party_id, 'id' => $project->id, 'name' => $project->name,
                'currency' => $project->currency, 'budget' => $project->budget_limit,
                'changes' => $changes->get($project->id, collect()),
            ];
        })->groupBy('party_id');
    }

    private function commissions(Collection $projects, Collection $invoices, Collection $payments, string $asOf): Collection
    {
        $paid = DB::table('commission_payments')->whereDate('payment_date', '<=', $asOf)->groupBy('project_commission_id')
            ->selectRaw('project_commission_id, SUM(amount) as paid')->pluck('paid', 'project_commission_id');

        return DB::table('project_commissions')->get()->map(function ($commission) use ($projects, $invoices, $payments, $asOf, $paid) {
            $project = $projects->get($commission->project_id);
            $billed = $invoices->get($commission->project_id, collect());
            $received = $payments->get($commission->project_id, collect());
            $currency = $commission->currency ?: ($project->currency ?? 'LKR');
            $convert = fn ($amount, $currencyFrom) => FinancialReportingService::convert((float) $amount, $currencyFrom, $asOf, $currency);
            $calculated = true;
            try {
                $earned = $commission->commission_type === 'percentage'
                    ? (match ($commission->calculation_basis) {
                        'invoiced' => $billed->sum(fn ($invoice) => $convert($invoice->amount, $invoice->currency)),
                        'collected' => $received->sum(fn ($payment) => $convert($payment->total_amount, $payment->currency)),
                        'budget' => $convert($project->budget_limit ?? 0, $project->currency ?? $currency),
                        default => 0,
                    }) * (float) $commission->percentage_value / 100
                    : (float) $commission->fixed_amount * (match ($commission->trigger_type) {
                        'invoice' => $billed->count(),
                        'milestone' => $received->count(),
                        default => 1,
                    });
            } catch (ValidationException) {
                $earned = 0;
                $calculated = false;
            }
            $row = self::row('commission', $commission->party_id, $project->name ?? 'Commission '.$commission->id, $currency,
                self::cents($earned), self::cents($paid->get($commission->id, 0)), $commission->effective_from ?? null,
                '/projects/'.$commission->project_id.'#commission-setup');
            if (! $calculated) {
                $row->status = 'Exchange rate required';
                $row->unpriced = true;
            }

            return $row;
        });
    }

    private static function row(string $category, ?int $partyId, string $label, ?string $currency, int $amount, int $paid, ?string $date, string $url): object
    {
        $balance = max(0, $amount - $paid);

        return (object) [
            'category' => $category, 'party_id' => $partyId, 'label' => $label, 'currency' => $currency ?: 'LKR',
            'amount' => $amount, 'paid' => $paid, 'balance' => $balance, 'date' => $date, 'url' => $url,
            'status' => self::state($balance, $paid, $category === 'receivable' ? 'Collected' : 'Paid'),
        ];
    }

    private static function state(int $balance, int $paid, string $settled): string
    {
        return $balance === 0 ? $settled : ($paid > 0 ? 'Partially paid' : 'Unpaid');
    }

    private static function cents($amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
