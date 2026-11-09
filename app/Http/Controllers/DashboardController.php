<?php

namespace App\Http\Controllers;

use App\Services\FinancialReportingService as Finance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['start_date' => 'nullable|date_format:Y-m-d', 'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date', 'department_id' => 'nullable|exists:departments,id', 'company_id' => 'nullable|exists:companies,id']);
        $range = $request->query('date_range', 'this_month');
        $companyId = $request->query('company_id');
        $deptId = $request->query('department_id');
        $selectedTags = $request->query('tags', []);

        // Resolve dates
        $startDate = null;
        $endDate = null;
        if ($range === 'today') {
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d');
        } elseif ($range === 'this_week') {
            $startDate = date('Y-m-d', strtotime('monday this week'));
            $endDate = date('Y-m-d', strtotime('sunday this week'));
        } elseif ($range === 'this_month') {
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
        } elseif ($range === 'this_quarter') {
            $quarter = ceil(date('m') / 3);
            $startDate = date('Y-m-d', mktime(0, 0, 0, ($quarter - 1) * 3 + 1, 1, date('Y')));
            $endDate = date('Y-m-d', mktime(0, 0, 0, $quarter * 3, date('t', mktime(0, 0, 0, $quarter * 3, 1, date('Y'))), date('Y')));
        } elseif ($range === 'this_year') {
            $startDate = date('Y-01-01');
            $endDate = date('Y-12-31');
        } elseif ($range === 'custom') {
            $startDate = $request->query('start_date', date('Y-m-01'));
            $endDate = $request->query('end_date', date('Y-m-t'));
        } else {
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
        }

        // Apply filters callback
        $applyFilters = function ($query, $tablePrefix = '') use ($companyId, $deptId) {
            if ($companyId) {
                $query->whereIn($tablePrefix.'department_id', function ($q) use ($companyId) {
                    $q->select('id')->from('departments')->where('company_id', $companyId);
                });
            }
            if ($deptId) {
                $query->where($tablePrefix.'department_id', $deptId);
            }

            return $query;
        };

        // 1. KPI Calculation
        // Total Cash Position
        $totalCash = 0;
        $bankAccounts = DB::table('bank_accounts')->whereNull('deleted_at')->when($companyId, fn ($q) => $q->where('company_id', $companyId))->when($deptId, fn ($q) => $q->where('department_id', $deptId))->get();
        foreach ($bankAccounts as $ba) {
            $inflow = DB::table('transactions')->where('bank_account_id', $ba->id)->where('transaction_date', '<=', $endDate)->where('payment_status', 'completed')->where('type', 'income')->sum('amount');
            $outflow = DB::table('transactions')->where('bank_account_id', $ba->id)->where('transaction_date', '<=', $endDate)->where('payment_status', 'completed')->where('type', 'expense')->sum('amount');
            $totalCash += Finance::convert(($ba->opening_balance ?? 0) + $inflow - $outflow, $ba->currency, $endDate);
        }

        $transactions = Finance::transactions($startDate, $endDate);
        $totalIncome = $transactions->where('type', 'income')->sum('base_amount');
        $totalExpense = $transactions->where('type', 'expense')->sum('base_amount');
        $netProfit = $totalIncome - $totalExpense;

        // Outstanding Receivables
        $invoices = Finance::scope(Finance::issuedInvoices($endDate))->get();
        $outstandingReceivables = 0;
        foreach ($invoices as $inv) {
            $collected = $inv->amount - Finance::outstanding($inv, $endDate);
            $outstandingReceivables += Finance::convert(max(0, $inv->amount - $collected), $inv->currency, $endDate);
        }

        // Outstanding Payables (Commissions + Loan Interest)
        $projectCommissions = DB::table('project_commissions')->where('status', 'active')->get();
        $outstandingCommissions = 0;
        foreach ($projectCommissions as $comm) {
            $totalInvoiced = DB::table('invoices')->where('project_id', $comm->project_id)->whereNotIn('status', ['draft', 'pending_approval'])->sum('amount');
            $totalCollected = DB::table('payments')->where('project_id', $comm->project_id)->sum('total_amount');
            $invoiceCount = DB::table('invoices')->where('project_id', $comm->project_id)->whereNotIn('status', ['draft', 'pending_approval'])->count();
            $paymentCount = DB::table('payments')->where('project_id', $comm->project_id)->count();

            $totalComm = 0;
            if ($comm->commission_type === 'percentage') {
                $percentage = $comm->percentage_value ?? 0;
                if ($comm->calculation_basis === 'invoiced') {
                    $totalComm = $totalInvoiced * ($percentage / 100);
                } elseif ($comm->calculation_basis === 'collected') {
                    $totalComm = $totalCollected * ($percentage / 100);
                } elseif ($comm->calculation_basis === 'budget') {
                    $project = DB::table('projects')->where('id', $comm->project_id)->first();
                    $totalComm = ($project->budget_limit ?? 0) * ($percentage / 100);
                }
            } else {
                $fixed = $comm->fixed_amount ?? 0;
                if ($comm->trigger_type === 'invoice') {
                    $totalComm = $fixed * $invoiceCount;
                } elseif ($comm->trigger_type === 'milestone') {
                    $totalComm = $fixed * $paymentCount;
                } else {
                    $totalComm = $fixed;
                }
            }
            $paid = DB::table('commission_payments')->where('project_commission_id', $comm->id)->sum('amount');
            $outstandingCommissions += max(0, $totalComm - $paid);
        }
        $interestDue = DB::table('loan_interest_schedule')->where('status', 'pending')->sum('interest_amount');
        $outstandingPayables = $outstandingCommissions + $interestDue;

        // Budget Utilization
        $activeBudgets = DB::table('budgets')
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', date('Y-m-d'));
            })
            ->get();
        $totalBudgetLimit = 0;
        $totalBudgetSpent = 0;
        $overBudgetCount = 0;
        foreach ($activeBudgets as $b) {
            $totalBudgetLimit += $b->allocated_amount;
            $spent = DB::table('budget_transactions')->where('budget_id', $b->id)->sum('amount');
            $totalBudgetSpent += $spent;
            if ($spent > $b->allocated_amount) {
                $overBudgetCount++;
            }
        }
        $budgetUtilization = $totalBudgetLimit > 0 ? ($totalBudgetSpent / $totalBudgetLimit) * 100 : 0;

        // Active Loans Principal Outstanding
        $activeLoans = DB::table('loans')->where('status', 'active')->get();
        $loansOutstanding = 0;
        foreach ($activeLoans as $loan) {
            $draws = DB::table('loan_principal_records')->where('loan_id', $loan->id)->where('record_type', 'draw')->sum('amount');
            $repayments = DB::table('loan_principal_records')->where('loan_id', $loan->id)->where('record_type', 'repayment')->sum('amount');
            $loansOutstanding += max(0, $loan->principal_amount + $draws - $repayments);
        }

        // Pending Approvals
        $pendingInvoicesCount = Finance::scope(DB::table('invoices'))->where('status', 'pending_approval')->count();
        $pendingCRsCount = DB::table('change_requests')->where('status', 'pending')->count();
        $pendingApprovals = $pendingInvoicesCount + DB::table('loans')->where('status', 'pending_settlement')->count();

        // Reminders Count
        $remindersCount = DB::table('reminders')->where('status', 'pending')->where('due_date', '<=', date('Y-m-d'))->count();

        // 2. Charts Data
        // Same dates and scope as the headline figures. Short windows use days, long windows use months.
        $trendData = ['labels' => [], 'income' => [], 'expense' => [], 'profit' => []];
        $daily = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) <= 31;
        $cursor = Carbon::parse($startDate);
        while ($cursor->lte(Carbon::parse($endDate))) {
            $bucketStart = $cursor->toDateString();
            $bucketEnd = $daily ? $bucketStart : min($cursor->copy()->endOfMonth()->toDateString(), $endDate);
            $bucket = $transactions->filter(fn ($row) => $row->transaction_date >= $bucketStart && $row->transaction_date <= $bucketEnd);
            $inc = $bucket->where('type', 'income')->sum('base_amount');
            $exp = $bucket->where('type', 'expense')->sum('base_amount');
            $trendData['labels'][] = $cursor->format($daily ? 'd M' : 'M Y');
            $trendData['income'][] = $inc;
            $trendData['expense'][] = $exp;
            $trendData['profit'][] = $inc - $exp;
            $daily ? $cursor->addDay() : $cursor->startOfMonth()->addMonth();
        }
        $expenseCategories = $transactions->where('type', 'expense')->groupBy('category_name')->map(fn ($rows, $name) => (object) ['name' => $name, 'value' => $rows->sum('base_amount')])->sortByDesc('value')->take(5)->values();

        // Receivables Aging
        $aging = ['30' => 0, '60' => 0, '90' => 0, '90_plus' => 0];
        foreach ($invoices as $inv) {
            $collected = $inv->amount - Finance::outstanding($inv, $endDate);
            $bal = Finance::convert(max(0, $inv->amount - $collected), $inv->currency, $endDate);
            if ($bal > 0 && $inv->due_date) {
                $days = Carbon::parse($inv->due_date)->diffInDays(Carbon::now(), false);
                if ($days <= 0) {
                    $aging['30'] += $bal;
                } elseif ($days <= 30) {
                    $aging['30'] += $bal;
                } elseif ($days <= 60) {
                    $aging['60'] += $bal;
                } elseif ($days <= 90) {
                    $aging['90'] += $bal;
                } else {
                    $aging['90_plus'] += $bal;
                }
            }
        }

        // 3. Tables Data
        // Upcoming & Overdue Payments
        $overduePayments = [];
        foreach ($invoices as $inv) {
            $collected = $inv->amount - Finance::outstanding($inv, $endDate);
            $bal = Finance::convert(max(0, $inv->amount - $collected), $inv->currency, $endDate);
            if ($bal > 0) {
                $days = Carbon::parse($inv->due_date)->diffInDays(Carbon::now(), false);
                $proj = DB::table('projects')->where('id', $inv->project_id)->value('name') ?? 'N/A';
                $client = DB::table('parties')->where('id', $inv->client_id)->value('name') ?? 'N/A';
                $overduePayments[] = (object) [
                    'ref' => $inv->invoice_no,
                    'client' => $client,
                    'project' => $proj,
                    'due_date' => $inv->due_date,
                    'amount' => $bal,
                    'days_overdue' => $days,
                    'status' => $days > 0 ? 'Overdue' : 'Due Soon',
                ];
            }
        }
        usort($overduePayments, function ($a, $b) {
            return $b->days_overdue <=> $a->days_overdue;
        });
        $overduePayments = array_slice($overduePayments, 0, 5);

        // Recent Reminders
        $remindersList = DB::table('reminders')
            ->where('status', 'pending')
            ->orderBy('due_date', 'asc')
            ->take(5)
            ->get();

        // Recent Transactions
        $recentTransactions = DB::table('transactions')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->leftJoin('bank_accounts', 'transactions.bank_account_id', '=', 'bank_accounts.id')
            ->select('transactions.*', 'categories.name as category_name', 'bank_accounts.bank_name as bank_name')
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // Rollups
        $companies = DB::table('companies')->get();
        $departments = DB::table('departments')->get();
        // Keep every department available so switching company can update options without a reload.

        // Today's Payment Milestones
        $todayMilestones = DB::table('payment_milestones')
            ->join('projects', 'payment_milestones.project_id', '=', 'projects.id')
            ->where('payment_milestones.status', 'pending')
            ->where('payment_milestones.due_date', date('Y-m-d'))
            ->select('payment_milestones.*', 'projects.name as project_name')
            ->get();

        return view('dashboard', compact(
            'range', 'startDate', 'endDate', 'companyId', 'deptId',
            'companies', 'departments',
            'totalCash', 'totalIncome', 'totalExpense', 'netProfit',
            'outstandingReceivables', 'outstandingPayables', 'budgetUtilization',
            'loansOutstanding', 'pendingApprovals', 'remindersCount',
            'trendData', 'expenseCategories', 'aging',
            'overduePayments', 'remindersList', 'recentTransactions', 'overBudgetCount',
            'todayMilestones'
        ));
    }
}
