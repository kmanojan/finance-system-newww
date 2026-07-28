<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class ReportController extends Controller
{
    private function getDateRange(Request $request)
    {
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

        $income = DB::table('transactions')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.type', 'income')
            ->whereBetween('transaction_date', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->select('categories.name as category', DB::raw('SUM(amount) as total'))
            ->groupBy('categories.id', 'categories.name')
            ->get();

        $expense = DB::table('transactions')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.type', 'expense')
            ->whereBetween('transaction_date', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->select('categories.name as category', DB::raw('SUM(amount) as total'))
            ->groupBy('categories.id', 'categories.name')
            ->get();

        $totalIncome = $income->sum('total');
        $totalExpense = $expense->sum('total');
        $netProfit = $totalIncome - $totalExpense;

        $data = compact('income', 'expense', 'totalIncome', 'totalExpense', 'netProfit');

        return view('reports.pnl', compact('from', 'to', 'data'));
    }

    public function expenses(Request $request)
    {
        extract($this->getDateRange($request));

        $expenses = DB::table('transactions')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.type', 'expense')
            ->whereBetween('transaction_date', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->select('categories.name as category', DB::raw('SUM(amount) as total'))
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('total', 'desc')
            ->get();

        $totalExpense = $expenses->sum('total');
        $data = compact('expenses', 'totalExpense');

        return view('reports.expenses', compact('from', 'to', 'data'));
    }

    public function commissions(Request $request)
    {
        extract($this->getDateRange($request));

        $parties = DB::table('parties')
            ->where(function($q) {
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
                $invSum = DB::table('invoices')
                    ->where('project_id', $c->project_id)
                    ->where('status', 'paid')
                    ->whereBetween('invoice_date', [$from, $to])
                    ->sum('amount');

                $commAmount = ($invSum * $c->percentage) / 100;

                $paidSum = DB::table('transactions')
                    ->where('type', 'expense')
                    ->where('party_id', $party->id)
                    ->where('project_id', $c->project_id)
                    ->whereBetween('transaction_date', [$from, $to])
                    ->sum('amount');

                $netPayable = max(0, $commAmount - $paidSum);

                $partyTotalComm += $commAmount;
                $partyTotalPaid += $paidSum;
                $partyTotalPayable += $netPayable;

                $partyComms[] = (object)[
                    'project_name' => $c->project_name,
                    'percentage' => $c->percentage,
                    'invoiced_paid' => $invSum,
                    'commission_earned' => $commAmount,
                    'paid_amount' => $paidSum,
                    'net_payable' => $netPayable,
                ];
            }

            if (count($partyComms) > 0) {
                $reportData[] = (object)[
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

        $query = \App\Models\CostAllocation::with(['project', 'employee', 'server'])
            ->whereBetween('period_start', [$from . ' 00:00:00', $to . ' 23:59:59']);

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

        $totalCost = $allocations->sum('amount');
        $employeeCost = $allocations->where('type', 'employee')->sum('amount');
        $serverCost = $allocations->where('type', 'server')->sum('amount');
        $otherCost = $allocations->where('type', 'other')->sum('amount');

        $projectBreakdown = $allocations->groupBy('project_id')->map(function ($items) {
            return [
                'project_name' => $items->first()->project->name ?? 'Unassigned Project',
                'total' => $items->sum('amount'),
                'count' => $items->count(),
            ];
        })->sortByDesc('total');

        $projects = \App\Models\Project::orderBy('name')->get();
        $employees = \App\Models\Employee::where('status', 'active')->orderBy('full_name')->get();
        $servers = \App\Models\Server::where('is_active', true)->orderBy('name')->get();

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

        // Assets
        $bankAccounts = Schema::hasTable('bank_accounts') ? DB::table('bank_accounts')->get() : collect();
        foreach ($bankAccounts as $acc) {
            $inflow = Schema::hasTable('transactions') ? DB::table('transactions')->where('bank_account_id', $acc->id)->where('type', 'income')->sum('amount') : 0;
            $outflow = Schema::hasTable('transactions') ? DB::table('transactions')->where('bank_account_id', $acc->id)->where('type', 'expense')->sum('amount') : 0;
            $acc->current_balance = ($acc->opening_balance ?? 0) + $inflow - $outflow;
        }
        $totalBankAssets = $bankAccounts->sum('current_balance');
        $accountsReceivable = Schema::hasTable('invoices') ? DB::table('invoices')->whereNotIn('status', ['draft', 'paid', 'cancelled'])->sum('amount') : 0;
        $totalAssets = $totalBankAssets + $accountsReceivable;

        // Liabilities
        $accountsPayable = Schema::hasTable('transactions') ? DB::table('transactions')->where('type', 'expense')->where('payment_status', 'pending')->sum('amount') : 0;
        $outstandingLoans = Schema::hasTable('loans') ? DB::table('loans')->where('status', 'active')->sum('outstanding_principal') : 0;
        $totalLiabilities = $accountsPayable + $outstandingLoans;

        // Equity
        $totalIncome = Schema::hasTable('transactions') ? DB::table('transactions')->where('type', 'income')->sum('amount') : 0;
        $totalExpense = Schema::hasTable('transactions') ? DB::table('transactions')->where('type', 'expense')->sum('amount') : 0;
        $retainedEarnings = $totalIncome - $totalExpense;
        $totalEquity = $totalAssets - $totalLiabilities;

        return view('reports.balance_sheet', compact('from', 'to', 'asOfDate', 'bankAccounts', 'totalBankAssets', 'accountsReceivable', 'totalAssets', 'accountsPayable', 'outstandingLoans', 'totalLiabilities', 'retainedEarnings', 'totalEquity'));
    }

    public function cashFlow(Request $request)
    {
        extract($this->getDateRange($request));

        $operatingInflows = Schema::hasTable('transactions') ? DB::table('transactions')->where('type', 'income')->whereBetween('transaction_date', [$from, $to])->sum('amount') : 0;
        $operatingOutflows = Schema::hasTable('transactions') ? DB::table('transactions')->where('type', 'expense')->whereBetween('transaction_date', [$from, $to])->sum('amount') : 0;
        $netOperatingCash = $operatingInflows - $operatingOutflows;

        $financingInflows = Schema::hasTable('loans') ? DB::table('loans')->whereBetween('claimed_date', [$from, $to])->sum('principal_amount') : 0;
        $financingOutflows = Schema::hasTable('loan_principal_records') ? DB::table('loan_principal_records')->where('record_type', 'repayment')->whereBetween('record_date', [$from, $to])->sum('amount') : 0;
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
            $invoices = DB::table('invoices')
                ->where('client_id', $client->id)
                ->whereNotIn('status', ['paid', 'draft', 'cancelled'])
                ->get();

            $current = 0; // 0-30 days
            $b30_60 = 0; // 31-60 days
            $b60_90 = 0; // 61-90 days
            $b90_plus = 0; // 90+ days

            foreach ($invoices as $inv) {
                $dueDate = Carbon::parse($inv->due_date ?? $inv->invoice_date);
                $days = $dueDate->diffInDays($now, false);
                $amt = $inv->amount;

                if ($days <= 30) $current += $amt;
                elseif ($days <= 60) $b30_60 += $amt;
                elseif ($days <= 90) $b60_90 += $amt;
                else $b90_plus += $amt;
            }

            $total = $current + $b30_60 + $b60_90 + $b90_plus;

            if ($total > 0) {
                $agingData[] = (object)[
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
            $invoiced = DB::table('invoices')->where('project_id', $p->id)->whereBetween('invoice_date', [$from, $to])->whereNotIn('status', ['draft', 'cancelled'])->sum('amount');
            $collected = DB::table('payments')->where('project_id', $p->id)->whereBetween('payment_date', [$from, $to])->sum('total_amount');
            $costAllocations = DB::table('cost_allocations')->where('project_id', $p->id)->whereBetween('period_start', [$from, $to])->sum('amount');
            $directExpenses = DB::table('transactions')->where('project_id', $p->id)->where('type', 'expense')->whereBetween('transaction_date', [$from, $to])->sum('amount');
            $totalCost = $costAllocations + $directExpenses;
            $netProfit = $invoiced - $totalCost;
            $margin = $invoiced > 0 ? ($netProfit / $invoiced) * 100 : 0;

            $report[] = (object)[
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

        usort($report, fn($a, $b) => $b->net_profit <=> $a->net_profit);

        return view('reports.project_profitability', compact('from', 'to', 'report'));
    }

    public function clientStatement(Request $request)
    {
        extract($this->getDateRange($request));
        $clientId = $request->query('client_id');
        $clients = DB::table('parties')->where('types', 'LIKE', '%client%')->get();
        $statement = [];
        $selectedClient = null;

        if ($clientId) {
            $selectedClient = DB::table('parties')->where('id', $clientId)->first();

            $invoices = DB::table('invoices')->where('client_id', $clientId)->whereBetween('invoice_date', [$from, $to])->get()->map(function($i) {
                $i->entry_type = 'Invoice';
                $i->date = $i->invoice_date;
                return $i;
            });

            $payments = DB::table('payments')->where('client_id', $clientId)->whereBetween('payment_date', [$from, $to])->get()->map(function($p) {
                $p->entry_type = 'Payment';
                $p->date = $p->payment_date;
                return $p;
            });

            $statement = $invoices->concat($payments)->sortBy('date')->values();
        }

        return view('reports.client_statement', compact('from', 'to', 'clients', 'selectedClient', 'clientId', 'statement'));
    }

    public function bankReconciliation(Request $request)
    {
        extract($this->getDateRange($request));
        $bankAccounts = Schema::hasTable('bank_accounts') ? DB::table('bank_accounts')->get() : collect();

        foreach ($bankAccounts as $acc) {
            $inflow = Schema::hasTable('transactions') ? DB::table('transactions')->where('bank_account_id', $acc->id)->where('type', 'income')->sum('amount') : 0;
            $outflow = Schema::hasTable('transactions') ? DB::table('transactions')->where('bank_account_id', $acc->id)->where('type', 'expense')->sum('amount') : 0;
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
                $sum = DB::table('transactions')
                    ->where('category_id', $cat->id)
                    ->whereBetween('transaction_date', [$m['start'], $m['end']])
                    ->sum('amount');
                $monthlyTotals[$m['key']] = $sum;
            }
            $trendData[] = (object)[
                'category_name' => $cat->name,
                'totals' => $monthlyTotals,
                'grand_total' => array_sum($monthlyTotals),
            ];
        }

        return view('reports.expense_trend', compact('from', 'to', 'months', 'trendData'));
    }
}
