<?php

namespace App\Http\Controllers;

use App\Models\CostAllocation;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Server;
use App\Services\AuditPayablesService;
use App\Services\FinancialReportingService;
use App\Services\FinancialReportingService as Finance;
use App\Services\PayrollPayablesService;
use App\Services\PayrollStatutoryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function auditPayables(Request $request)
    {
        abort_unless($request->user()?->isAdmin() || in_array(strtolower($request->user()?->role ?? ''), ['manager', 'accountant']), 403, 'Employee salary details are restricted to authorised finance staff.');
        $request->validate(['as_of' => 'nullable|date_format:Y-m-d|before_or_equal:'.PayrollStatutoryService::today(), 'view' => 'nullable|in:remaining,all']);
        $asOf = $request->input('as_of') ?: PayrollStatutoryService::today();
        $report = AuditPayablesService::build($asOf, $request->input('view', 'remaining') !== 'all');

        return view('reports.audit-payables', compact('report'));
    }

    private function getDateRange(Request $request)
    {
        $request->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);
        $from = $request->query('from', date('Y-m-01'));
        $to = $request->query('to', date('Y-m-t'));

        return compact('from', 'to');
    }

    public function index(Request $request)
    {
        return redirect()->route('reports.pnl');
    }

    public function pnl(Request $request)
    {
        extract($this->getDateRange($request));

        $balances = Finance::ledgerBalances($from, $to, true);
        $income = $balances->where('type', 'revenue')->map(fn ($row) => (object) ['category' => $row->name, 'total' => -$row->balance])->values();
        $expense = $balances->where('type', 'expense')->map(fn ($row) => (object) ['category' => $row->name, 'total' => $row->balance])->values();
        $unpostedCount = Finance::unpostedCount();

        $totalIncome = $income->sum('total');
        $totalExpense = $expense->sum('total');
        $netProfit = $totalIncome - $totalExpense;

        $data = compact('income', 'expense', 'totalIncome', 'totalExpense', 'netProfit');

        return view('reports.pnl', compact('from', 'to', 'data', 'unpostedCount'));
    }

    public function expenses(Request $request)
    {
        extract($this->getDateRange($request));

        $expenses = Finance::transactions($from, $to)->where('type', 'expense')->where('financing', false)->groupBy('category_name')->map(fn ($rows, $name) => (object) ['category' => $name, 'total' => $rows->sum('base_amount')])->sortByDesc('total')->values();

        $totalExpense = $expenses->sum('total');
        $data = compact('expenses', 'totalExpense');

        return view('reports.expenses', compact('from', 'to', 'data'));
    }

    public function commissions(Request $request)
    {
        extract($this->getDateRange($request));

        $parties = DB::table('parties')
            ->where(function ($q) {
                $q->where('types', 'LIKE', '%partner%')
                    ->orWhere('types', 'LIKE', '%vendor%');
            })
            ->whereNull('deleted_at')
            ->get();

        $reportData = [];

        foreach ($parties as $party) {
            $commissions = DB::table('project_commissions')
                ->join('projects', 'project_commissions.project_id', '=', 'projects.id')
                ->where('project_commissions.party_id', $party->id)
                ->select('project_commissions.*', 'projects.name as project_name')
                ->get();

            $partyComms = [];
            $partyTotalComm = 0;
            $partyTotalPaid = 0;
            $partyTotalPayable = 0;

            foreach ($commissions as $c) {
                $proj = DB::table('projects')->where('id', $c->project_id)->first();
                $totInvoiced = DB::table('invoices')->where('project_id', $c->project_id)->whereNotIn('status', ['draft', 'pending_approval'])->sum('grand_total');
                $totCollected = DB::table('payments')->where('project_id', $c->project_id)->sum('total_amount');

                $commAmount = 0;
                if ($c->commission_type === 'percentage') {
                    $pct = $c->percentage_value ?? 0;
                    if ($c->calculation_basis === 'invoiced') {
                        $commAmount = $totInvoiced * ($pct / 100);
                    } elseif ($c->calculation_basis === 'collected') {
                        $commAmount = $totCollected * ($pct / 100);
                    } elseif ($c->calculation_basis === 'budget') {
                        $commAmount = ($proj->budget_limit ?? 0) * ($pct / 100);
                    }
                } else { // fixed
                    $commAmount = $c->fixed_amount ?? 0;
                }

                $paidSum = Schema::hasTable('commission_payments')
                    ? DB::table('commission_payments')->where('project_commission_id', $c->id)->sum('amount')
                    : 0;

                $netPayable = max(0, $commAmount - $paidSum);

                $partyTotalComm += $commAmount;
                $partyTotalPaid += $paidSum;
                $partyTotalPayable += $netPayable;

                $partyComms[] = (object) [
                    'project_name' => $c->project_name,
                    'percentage' => (float) ($c->percentage_value ?? 0),
                    'invoiced_paid' => (float) ($totCollected ?: $totInvoiced),
                    'commission_earned' => (float) $commAmount,
                    'paid_amount' => (float) $paidSum,
                    'net_payable' => (float) $netPayable,
                ];
            }

            if (count($partyComms) > 0) {
                $reportData[] = (object) [
                    'party_id' => $party->id,
                    'party_name' => $party->name,
                    'party_types' => $party->types,
                    'total_commission' => $partyTotalComm,
                    'total_paid' => $partyTotalPaid,
                    'total_payable' => $partyTotalPayable,
                    'items' => $partyComms,
                ];
            }
        }

        $data = ['parties' => $reportData];

        return view('reports.commissions', compact('from', 'to', 'data'));
    }

    public function projectStatus(Request $request)
    {
        extract($this->getDateRange($request));
        $projects = DB::table('projects')->get();
        $data = ['projects' => $projects];

        return view('reports.project_status', compact('from', 'to', 'data'));
    }

    public function clientHealth(Request $request)
    {
        extract($this->getDateRange($request));
        $clients = DB::table('parties')->where('types', 'LIKE', '%client%')->get();
        $invoices = FinancialReportingService::scope(FinancialReportingService::issuedInvoices($to))->get()->groupBy('client_id');
        foreach ($clients as $client) {
            $client->outstanding = $client->overdue = $client->period_billed = 0;
            $client->oldest_days = 0;
            foreach ($invoices->get($client->id, collect()) as $invoice) {
                $date = $invoice->issue_date ?? $invoice->invoice_date ?? substr($invoice->created_at, 0, 10);
                $outstanding = FinancialReportingService::outstanding($invoice, $to);
                $converted = FinancialReportingService::convert($outstanding, $invoice->currency, $to);
                $client->outstanding += $converted;
                if ($date >= $from) {
                    $client->period_billed += FinancialReportingService::convert($invoice->amount, $invoice->currency, $date);
                }
                if ($outstanding > 0 && $invoice->due_date && $invoice->due_date < $to) {
                    $client->overdue += $converted;
                    $client->oldest_days = max($client->oldest_days, (int) Carbon::parse($invoice->due_date)->diffInDays($to));
                }
            }
            $client->health = $client->oldest_days > 30 ? 'At risk' : ($client->overdue > 0 ? 'Attention' : ($invoices->has($client->id) ? 'Current' : 'No billing history'));
        }
        $data = ['clients' => $clients];

        return view('reports.client_health', compact('from', 'to', 'data'));
    }

    public function costAllocations(Request $request)
    {
        extract($this->getDateRange($request));
        $type = $request->query('type');
        $projectId = $request->query('project_id');
        $employeeId = $request->query('employee_id');
        $serverId = $request->query('server_id');

        $query = CostAllocation::with(['project', 'employee', 'server'])
            ->whereDate('period_start', '>=', $from)->whereDate('period_start', '<=', $to);

        if ($type) {
            $query->where('type', $type);
        }
        if ($projectId) {
            $query->where('project_id', $projectId);
        }
        if ($employeeId) {
            $query->where('employee_id', $employeeId);
        }
        if ($serverId) {
            $query->where('server_id', $serverId);
        }

        $allocations = $query->orderBy('period_start', 'desc')->get();

        foreach ($allocations as $allocation) {
            $allocation->base_amount = Finance::convert($allocation->amount, $allocation->currency, $allocation->period_start->format('Y-m-d'));
        }
        $totalCost = $allocations->sum('base_amount');
        $employeeCost = $allocations->where('type', 'employee')->sum('base_amount');
        $serverCost = $allocations->where('type', 'server')->sum('base_amount');
        $otherCost = $allocations->where('type', 'other')->sum('base_amount');

        $projectBreakdown = $allocations->groupBy('project_id')->map(function ($items) {
            return [
                'project_name' => $items->first()->project->name ?? 'Unassigned Project',
                'total' => $items->sum('base_amount'),
                'count' => $items->count(),
            ];
        })->sortByDesc('total');

        $projects = Project::orderBy('name')->get();
        $employees = Employee::where('status', 'active')->orderBy('full_name')->get();
        $servers = Server::where('is_active', true)->orderBy('name')->get();

        return view('reports.cost_allocations', compact(
            'allocations', 'totalCost', 'employeeCost', 'serverCost', 'otherCost',
            'projectBreakdown', 'projects', 'employees', 'servers',
            'from', 'to', 'type', 'projectId', 'employeeId', 'serverId'
        ));
    }

    public function balanceSheet(Request $request)
    {
        extract($this->getDateRange($request));
        $asOfDate = $to;

        $balances = Finance::ledgerBalances('1900-01-01', $asOfDate);
        $assets = $balances->where('type', 'asset');
        $liabilities = $balances->where('type', 'liability');
        $equity = $balances->where('type', 'equity');
        $totalAssets = $assets->sum('balance');
        $totalLiabilities = -$liabilities->sum('balance');
        $retainedEarnings = -$balances->whereIn('type', ['revenue', 'expense'])->sum('balance');
        $totalEquity = -$equity->sum('balance') + $retainedEarnings;
        $unreconciledDifference = round($totalAssets - $totalLiabilities - $totalEquity, 2);
        $accountsReceivable = $assets->filter(fn ($row) => str_ends_with($row->code, '1200'))->sum('balance');
        $unpostedCount = Finance::unpostedCount();

        return view('reports.balance_sheet', compact('from', 'to', 'asOfDate', 'assets', 'liabilities', 'equity', 'totalAssets', 'totalLiabilities', 'retainedEarnings', 'totalEquity', 'unreconciledDifference', 'accountsReceivable', 'unpostedCount'));
    }

    public function cashFlow(Request $request)
    {
        extract($this->getDateRange($request));

        $rows = Finance::transactions($from, $to);
        $operating = $rows->where('financing', false);
        $financing = $rows->where('financing', true);
        $operatingInflows = $operating->where('type', 'income')->sum('base_amount');
        $operatingOutflows = $operating->where('type', 'expense')->sum('base_amount');
        $netOperatingCash = $operatingInflows - $operatingOutflows;
        $financingInflows = $financing->where('type', 'income')->sum('base_amount');
        $financingOutflows = $financing->where('type', 'expense')->sum('base_amount');
        $netFinancingCash = $financingInflows - $financingOutflows;
        $netCashFlow = $netOperatingCash + $netFinancingCash;

        return view('reports.cash_flow', compact('from', 'to', 'operatingInflows', 'operatingOutflows', 'netOperatingCash', 'financingInflows', 'financingOutflows', 'netFinancingCash', 'netCashFlow'));
    }

    public function arAging(Request $request)
    {
        extract($this->getDateRange($request));
        $clients = DB::table('parties')->where('types', 'LIKE', '%client%')->get();

        $agingData = [];
        $now = Carbon::parse($to);

        foreach ($clients as $client) {
            $invoices = Finance::scope(Finance::issuedInvoices($to))
                ->where('client_id', $client->id)
                ->get();

            $current = 0; // 0-30 days
            $b30_60 = 0; // 31-60 days
            $b60_90 = 0; // 61-90 days
            $b90_plus = 0; // 90+ days

            foreach ($invoices as $inv) {
                $dueDate = Carbon::parse($inv->due_date ?? $inv->invoice_date);
                $days = $dueDate->diffInDays($now, false);
                $amt = Finance::convert(Finance::outstanding($inv, $to), $inv->currency, $to);

                if ($days <= 30) {
                    $current += $amt;
                } elseif ($days <= 60) {
                    $b30_60 += $amt;
                } elseif ($days <= 90) {
                    $b60_90 += $amt;
                } else {
                    $b90_plus += $amt;
                }
            }

            $total = $current + $b30_60 + $b60_90 + $b90_plus;

            if ($total > 0) {
                $agingData[] = (object) [
                    'client_name' => $client->name,
                    'current' => $current,
                    'b30_60' => $b30_60,
                    'b60_90' => $b60_90,
                    'b90_plus' => $b90_plus,
                    'total' => $total,
                ];
            }
        }

        return view('reports.ar_aging', compact('from', 'to', 'agingData'));
    }

    public function projectProfitability(Request $request)
    {
        extract($this->getDateRange($request));
        $projects = DB::table('projects')->get();
        $report = [];

        foreach ($projects as $p) {
            $invoiced = Finance::issuedInvoices($to)->where('project_id', $p->id)->whereRaw('COALESCE(issue_date,invoice_date,DATE(created_at)) >= ?', [$from])->get()->sum(fn ($row) => Finance::convert($row->amount, $row->currency, $row->issue_date ?? $row->invoice_date, $p->currency));
            $collected = DB::table('payments')->where('project_id', $p->id)->whereBetween('payment_date', [$from, $to])->get()->sum(fn ($row) => Finance::convert($row->total_amount, $row->currency, $row->payment_date, $p->currency));
            $costAllocations = DB::table('cost_allocations')->whereNull('deleted_at')->where('project_id', $p->id)->whereBetween('period_start', [$from, $to])->get()->sum(fn ($row) => Finance::convert($row->amount, $row->currency, $row->period_start, $p->currency));
            $directExpenses = DB::table('transactions')->where('project_id', $p->id)->where('type', 'expense')->where('payment_status', 'completed')->whereBetween('transaction_date', [$from, $to])->get()->sum(fn ($row) => Finance::convert($row->amount, $row->currency, $row->transaction_date, $p->currency));
            $totalCost = $costAllocations + $directExpenses;
            $netProfit = $invoiced - $totalCost;
            $margin = $invoiced > 0 ? ($netProfit / $invoiced) * 100 : 0;

            $report[] = (object) [
                'project_name' => $p->name,
                'currency' => $p->currency ?? 'LKR',
                'invoiced' => $invoiced,
                'collected' => $collected,
                'cost_allocations' => $costAllocations,
                'direct_expenses' => $directExpenses,
                'total_cost' => $totalCost,
                'net_profit' => $netProfit,
                'margin' => $margin,
            ];
        }

        usort($report, fn ($a, $b) => $b->net_profit <=> $a->net_profit);

        return view('reports.project_profitability', compact('from', 'to', 'report'));
    }

    public function clientStatement(Request $request)
    {
        extract($this->getDateRange($request));
        $clientId = $request->query('client_id');
        $clients = DB::table('parties')->where('types', 'LIKE', '%client%')->get();
        $statement = [];
        $selectedClient = null;
        $openingBalance = 0;

        if ($clientId) {
            $selectedClient = DB::table('parties')->where('id', $clientId)->first();

            $invoices = Finance::issuedInvoices($to)->where('client_id', $clientId)->whereRaw('COALESCE(issue_date, invoice_date, DATE(created_at)) >= ?', [$from])->get()->map(function ($i) {
                $i->entry_type = 'Invoice';
                $i->date = $i->issue_date ?? $i->invoice_date;

                return $i;
            });

            $payments = DB::table('payments')->join('payment_allocations', 'payment_allocations.payment_id', '=', 'payments.id')->join('invoices', 'invoices.id', '=', 'payment_allocations.invoice_id')->where('invoices.client_id', $clientId)->whereBetween('payments.payment_date', [$from, $to])->select('payments.id', 'payments.payment_date', 'payments.currency', DB::raw('SUM(payment_allocations.amount) as total_amount'))->groupBy('payments.id', 'payments.payment_date', 'payments.currency')->get()->map(function ($p) {
                $p->entry_type = 'Payment';
                $p->date = $p->payment_date;

                return $p;
            });

            $before = Carbon::parse($from)->subDay()->toDateString();
            $openingBalance = Finance::scope(Finance::issuedInvoices($before))->where('client_id', $clientId)->get()->sum(fn ($invoice) => Finance::convert(Finance::outstanding($invoice, $before), $invoice->currency, $before));
            $statement = $invoices->concat($payments)->sortBy('date')->values();
            foreach ($statement as $row) {
                $row->amount_base = Finance::convert($row->entry_type === 'Invoice' ? $row->amount : $row->total_amount, $row->currency, $row->date);
            }
        }

        return view('reports.client_statement', compact('from', 'to', 'clients', 'selectedClient', 'clientId', 'statement', 'openingBalance'));
    }

    public function bankReconciliation(Request $request)
    {
        extract($this->getDateRange($request));
        $bankAccounts = Schema::hasTable('bank_accounts') ? DB::table('bank_accounts')->get() : collect();

        foreach ($bankAccounts as $acc) {
            $inflow = Schema::hasTable('transactions') ? DB::table('transactions')->where('bank_account_id', $acc->id)->where('type', 'income')->where('payment_status', 'completed')->where('transaction_date', '<=', $to)->sum('amount') : 0;
            $outflow = Schema::hasTable('transactions') ? DB::table('transactions')->where('bank_account_id', $acc->id)->where('type', 'expense')->where('payment_status', 'completed')->where('transaction_date', '<=', $to)->sum('amount') : 0;
            $acc->current_balance = ($acc->opening_balance ?? 0) + $inflow - $outflow;

            $acc->unreconciled_tx = Schema::hasTable('transactions') ? DB::table('transactions')
                ->where('bank_account_id', $acc->id)
                ->whereNull('reconciled_at')
                ->whereBetween('transaction_date', [$from, $to])
                ->get() : collect();
            $acc->unreconciled_total = $acc->unreconciled_tx->sum('amount');
        }

        return view('reports.bank_reconciliation', compact('from', 'to', 'bankAccounts'));
    }

    public function expenseTrend(Request $request)
    {
        extract($this->getDateRange($request));

        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $dt = Carbon::parse($to)->subMonths($i);
            $months[] = [
                'key' => $dt->format('Y-m'),
                'label' => $dt->format('M Y'),
                'start' => $dt->copy()->startOfMonth()->format('Y-m-d'),
                'end' => $dt->copy()->endOfMonth()->format('Y-m-d'),
            ];
        }

        $categories = DB::table('categories')->where('type', 'expense')->get();
        $trendData = [];

        foreach ($categories as $cat) {
            $monthlyTotals = [];
            foreach ($months as $m) {
                $sum = Finance::transactions($m['start'], min($to, $m['end']))->where('type', 'expense')->where('financing', false)->where('category_id', $cat->id)->sum('base_amount');
                $monthlyTotals[$m['key']] = $sum;
            }
            $trendData[] = (object) [
                'category_name' => $cat->name,
                'totals' => $monthlyTotals,
                'grand_total' => array_sum($monthlyTotals),
            ];
        }

        return view('reports.expense_trend', compact('from', 'to', 'months', 'trendData'));
    }

    public function partyLedger(Request $request)
    {
        extract($this->getDateRange($request));
        $selectedPartyId = $request->query('party_id');
        $roleFilter = $request->query('role');

        $partiesQuery = DB::table('parties');
        if ($roleFilter && $roleFilter !== 'all') {
            $partiesQuery->where('types', 'LIKE', '%'.$roleFilter.'%');
        }
        $parties = $partiesQuery->orderBy('name')->get();

        $partySummaries = collect();
        $canViewPayroll = in_array(strtolower($request->user()->role), ['admin', 'manager', 'accountant']);
        $payrollItems = $canViewPayroll ? PayrollPayablesService::items() : collect();
        $salaryByParty = $payrollItems->groupBy('party_id');
        $salaryPaymentsByParty = $canViewPayroll ? PayrollPayablesService::payments()->groupBy('party_id') : collect();

        foreach ($parties as $p) {
            // 0. Active Client Projects Contract Value (Contracted Project Budgets + Approved CRs)
            $clientProjects = DB::table('projects')
                ->join('project_party', 'projects.id', '=', 'project_party.project_id')
                ->where('project_party.party_id', $p->id)
                ->where('project_party.role', 'client')
                ->select('projects.*')
                ->get();

            $totalContractValueLkr = 0;
            $contractOrigCurrencies = [];

            foreach ($clientProjects as $cp) {
                $approvedCR = DB::table('change_requests')
                    ->where('project_id', $cp->id)
                    ->whereIn('status', ['approved', 'invoiced'])
                    ->sum('amount');
                $projVal = (float) ($cp->budget_limit + $approvedCR);
                $curr = strtoupper(trim($cp->currency ?? 'LKR'));
                $valLkr = $this->convertToLkr($projVal, $curr);

                $totalContractValueLkr += $valLkr;
                if (! isset($contractOrigCurrencies[$curr])) {
                    $contractOrigCurrencies[$curr] = 0;
                }
                $contractOrigCurrencies[$curr] += $projVal;
            }

            // 1. AR Invoices & Payments (Client Role)
            $clientInvoices = DB::table('invoices')->where('client_id', $p->id)->get();

            $totalInvoiced = 0;
            foreach ($clientInvoices as $cinv) {
                $cCurr = strtoupper(trim($cinv->currency ?? 'LKR'));
                $cAmt = (float) ($cinv->grand_total > 0 ? $cinv->grand_total : $cinv->amount);
                $totalInvoiced += $this->convertToLkr($cAmt, $cCurr);
            }

            $totalCollected = 0;
            if ($clientInvoices->isNotEmpty()) {
                $pmtAllocations = DB::table('payment_allocations')
                    ->join('invoices', 'payment_allocations.invoice_id', '=', 'invoices.id')
                    ->whereIn('payment_allocations.invoice_id', $clientInvoices->pluck('id'))
                    ->select('payment_allocations.amount', 'invoices.currency')
                    ->get();

                foreach ($pmtAllocations as $pa) {
                    $paCurr = strtoupper(trim($pa->currency ?? 'LKR'));
                    $totalCollected += $this->convertToLkr((float) $pa->amount, $paCurr);
                }
            }

            $arBalance = max(0, $totalInvoiced - $totalCollected);
            $unbilledContractValue = max(0, $totalContractValueLkr - $totalInvoiced);

            // Format contract original currency text
            $contractOrigText = [];
            foreach ($contractOrigCurrencies as $cCode => $cVal) {
                $sym = $this->getCurrencySymbol($cCode);
                $contractOrigText[] = $sym.' '.number_format($cVal, 2).' '.$cCode;
            }
            $contractOrigStr = implode(' | ', $contractOrigText);

            // 2. AP Vendor Bills & Bill Payments (Vendor Role)
            $vendorBills = Schema::hasTable('vendor_bills') ? DB::table('vendor_bills')->where('vendor_id', $p->id)->get() : collect();
            $totalVendorBills = $vendorBills->sum('amount');
            $totalVendorPaid = $vendorBills->where('status', 'paid')->sum('amount');
            $apBillsBalance = max(0, $totalVendorBills - $totalVendorPaid);

            // 3. Loans Borrowings & Settlements (Lender / Director / Bank Role)
            $partyLoans = Schema::hasTable('loans') ? DB::table('loans')->where('party_id', $p->id)->get() : collect();
            if ($partyLoans->isEmpty()) {
                $partyLoans = DB::table('loans')->where('lender_name', 'LIKE', '%'.$p->name.'%')->get();
            }

            $totalLoanBorrowed = 0;
            $totalLoanRepaid = 0;
            $totalInterestPaid = 0;
            $totalPendingInterest = 0;

            foreach ($partyLoans as $loan) {
                $repayments = DB::table('loan_principal_records')
                    ->where('loan_id', $loan->id)
                    ->where('record_type', 'repayment')
                    ->sum('amount');
                $draws = DB::table('loan_principal_records')
                    ->where('loan_id', $loan->id)
                    ->where('record_type', 'draw')
                    ->sum('amount');
                $totalLoanBorrowed += ($loan->principal_amount + $draws);
                $totalLoanRepaid += $repayments;

                $intPaid = DB::table('loan_interest_schedule')
                    ->where('loan_id', $loan->id)
                    ->sum('paid_amount');
                $totalInterestPaid += $intPaid;

                $pendingScheds = DB::table('loan_interest_schedule')
                    ->where('loan_id', $loan->id)
                    ->whereIn('status', ['pending', 'partially_paid', 'overdue'])
                    ->get();
                foreach ($pendingScheds as $ps) {
                    $totalPendingInterest += max(0, $ps->interest_amount - ($ps->paid_amount ?? 0));
                }
            }

            $loanOutstandingPrincipal = max(0, $totalLoanBorrowed - $totalLoanRepaid);
            $loanTotalPayable = $loanOutstandingPrincipal + $totalPendingInterest;
            $loanTotalPaid = $totalLoanRepaid + $totalInterestPaid;

            // 4. Project Partner Commissions
            $partnerCommissions = Schema::hasTable('project_commissions') ? DB::table('project_commissions')->where('party_id', $p->id)->get() : collect();
            $totalCommissionOwed = 0;
            $totalCommissionPaid = 0;

            if ($partnerCommissions->isNotEmpty()) {
                foreach ($partnerCommissions as $comm) {
                    $proj = DB::table('projects')->where('id', $comm->project_id)->first();
                    $totInvoiced = DB::table('invoices')->where('project_id', $comm->project_id)->whereNotIn('status', ['draft', 'pending_approval'])->sum('grand_total');
                    $totCollected = DB::table('payments')->where('project_id', $comm->project_id)->sum('total_amount');
                    $invCount = DB::table('invoices')->where('project_id', $comm->project_id)->whereNotIn('status', ['draft', 'pending_approval'])->count();
                    $pmtCount = DB::table('payments')->where('project_id', $comm->project_id)->count();

                    $commOwed = 0;
                    if ($comm->commission_type === 'percentage') {
                        $pct = $comm->percentage_value ?? 0;
                        if ($comm->calculation_basis === 'invoiced') {
                            $commOwed = $totInvoiced * ($pct / 100);
                        } elseif ($comm->calculation_basis === 'collected') {
                            $commOwed = $totCollected * ($pct / 100);
                        } elseif ($comm->calculation_basis === 'budget') {
                            $commOwed = ($proj->budget_limit ?? 0) * ($pct / 100);
                        }
                    } else { // fixed
                        $fixed = $comm->fixed_amount ?? 0;
                        if ($comm->trigger_type === 'invoice') {
                            $commOwed = $fixed * $invCount;
                        } elseif ($comm->trigger_type === 'milestone') {
                            $commOwed = $fixed * $pmtCount;
                        } else {
                            $commOwed = $fixed;
                        }
                    }

                    $totalCommissionOwed += $commOwed;
                }

                $totalCommissionPaid = Schema::hasTable('commission_payments')
                    ? DB::table('commission_payments')->whereIn('project_commission_id', $partnerCommissions->pluck('id'))->sum('amount')
                    : 0;
            }

            $commissionBalance = max(0, $totalCommissionOwed - $totalCommissionPaid);

            // Combined Totals
            $salaries = $salaryByParty->get($p->id, collect());
            $salaryOwed = $salaries->sum('net_cents') / 100;
            $salaryPaid = $salaries->sum('paid_cents') / 100;
            $salaryBalance = $salaries->sum('balance_cents') / 100;
            $totalPayables = $apBillsBalance + $loanTotalPayable + $commissionBalance + $salaryBalance;
            $totalPaids = $totalVendorPaid + $loanTotalPaid + $totalCommissionPaid + $salaryPaid;
            $netBalance = $arBalance - $totalPayables;

            $partySummaries->push((object) [
                'id' => $p->id,
                'name' => $p->name,
                'types' => $p->types,
                'contact_person' => $p->contact_person,
                'email' => $p->email,
                'phone' => $p->phone,
                'total_contract_value' => $totalContractValueLkr,
                'unbilled_contract_value' => $unbilledContractValue,
                'contract_orig_str' => $contractOrigStr,
                'total_invoiced' => $totalInvoiced,
                'total_collected' => $totalCollected,
                'ar_balance' => $arBalance,
                'total_vendor_bills' => $totalVendorBills,
                'total_vendor_paid' => $totalVendorPaid,
                'ap_bills_balance' => $apBillsBalance,
                'total_loan_borrowed' => $totalLoanBorrowed,
                'loan_total_paid' => $loanTotalPaid,
                'loan_total_payable' => $loanTotalPayable,
                'total_commission_owed' => $totalCommissionOwed,
                'total_commission_paid' => $totalCommissionPaid,
                'commission_balance' => $commissionBalance,
                'salary_owed' => $salaryOwed,
                'salary_paid' => $salaryPaid,
                'salary_balance' => $salaryBalance,
                'total_payables' => $totalPayables,
                'total_paids' => $totalPaids,
                'net_balance' => $netBalance,
            ]);
        }

        // Pre-generate Full Detail Ledger Timelines for all parties with multi-currency tracking
        $allPartyTimelines = [];
        foreach ($parties as $p) {
            $timeline = collect();

            // 0. Active Client Projects Contracts
            $clientProjects = DB::table('projects')
                ->join('project_party', 'projects.id', '=', 'project_party.project_id')
                ->where('project_party.party_id', $p->id)
                ->where('project_party.role', 'client')
                ->select('projects.*')
                ->get();

            foreach ($clientProjects as $cp) {
                $approvedCR = DB::table('change_requests')
                    ->where('project_id', $cp->id)
                    ->whereIn('status', ['approved', 'invoiced'])
                    ->sum('amount');
                $projVal = (float) ($cp->budget_limit + $approvedCR);
                $curr = strtoupper(trim($cp->currency ?? 'LKR'));
                $valLkr = $this->convertToLkr($projVal, $curr);
                $sym = $this->getCurrencySymbol($curr);

                $timeline->push((object) [
                    'date' => date('Y-m-d', strtotime($cp->start_date ?? $cp->created_at)),
                    'type' => 'Project Contract',
                    'reference' => 'PROJ-'.$cp->id,
                    'currency' => $curr,
                    'currency_symbol' => $sym,
                    'original_debit' => $projVal,
                    'original_credit' => 0.00,
                    'debit' => $valLkr,
                    'credit' => 0.00,
                    'description' => 'Project Contract Value: '.$cp->name.' (Contracted Budget)',
                ]);
            }

            // 1. Client Invoices
            $invs = DB::table('invoices')->where('client_id', $p->id)->get();
            foreach ($invs as $inv) {
                $curr = strtoupper(trim($inv->currency ?? 'LKR'));
                $origAmt = (float) $inv->grand_total;
                $lkrAmt = $this->convertToLkr($origAmt, $curr);
                $sym = $this->getCurrencySymbol($curr);

                $timeline->push((object) [
                    'date' => date('Y-m-d', strtotime($inv->issue_date ?? $inv->created_at)),
                    'type' => 'Invoice Issued',
                    'reference' => $inv->invoice_no,
                    'currency' => $curr,
                    'currency_symbol' => $sym,
                    'original_debit' => $origAmt,
                    'original_credit' => 0.00,
                    'debit' => $lkrAmt,
                    'credit' => 0.00,
                    'description' => 'Client Invoice #'.$inv->invoice_no,
                ]);
            }

            // 2. Payments Collected
            $pmts = DB::table('payments')
                ->join('payment_allocations', 'payments.id', '=', 'payment_allocations.payment_id')
                ->join('invoices', 'payment_allocations.invoice_id', '=', 'invoices.id')
                ->where('invoices.client_id', $p->id)
                ->select('payments.*', 'payment_allocations.amount as alloc_amount', 'invoices.invoice_no', 'invoices.currency as inv_currency')
                ->get();
            foreach ($pmts as $pmt) {
                $curr = strtoupper(trim($pmt->currency ?? $pmt->inv_currency ?? 'LKR'));
                $origAmt = (float) $pmt->alloc_amount;
                $lkrAmt = $this->convertToLkr($origAmt, $curr);
                $sym = $this->getCurrencySymbol($curr);

                $timeline->push((object) [
                    'date' => date('Y-m-d', strtotime($pmt->payment_date)),
                    'type' => 'Payment Received',
                    'reference' => $pmt->receipt_no ?? ('PMT-'.$pmt->id),
                    'currency' => $curr,
                    'currency_symbol' => $sym,
                    'original_debit' => 0.00,
                    'original_credit' => $origAmt,
                    'debit' => 0.00,
                    'credit' => $lkrAmt,
                    'description' => 'Client Payment for Invoice #'.$pmt->invoice_no,
                ]);
            }

            // 3. Vendor Bills
            $bills = Schema::hasTable('vendor_bills') ? DB::table('vendor_bills')->where('vendor_id', $p->id)->get() : collect();
            foreach ($bills as $b) {
                $curr = strtoupper(trim($b->currency ?? 'LKR'));
                $origAmt = (float) $b->amount;
                $lkrAmt = $this->convertToLkr($origAmt, $curr);
                $sym = $this->getCurrencySymbol($curr);

                $timeline->push((object) [
                    'date' => date('Y-m-d', strtotime($b->issue_date)),
                    'type' => 'Vendor Bill',
                    'reference' => $b->bill_number,
                    'currency' => $curr,
                    'currency_symbol' => $sym,
                    'original_debit' => 0.00,
                    'original_credit' => $origAmt,
                    'debit' => 0.00,
                    'credit' => $lkrAmt,
                    'description' => 'Vendor Bill #'.$b->bill_number,
                ]);
            }

            // 4. Loans & Repayments
            $loans = Schema::hasTable('loans') ? DB::table('loans')->where('party_id', $p->id)->orWhere('lender_name', 'LIKE', '%'.$p->name.'%')->get() : collect();
            foreach ($loans as $l) {
                $curr = strtoupper(trim($l->currency ?? 'LKR'));
                $origAmt = (float) $l->principal_amount;
                $lkrAmt = $this->convertToLkr($origAmt, $curr);
                $sym = $this->getCurrencySymbol($curr);

                $timeline->push((object) [
                    'date' => date('Y-m-d', strtotime($l->claimed_date ?? $l->created_at)),
                    'type' => 'Loan Facility Taken',
                    'reference' => 'LOAN-'.$l->id,
                    'currency' => $curr,
                    'currency_symbol' => $sym,
                    'original_debit' => 0.00,
                    'original_credit' => $origAmt,
                    'debit' => 0.00,
                    'credit' => $lkrAmt,
                    'description' => 'Loan Borrowed: '.($l->purpose ?? $l->lender_name),
                ]);

                $repayRecords = DB::table('loan_principal_records')->where('loan_id', $l->id)->get();
                foreach ($repayRecords as $pr) {
                    $origPrAmt = (float) $pr->amount;
                    $lkrPrAmt = $this->convertToLkr($origPrAmt, $curr);

                    $timeline->push((object) [
                        'date' => date('Y-m-d', strtotime($pr->record_date)),
                        'type' => 'Loan '.ucfirst($pr->record_type),
                        'reference' => $pr->reference_no ?? ('LOAN-PRIN-'.$pr->id),
                        'currency' => $curr,
                        'currency_symbol' => $sym,
                        'original_debit' => $pr->record_type === 'repayment' ? $origPrAmt : 0.00,
                        'original_credit' => $pr->record_type === 'draw' ? $origPrAmt : 0.00,
                        'debit' => $pr->record_type === 'repayment' ? $lkrPrAmt : 0.00,
                        'credit' => $pr->record_type === 'draw' ? $lkrPrAmt : 0.00,
                        'description' => 'Loan Principal '.ucfirst($pr->record_type),
                    ]);
                }

                $intScheds = DB::table('loan_interest_schedule')->where('loan_id', $l->id)->where('paid_amount', '>', 0)->get();
                foreach ($intScheds as $is) {
                    $origIntAmt = (float) $is->paid_amount;
                    $lkrIntAmt = $this->convertToLkr($origIntAmt, $curr);

                    $timeline->push((object) [
                        'date' => date('Y-m-d', strtotime($is->paid_date ?? $is->due_date)),
                        'type' => 'Loan Interest Paid',
                        'reference' => 'LOAN-INT-'.$is->id,
                        'currency' => $curr,
                        'currency_symbol' => $sym,
                        'original_debit' => $origIntAmt,
                        'original_credit' => 0.00,
                        'debit' => $lkrIntAmt,
                        'credit' => 0.00,
                        'description' => 'Loan Interest Settlement',
                    ]);
                }
            }

            // 5. Commission Entitlements & Payments
            $comms = Schema::hasTable('project_commissions') ? DB::table('project_commissions')->where('party_id', $p->id)->get() : collect();
            foreach ($comms as $c) {
                $proj = DB::table('projects')->where('id', $c->project_id)->first();
                $curr = strtoupper(trim($proj->currency ?? 'LKR'));
                $sym = $this->getCurrencySymbol($curr);

                $totInvoiced = DB::table('invoices')->where('project_id', $c->project_id)->whereNotIn('status', ['draft', 'pending_approval'])->sum('grand_total');
                $totCollected = DB::table('payments')->where('project_id', $c->project_id)->sum('total_amount');
                $invCount = DB::table('invoices')->where('project_id', $c->project_id)->whereNotIn('status', ['draft', 'pending_approval'])->count();
                $pmtCount = DB::table('payments')->where('project_id', $c->project_id)->count();

                $commOwed = 0;
                if ($c->commission_type === 'percentage') {
                    $pct = $c->percentage_value ?? 0;
                    if ($c->calculation_basis === 'invoiced') {
                        $commOwed = $totInvoiced * ($pct / 100);
                    } elseif ($c->calculation_basis === 'collected') {
                        $commOwed = $totCollected * ($pct / 100);
                    } elseif ($c->calculation_basis === 'budget') {
                        $commOwed = ($proj->budget_limit ?? 0) * ($pct / 100);
                    }
                } else { // fixed
                    $fixed = $c->fixed_amount ?? 0;
                    if ($c->trigger_type === 'invoice') {
                        $commOwed = $fixed * $invCount;
                    } elseif ($c->trigger_type === 'milestone') {
                        $commOwed = $fixed * $pmtCount;
                    } else {
                        $commOwed = $fixed;
                    }
                }

                if ($commOwed > 0) {
                    $lkrCommOwed = $this->convertToLkr($commOwed, $curr);
                    $timeline->push((object) [
                        'date' => date('Y-m-d', strtotime($c->created_at)),
                        'type' => 'Commission Entitlement',
                        'reference' => 'COMM-'.$c->id,
                        'currency' => $curr,
                        'currency_symbol' => $sym,
                        'original_debit' => 0.00,
                        'original_credit' => (float) $commOwed,
                        'debit' => 0.00,
                        'credit' => $lkrCommOwed,
                        'description' => 'Project Commission ('.($proj->name ?? 'Project #'.$c->project_id).')',
                    ]);
                }

                $commPmts = DB::table('commission_payments')->where('project_commission_id', $c->id)->get();
                foreach ($commPmts as $cp) {
                    $origCpAmt = (float) $cp->amount;
                    $lkrCpAmt = $this->convertToLkr($origCpAmt, $curr);

                    $timeline->push((object) [
                        'date' => date('Y-m-d', strtotime($cp->payment_date)),
                        'type' => 'Commission Payout',
                        'reference' => $cp->reference_no ?? ('COMM-PAY-'.$cp->id),
                        'currency' => $curr,
                        'currency_symbol' => $sym,
                        'original_debit' => $origCpAmt,
                        'original_credit' => 0.00,
                        'debit' => $lkrCpAmt,
                        'credit' => 0.00,
                        'description' => 'Commission Payment Payout',
                    ]);
                }
            }

            foreach ($salaryByParty->get($p->id, collect()) as $salary) {
                $timeline->push((object) ['date' => Carbon::parse($salary->month)->endOfMonth()->toDateString(), 'type' => 'Salary Entitlement',
                    'reference' => 'PR-'.$salary->run_id, 'currency' => 'LKR', 'currency_symbol' => 'Rs.',
                    'original_debit' => 0, 'original_credit' => $salary->net_cents / 100, 'debit' => 0, 'credit' => $salary->net_cents / 100,
                    'description' => Carbon::parse($salary->month)->format('F Y').' net salary']);
            }
            foreach ($salaryPaymentsByParty->get($p->id, collect()) as $payment) {
                $timeline->push((object) ['date' => $payment->paid_on, 'type' => 'Salary Payment', 'reference' => 'PB-'.$payment->id,
                    'currency' => 'LKR', 'currency_symbol' => 'Rs.', 'original_debit' => $payment->amount_cents / 100, 'original_credit' => 0,
                    'debit' => $payment->amount_cents / 100, 'credit' => 0,
                    'description' => Carbon::parse($payment->month)->format('F Y').' salary · '.$payment->reference]);
            }
            $allPartyTimelines[$p->id] = $timeline->sortByDesc('date')->values();
        }

        $partyDetail = $selectedPartyId ? $partySummaries->firstWhere('id', (int) $selectedPartyId) : null;
        $partyTimeline = $selectedPartyId ? ($allPartyTimelines[$selectedPartyId] ?? collect()) : collect();

        return view('reports.party_ledger', compact(
            'canViewPayroll',
            'payrollItems',
            'from',
            'to',
            'partySummaries',
            'allPartyTimelines',
            'partyDetail',
            'partyTimeline',
            'selectedPartyId',
            'roleFilter'
        ));
    }

    private function unformatAmount($val)
    {
        if (is_numeric($val)) {
            return (float) $val;
        }
        if (empty($val)) {
            return 0.0;
        }

        return (float) preg_replace('/[^\d.]/', '', str_replace(',', '', (string) $val));
    }

    public function recordPartySettlement(Request $request)
    {
        throw ValidationException::withMessages(['settlement' => 'Select the source invoice, vendor bill, loan or project commission and record its payment there. This keeps currency, allocations and ledger entries together.']);
    }

    /**
     * Convert foreign currency amount to LKR base currency
     */
    private function convertToLkr(float $amount, ?string $currency): float
    {
        return Finance::convert($amount, $currency, request('to', date('Y-m-d')), 'LKR');
    }

    /**
     * Get currency symbol
     */
    private function getCurrencySymbol(?string $currency): string
    {
        $curr = strtoupper(trim($currency ?: 'LKR'));
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'AED' => 'AED',
            'LKR' => 'Rs.',
            'INR' => '₹',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'SGD' => 'S$',
            'JPY' => '¥',
        ];

        return $symbols[$curr] ?? $curr;
    }
}
