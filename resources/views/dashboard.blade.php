@extends('layouts.app')
@section('title', 'Financial overview')
@section('content')
<header class="page-header">
    <div class="header-titles"><h1>Financial overview</h1><p class="subtitle">{{ \Carbon\Carbon::parse($startDate)->format('d M Y') }} – {{ \Carbon\Carbon::parse($endDate)->format('d M Y') }} · {{ $baseCurrency }} reporting currency</p></div>
    <a class="btn btn-primary" href="/transactions">Record transaction</a>
</header>
<details class="overview-filters" {{ request()->hasAny(['company_id','department_id','date_range']) ? 'open' : '' }}>
    <summary>Period &amp; company filters <span>{{ ucwords(str_replace('_',' ',$range)) }}</span></summary>
    <form method="GET" class="filter-bar" id="filterForm">
        <div class="filter-group"><label class="filter-label" for="date_range">Period</label><select name="date_range" id="date_range" class="filter-select"><option value="this_month" @selected($range==='this_month')>This month</option><option value="today" @selected($range==='today')>Today</option><option value="this_week" @selected($range==='this_week')>This week</option><option value="this_quarter" @selected($range==='this_quarter')>This quarter</option><option value="this_year" @selected($range==='this_year')>This year</option><option value="custom" @selected($range==='custom')>Custom dates</option></select></div>
        <div class="filter-group"><label class="filter-label" for="company_id">Company</label><select name="company_id" id="company_id" class="filter-select"><option value="">All companies</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected($companyId==$company->id)>{{ $company->name }}</option>@endforeach</select></div>
        <div class="filter-group"><label class="filter-label" for="department_id">Department</label><select name="department_id" id="department_id" class="filter-select"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" data-company="{{ $department->company_id }}" @selected($deptId==$department->id)>{{ $department->name }}</option>@endforeach</select></div>
        <div class="filter-group custom-date"><label class="filter-label" for="start_date">From</label><input type="date" id="start_date" name="start_date" class="filter-input" value="{{ $startDate }}"></div>
        <div class="filter-group custom-date"><label class="filter-label" for="end_date">To</label><input type="date" id="end_date" name="end_date" class="filter-input" value="{{ $endDate }}"></div>
        <button class="btn btn-primary" type="submit">Apply filters</button>
    </form>
</details>
<div class="overview-balances">
    <a href="/reports/bank-reconciliation"><span>Cash at bank</span><strong>{{ number_format($totalCash,2) }}</strong><small>{{ $baseCurrency }} · as of {{ $endDate }}</small></a>
    <a href="/invoices"><span>Receivables</span><strong>{{ number_format($outstandingReceivables,2) }}</strong><small>{{ $baseCurrency }} · unpaid invoices</small></a>
    <a href="/transactions?type=income"><span>Cash received</span><strong>{{ number_format($totalIncome,2) }}</strong><small>{{ $baseCurrency }} · selected period</small></a>
    <a href="/transactions?type=expense"><span>Cash spent</span><strong>{{ number_format($totalExpense,2) }}</strong><small>{{ $baseCurrency }} · selected period</small></a>
</div>
<div class="overview-work">
    <section class="ds-panel attention-panel">
        <header class="overview-section-header"><h2>Payments to follow up</h2><a href="/invoices">View invoices</a></header>
        @forelse($overduePayments as $payment)
        <a class="attention-row" href="/invoices"><div><strong>{{ $payment->client }}</strong><span>{{ $payment->ref }} · Due {{ $payment->due_date ?? '—' }}</span></div><div class="num"><strong>{{ number_format($payment->amount,2) }}</strong><span class="{{ $payment->days_overdue > 0 ? 'text-danger' : 'text-muted' }}">{{ $payment->days_overdue > 0 ? (int)$payment->days_overdue.' days overdue' : 'Upcoming' }}</span></div></a>
        @empty<p class="overview-empty">No outstanding invoices in this scope.</p>@endforelse
    </section>
    <section class="ds-panel">
        <header class="overview-section-header"><h2>Today's work</h2><a href="/reminders">View reminders</a></header>
        <a class="work-row" href="/approvals"><span>Pending approvals</span><strong>{{ $pendingApprovals }}</strong><span aria-hidden="true">→</span></a>
        <a class="work-row" href="/reminders"><span>Reminders due</span><strong>{{ $remindersCount }}</strong><span aria-hidden="true">→</span></a>
        <a class="work-row" href="/projects"><span>Milestones due today</span><strong>{{ $todayMilestones->count() }}</strong><span aria-hidden="true">→</span></a>
        <a class="work-row" href="/budgets"><span>Budgets over limit</span><strong>{{ $overBudgetCount }}</strong><span aria-hidden="true">→</span></a>
    </section>
</div>
<div class="overview-charts">
    <section class="ds-panel"><header class="overview-section-header"><h2>Cash movement</h2><span>{{ $baseCurrency }}</span></header><p class="overview-finding">Net cash movement <strong>{{ number_format($netProfit,2) }}</strong> in the selected period.</p><div class="overview-chart"><canvas id="trendChart" aria-label="Cash received and spent by period" role="img"></canvas></div></section>
    <section class="ds-panel"><header class="overview-section-header"><h2>Spending by category</h2><a href="/reports/expenses">Expense report</a></header>
        @forelse($expenseCategories as $category)
        <div class="expense-row"><div><span>{{ $category->name }}</span><strong>{{ number_format($category->value,2) }}</strong></div><progress max="{{ max(1,$expenseCategories->max('value')) }}" value="{{ max(0,$category->value) }}" aria-label="{{ $category->name }}"></progress></div>
        @empty<p class="overview-empty">No expenses recorded in this period.</p>@endforelse
    </section>
</div>
<section class="ds-panel overview-secondary">
    <h2>Commitments &amp; budget</h2>
    <div class="overview-summary"><a href="/payables/vendor-bills"><span>Commissions &amp; interest due</span><strong>{{ $baseCurrency }} {{ number_format($outstandingPayables,2) }}</strong></a><a href="/loans"><span>Outstanding loan principal</span><strong>{{ $baseCurrency }} {{ number_format($loansOutstanding,2) }}</strong></a><a href="/budgets"><span>Budget used</span><strong>{{ number_format($budgetUtilization,1) }}%</strong></a></div>
</section>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const range=document.getElementById('date_range'), company=document.getElementById('company_id'), department=document.getElementById('department_id');
    const dates=()=>document.querySelectorAll('.custom-date').forEach(el=>el.hidden=range.value!=='custom');
    dates(); range.addEventListener('change',dates);
    company.addEventListener('change',()=>{ [...department.options].forEach(option=>option.hidden=!!(company.value && option.value && option.dataset.company!==company.value)); if(department.selectedOptions[0]?.hidden) department.value=''; });
    if(typeof Chart==='undefined') return;
    const colors=()=>{const css=getComputedStyle(document.documentElement);return {text:css.getPropertyValue('--text-muted').trim(),line:css.getPropertyValue('--border').trim(),accent:css.getPropertyValue('--accent').trim()};};
    const c=colors();
    const chart=new Chart(document.getElementById('trendChart'),{type:'bar',data:{labels:@json($trendData['labels']),datasets:[{label:'Received',data:@json($trendData['income']),backgroundColor:c.accent,borderRadius:2},{label:'Spent',data:@json($trendData['expense']),backgroundColor:'#9699a2',borderRadius:2}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{color:c.text,font:{family:'Lexend Deca',size:12},boxWidth:10}}},scales:{x:{grid:{display:false},ticks:{color:c.text,maxRotation:0,autoSkip:true,maxTicksLimit:5}},y:{beginAtZero:true,grid:{color:c.line},ticks:{color:c.text,maxTicksLimit:5}}}}});
    new MutationObserver(()=>{const c=colors();chart.data.datasets[0].backgroundColor=c.accent;chart.options.plugins.legend.labels.color=c.text;chart.options.scales.x.ticks.color=c.text;chart.options.scales.y.ticks.color=c.text;chart.options.scales.y.grid.color=c.line;chart.update();}).observe(document.documentElement,{attributes:true,attributeFilter:['data-theme']});
});
</script>
@endsection
