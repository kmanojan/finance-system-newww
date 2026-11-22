<?php

namespace App\Http\Controllers;

use App\Models\CostAllocation;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Server;
use App\Services\AuditPayablesService;
use App\Services\FinancialReportingService;
use App\Services\FinancialReportingService as Finance;
use App\Services\PartyLedgerReport;
use App\Services\PayrollStatutoryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
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
        $canViewPayroll = $request->user()->isAdmin() || in_array(strtolower($request->user()->role ?? ''), ['manager', 'accountant']);
        $balanceFilters = ['all', 'open', 'receivable', 'loan', 'other', 'unapplied'];
        if ($canViewPayroll) {
            $balanceFilters[] = 'salary';
        }
        $data = $request->validate([
            'q' => 'nullable|string|max:120',
            'role' => 'nullable|string|max:50',
            'balance' => ['nullable', Rule::in($balanceFilters)],
            'party_id' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
        ]);
        $asOf = PayrollStatutoryService::today();
        $report = app(PartyLedgerReport::class)->build($asOf, $canViewPayroll);
        $parties = $report['parties'];
        $roleOptions = $parties->flatMap(fn ($party) => $party->roles)->unique()->sort()->values();
        $query = trim($data['q'] ?? '');
        $role = $data['role'] ?? 'all';
        $balance = $data['balance'] ?? 'all';
        $party = $parties->get($data['party_id'] ?? null);

        $matching = $parties->filter(function ($row) use ($query, $role, $balance) {
            $search = implode(' ', [$row->name, $row->contact_person, $row->employeeCodes, implode(' ', $row->roles)]);
            if (($query !== '' && ! str_contains(mb_strtolower($search), mb_strtolower($query))) || ($role !== 'all' && ! in_array($role, $row->roles))) {
                return false;
            }

            return match ($balance) {
                'all' => true,
                'open' => $row->balances->contains(fn ($currency) => $currency['receivable'] + $currency['loan'] + $currency['salary'] + $currency['other'] > 0),
                default => $row->balances->contains(fn ($currency) => $currency[$balance] > 0),
            };
        })->values();
        $totals = PartyLedgerReport::balances($matching->flatMap(fn ($row) => $row->entries->flatten(1)));
        $statutoryTotals = $report['statutory']->groupBy('currency')->sortKeys()->map->sum('balance_cents');
        $page = min($data['page'] ?? 1, max(1, (int) ceil($matching->count() / 25)));
        $export = $request->is('reports/export/*');
        $registry = new LengthAwarePaginator($export ? $matching : $matching->forPage($page, 25), $matching->count(), 25, $page, [
            'path' => route('reports.party_ledger'),
            'query' => $request->except(['page', 'party_id']),
        ]);

        return view('reports.party_ledger', $report + compact('asOf', 'canViewPayroll', 'roleOptions', 'query', 'role', 'balance', 'party', 'totals', 'statutoryTotals', 'registry'));
    }

    public function recordPartySettlement(Request $request)
    {
        throw ValidationException::withMessages(['settlement' => 'Select the source invoice, vendor bill, loan or project commission and record its payment there. This keeps currency, allocations and ledger entries together.']);
    }
}
