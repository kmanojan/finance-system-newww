@extends('layouts.app')
@section('title', 'Financial overview')
@section('content')
<link rel="stylesheet" href="{{ asset('dashboard.css') }}?v={{ substr(hash_file('sha256', public_path('dashboard.css')), 0, 12) }}">
@php
    $periodLabel = \Carbon\Carbon::parse($startDate)->format('d M Y').' – '.\Carbon\Carbon::parse($endDate)->format('d M Y');
    $scopeName = $companies->firstWhere('id', $companyId)->name ?? 'All companies';
    $departmentName = $departments->firstWhere('id', $deptId)->name ?? 'All departments';
    $followUpDate = min($endDate, now()->toDateString());
    $hasCashMovement = collect($trendData['income'])->contains(fn($value) => $value != 0) || collect($trendData['expense'])->contains(fn($value) => $value != 0);
    $reportQuery = array_filter(['from'=>$startDate,'to'=>$endDate,'company_id'=>$companyId,'department_id'=>$deptId]);
@endphp
<div class="dash-workspace">
    <header class="dash-header">
        <div><h1>Financial overview</h1><p>{{ $scopeName }} <span aria-hidden="true">/</span> {{ $departmentName }}</p></div>
        <a class="dash-button dash-primary" href="/transactions"><ion-icon name="add-outline" aria-hidden="true"></ion-icon> Record transaction</a>
    </header>

    <details class="dash-filters" @if(request()->hasAny(['company_id','department_id','date_range','start_date','end_date'])) open @endif>
        <summary><span><ion-icon name="options-outline" aria-hidden="true"></ion-icon> Period & scope</span><span>{{ $periodLabel }} <ion-icon name="chevron-down-outline" aria-hidden="true"></ion-icon></span></summary>
        <form method="GET" action="/dashboard" id="dashboard-filters" aria-label="Filter financial overview">
            <div class="dash-field"><label for="date_range">Period</label><select name="date_range" id="date_range"><option value="this_month" @selected($range==='this_month')>This month</option><option value="today" @selected($range==='today')>Today</option><option value="this_week" @selected($range==='this_week')>This week</option><option value="this_quarter" @selected($range==='this_quarter')>This quarter</option><option value="this_year" @selected($range==='this_year')>This year</option><option value="custom" @selected($range==='custom')>Custom dates</option></select></div>
            <div class="dash-field"><label for="company_id">Company</label><select name="company_id" id="company_id"><option value="">All companies</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected($companyId==$company->id)>{{ $company->name }}</option>@endforeach</select></div>
            <div class="dash-field"><label for="department_id">Department</label><select name="department_id" id="department_id"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" data-company="{{ $department->company_id }}" @selected($deptId==$department->id)>{{ $department->name }}</option>@endforeach</select></div>
            <div class="dash-field dash-custom-date" @if($range !== 'custom') hidden @endif><label for="start_date">From</label><input type="date" id="start_date" name="start_date" value="{{ $startDate }}" @disabled($range !== 'custom')></div>
            <div class="dash-field dash-custom-date" @if($range !== 'custom') hidden @endif><label for="end_date">To</label><input type="date" id="end_date" name="end_date" value="{{ $endDate }}" @disabled($range !== 'custom')></div>
            <div class="dash-filter-actions"><button class="dash-button" type="submit">Apply filters</button>@if(request()->query())<a class="dash-text-link" href="/dashboard">Reset</a>@endif</div>
        </form>
    </details>

    <section class="dash-position" aria-label="Financial position">
        <a class="dash-cash" href="/reports/bank-reconciliation?{{ http_build_query($reportQuery) }}"><span>Cash at bank <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon></span><strong><small>{{ $baseCurrency }}</small> {{ number_format($totalCash,2) }}</strong><span>Balance as of {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</span></a>
        <a class="dash-position-item" href="/invoices"><span>Outstanding receivables <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon></span><strong><small>{{ $baseCurrency }}</small> {{ number_format($outstandingReceivables,2) }}</strong><p>Unpaid issued invoices at period end</p></a>
        <div class="dash-position-item"><span>Net cash movement</span><strong @class(['dash-negative'=>$netProfit<0])><small>{{ $baseCurrency }}</small> {{ number_format($netProfit,2) }}</strong><p>{{ $netProfit < 0 ? 'Cash spent exceeds cash received' : ($netProfit > 0 ? 'Cash received exceeds cash spent' : 'Cash received and spent are balanced') }} · selected period</p></div>
    </section>

    <div class="dash-main-grid">
        <section class="dash-panel dash-movement" aria-labelledby="cash-movement-title">
            <header class="dash-panel-header"><div><h2 id="cash-movement-title">Cash movement</h2><p>{{ $periodLabel }} · {{ $baseCurrency }}</p></div><a class="dash-text-link" href="/reports/cash-flow?{{ http_build_query($reportQuery) }}">Cash flow report <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon></a></header>
            <div class="dash-flow-totals"><div><span><i class="dash-swatch dash-received" aria-hidden="true"></i> Cash received</span><strong>{{ number_format($totalIncome,2) }}</strong></div><div><span><i class="dash-swatch dash-spent" aria-hidden="true"></i> Cash spent</span><strong>{{ number_format($totalExpense,2) }}</strong></div></div>
            @if($hasCashMovement)
                <div class="dash-chart-controls" role="group" aria-label="Cash movement series"><button type="button" data-cash-series="both" aria-pressed="true">Compare</button><button type="button" data-cash-series="income" aria-pressed="false">Received</button><button type="button" data-cash-series="expense" aria-pressed="false">Spent</button></div>
                <div class="dash-chart" id="dashboard-chart-region"><canvas id="dashboard-cash-chart" role="img" aria-label="Cash received and spent in {{ $baseCurrency }} for {{ $periodLabel }}. Exact values are available in the data table below."></canvas></div>
                <p class="dash-chart-fallback" id="dashboard-chart-fallback" hidden>The chart is unavailable. The period totals and data table below contain the same figures.</p>
                <details class="dash-chart-data"><summary>View chart data</summary><div class="dash-data-scroll" tabindex="0" role="region" aria-label="Cash movement values"><table><caption>Cash movement in {{ $baseCurrency }}</caption><thead><tr><th scope="col">Period</th><th scope="col">Received</th><th scope="col">Spent</th><th scope="col">Net movement</th></tr></thead><tbody>@foreach($trendData['labels'] as $index => $label)<tr><th scope="row">{{ $label }}</th><td>{{ number_format($trendData['income'][$index],2) }}</td><td>{{ number_format($trendData['expense'][$index],2) }}</td><td>{{ number_format($trendData['profit'][$index],2) }}</td></tr>@endforeach</tbody></table></div></details>
            @else
                <div class="dash-empty dash-chart-empty"><ion-icon name="bar-chart-outline" aria-hidden="true"></ion-icon><h3>No cash movement in this period</h3><p>Try another period or review the ledger for recorded transactions.</p><a class="dash-text-link" href="/transactions">Open ledger <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon></a></div>
            @endif
        </section>

        <section class="dash-panel dash-work" aria-labelledby="today-work-title">
            <header class="dash-panel-header"><div><h2 id="today-work-title">Today's work</h2><p>{{ now()->format('l, d M') }}</p></div></header>
            <div class="dash-work-list">
                @foreach([
                    ['/approvals','checkmark-circle-outline','Pending approvals',$pendingApprovals,'Invoice scope plus pending loan settlements'],
                    ['/reminders','notifications-outline','Reminders due',$remindersCount,'Current · all companies'],
                    ['/projects','flag-outline','Milestones due today',$todayMilestones->count(),'Current · all companies'],
                    ['/budgets','pie-chart-outline','Budgets over limit',$overBudgetCount,'Active budgets · all companies']
                ] as [$href,$icon,$label,$count,$scope])
                    <a class="dash-work-row" href="{{ $href }}"><ion-icon name="{{ $icon }}" aria-hidden="true"></ion-icon><div><strong>{{ $label }}</strong><span>{{ $scope }}</span></div><span @class(['dash-count','dash-count-attention'=>$count>0])>{{ $count }}</span><ion-icon name="chevron-forward-outline" aria-hidden="true"></ion-icon></a>
                @endforeach
            </div>
            <div class="dash-payroll-shortcuts"><h3>Employee payments</h3><a href="/payroll">Monthly payroll <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon></a><a href="/payroll/statutory">EPF, ETF & tax <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon></a></div>
        </section>

        <section class="dash-panel dash-collections" aria-labelledby="collections-title">
            <header class="dash-panel-header"><div><h2 id="collections-title">Collections to follow up</h2><p>Unpaid invoices at period end · {{ $baseCurrency }}</p></div><a class="dash-text-link" href="/invoices">All invoices <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon></a></header>
            @forelse($overduePayments as $payment)
                @php
                    $days = $payment->due_date ? (int) \Carbon\Carbon::parse($payment->due_date)->startOfDay()->diffInDays(\Carbon\Carbon::parse($followUpDate)->startOfDay(), false) : null;
                    $dueLabel = $days === null ? 'No due date' : ($days > 0 ? $days.' '.Str::plural('day', $days).' overdue' : ($days === 0 ? 'Due today' : 'Upcoming'));
                @endphp
                <a class="dash-collection-row" href="/invoices?{{ http_build_query(['search'=>$payment->ref]) }}"><div><strong>{{ $payment->client }}</strong><span>{{ $payment->ref }} · {{ $payment->due_date ? 'Due '.\Carbon\Carbon::parse($payment->due_date)->format('d M Y') : 'No due date set' }}</span></div><div><strong>{{ number_format($payment->amount,2) }}</strong><span @class(['dash-due-badge','dash-overdue'=>$days>0])>{{ $dueLabel }}</span></div><ion-icon name="chevron-forward-outline" aria-hidden="true"></ion-icon></a>
            @empty
                <div class="dash-empty"><ion-icon name="checkmark-circle-outline" aria-hidden="true"></ion-icon><h3>No outstanding invoices</h3><p>There are no unpaid issued invoices in this scope.</p></div>
            @endforelse
            @if(count($overduePayments))<footer class="dash-panel-note">Up to five invoices, oldest due first. Due status as of {{ \Carbon\Carbon::parse($followUpDate)->format('d M Y') }}.</footer>@endif
        </section>

        <section class="dash-panel dash-spending" aria-labelledby="spending-title">
            <header class="dash-panel-header"><div><h2 id="spending-title">Spending by category</h2><p>Top five · selected period · {{ $baseCurrency }}</p></div></header>
            @forelse($expenseCategories as $category)
                <div class="dash-expense"><div><span>{{ $category->name ?: 'Uncategorised' }}</span><strong>{{ number_format($category->value,2) }}</strong></div><progress max="{{ max(1,$expenseCategories->max('value')) }}" value="{{ max(0,$category->value) }}" aria-label="{{ $category->name ?: 'Uncategorised' }} · {{ $baseCurrency }} {{ number_format($category->value,2) }}"></progress></div>
            @empty
                <div class="dash-empty"><ion-icon name="receipt-outline" aria-hidden="true"></ion-icon><h3>No expenses recorded</h3><p>Expenses in the selected period will appear here by category.</p></div>
            @endforelse
            <footer class="dash-panel-note"><a class="dash-text-link" href="/reports/expenses?{{ http_build_query($reportQuery) }}">Operating expense report <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon></a></footer>
        </section>
    </div>

    <section class="dash-panel dash-commitments" aria-labelledby="commitments-title">
        <header class="dash-panel-header"><div><h2 id="commitments-title">Commitments & budget</h2><p>Current position · all companies</p></div><a class="dash-text-link" href="/reports/audit-payables">Payables report <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon></a></header>
        <div class="dash-commitment-grid">
            <div><span>Outstanding loan principal</span><strong>{{ $baseCurrency }} {{ number_format($loansOutstanding,2) }}</strong><a href="/loans">Review active loans <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon></a></div>
            <div><span>Commissions & scheduled interest</span><strong>{{ $baseCurrency }} {{ number_format($outstandingPayables,2) }}</strong><div class="dash-split-links"><a href="/reports/commissions">Commissions</a><span aria-hidden="true">·</span><a href="/loans/schedules">Interest schedules</a></div></div>
            <div><span>Active budget used</span><strong @class(['dash-negative'=>$budgetUtilization>100])>{{ number_format($budgetUtilization,1) }}<small>%</small></strong><a href="/budgets">Review budgets <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon></a></div>
        </div>
    </section>
</div>
<script type="application/json" id="dashboard-chart-data">{!! json_encode(['labels'=>$trendData['labels'],'income'=>$trendData['income'],'expense'=>$trendData['expense'],'currency'=>$baseCurrency], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script src="{{ asset('dashboard.js') }}?v={{ substr(hash_file('sha256', public_path('dashboard.js')), 0, 12) }}" defer></script>
@endsection
