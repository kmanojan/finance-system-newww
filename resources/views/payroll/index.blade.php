@extends('payroll.layout')
@section('title', 'Monthly payroll')

@section('payroll-content')
<link rel="stylesheet" href="{{ asset('people-register.css') }}?v={{ hash_file('sha256', public_path('people-register.css')) }}">
@php
    $statuses = ['all' => 'All payrolls', 'draft' => 'Draft', 'review' => 'Awaiting approval', 'approved' => 'Approved', 'completed' => 'Completed', 'void' => 'Void'];
    $activeStatus = request('status') ?: 'all';
    $scope = array_filter(request()->only(['company_id', 'month', 'q']), fn ($value) => filled($value));
    $hasFilters = $scope || $activeStatus !== 'all';
    $money = fn ($cents) => number_format($cents / 100, 2);
@endphp
<div class="pi-page pi-payroll">
    <header class="pi-header">
        <div><h1>Monthly payroll</h1><p>Prepare salaries, follow approvals and see what remains to pay.</p></div>
        <div class="pi-actions">
            <details class="pi-menu">
                <summary class="pi-button">More actions <ion-icon name="chevron-down-outline" aria-hidden="true"></ion-icon></summary>
                <div class="pi-menu-links"><a href="/payroll/statutory">EPF, ETF &amp; tax payments</a><a href="/payroll/import-history">Import historical payroll</a><a href="/employees">Employee salary profiles</a></div>
            </details>
            <a href="/payroll/create" class="pi-button pi-primary"><ion-icon name="add-outline" aria-hidden="true"></ion-icon> Create payroll</a>
        </div>
    </header>

    <section class="pi-overview" aria-label="Payroll processing summary">
        <div class="pi-highlight">
            <span>Awaiting approval</span>
            <strong>{{ number_format($statusCounts->get('review', 0)) }}</strong>
            <p>{{ $scope ? 'Within the selected search, month and company' : 'Across all companies and payroll months' }}</p>
        </div>
        <dl class="pi-metrics">
            <div><dt>Draft</dt><dd>{{ number_format($statusCounts->get('draft', 0)) }}</dd></div>
            <div><dt>Approved</dt><dd>{{ number_format($statusCounts->get('approved', 0)) }}</dd></div>
            <div><dt>Completed</dt><dd>{{ number_format($statusCounts->get('completed', 0)) }}</dd></div>
        </dl>
    </section>

    <section class="pi-register" aria-labelledby="payroll-register-title">
        <header class="pi-register-header">
            <h2 id="payroll-register-title">Payroll register</h2>
            <p>{{ number_format($runs->total()) }} {{ Str::plural('payroll', $runs->total()) }}{{ $hasFilters ? ' matching your filters' : ' in total' }}</p>
        </header>
        <nav class="pi-tabs" aria-label="Filter by processing status">
            @foreach($statuses as $status => $label)
                <a href="/payroll?{{ http_build_query($scope + ['status' => $status]) }}" @if($activeStatus === $status) aria-current="page" @endif>
                    {{ $label }} <span>{{ number_format($status === 'all' ? $statusCounts->sum() : $statusCounts->get($status, 0)) }}</span>
                </a>
            @endforeach
        </nav>
        <details class="pi-filter-panel" @if($scope) open @endif>
            <summary><ion-icon name="options-outline" aria-hidden="true"></ion-icon> Find or filter payrolls @if($scope)<span>{{ count($scope) }} applied</span>@endif</summary>
            <form method="get" action="/payroll" class="pi-filters" aria-label="Filter payrolls">
                @if($activeStatus !== 'all')<input type="hidden" name="status" value="{{ $activeStatus }}">@endif
                <div class="pi-field pi-search"><label for="payroll-search">Find a payroll</label><input id="payroll-search" name="q" type="search" maxlength="150" placeholder="Company or PR number" value="{{ request('q') }}"></div>
                <div class="pi-field"><label for="filter-month">Payroll month</label><input id="filter-month" type="month" name="month" value="{{ request('month') }}"></div>
                <div class="pi-field"><label for="filter-company">Company</label><select name="company_id" id="filter-company"><option value="">All companies</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(request('company_id') == $company->id)>{{ $company->name }}</option>@endforeach</select></div>
                <div class="pi-actions pi-filter-actions">
                    <button type="submit" class="pi-button">Apply filters</button>
                    @if($hasFilters)<a href="/payroll" class="pi-button pi-quiet">Clear filters</a>@endif
                </div>
            </form>
        </details>
        @if($runs->count())
            <div class="pi-columns" aria-hidden="true"><span>Payroll / company</span><span>Processing</span><span>Salary payment</span><span>Employer cost</span><span>Action</span></div>
        @endif
        @forelse($runs as $run)
            @php
                $payrollMonth = \Carbon\Carbon::parse($run->month);
                $month = $payrollMonth->format('F Y');
                $reference = 'PR-'.str_pad($run->id, 5, '0', STR_PAD_LEFT);
                $payment = $run->salary_payment;
                $posted = in_array($run->status, \App\Services\PayrollService::POSTED_STATUSES);
            @endphp
            <article class="pi-row" aria-labelledby="payroll-row-{{ $run->id }}">
                <div class="pi-identity">
                    <span class="pi-mark" aria-hidden="true">{{ $payrollMonth->format('M') }}</span>
                    <div><h3 id="payroll-row-{{ $run->id }}"><a href="/payroll/{{ $run->id }}">{{ $month }}</a></h3><p class="pi-subline">{{ $run->company_name }}</p><p class="pi-subline">{{ $reference }} · {{ number_format($run->employee_count) }} {{ Str::plural('employee', $run->employee_count) }}</p></div>
                </div>
                <div class="pi-stage"><span class="pi-label">Processing</span><span class="pi-status pi-state-{{ $run->status }}">{{ $statuses[$run->status] ?? ucfirst($run->status) }}</span></div>
                <div class="pi-money pi-key-money">
                    <span class="pi-label">Salary payment</span>
                    @if($posted)
                        <span class="pi-status pi-state-{{ $payment['state'] }}">{{ $payment['label'] }}</span>
                        <span class="pi-amount"><small>{{ $run->currency }}</small> {{ $money($payment['balance']) }} <small>left</small></span>
                        <span class="pi-subline">Paid {{ $run->currency }} {{ $money($payment['paid']) }}</span>
                        <span class="pi-subline">All unpaid obligations {{ $run->currency }} {{ $money($run->balance) }}</span>
                    @elseif($run->status === 'void')
                        <span class="pi-subline">Voided</span>
                    @else
                        <span class="pi-amount"><small>{{ $run->currency }}</small> {{ $money($payment['due']) }}</span>
                        <span class="pi-subline">Estimated take-home pay</span>
                    @endif
                </div>
                <div class="pi-money pi-employer"><span class="pi-label">Employer cost</span><span class="pi-amount"><small>{{ $run->currency }}</small> {{ $money($run->cost) }}</span></div>
                <div class="pi-row-actions"><a href="/payroll/{{ $run->id }}" class="pi-button pi-quiet" aria-label="Open {{ $reference }} for {{ $run->company_name }}">Open <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon></a></div>
            </article>
        @empty
            <div class="pi-empty"><h3>{{ $hasFilters ? 'No matching payrolls' : 'No payrolls yet' }}</h3><p>{{ $hasFilters ? 'Try another month, company, status or search term.' : 'Set up employee salary profiles before preparing the first payroll.' }}</p><a href="{{ $hasFilters ? '/payroll' : '/employees' }}" class="pi-button">{{ $hasFilters ? 'Clear filters' : 'Review employee salaries' }}</a></div>
        @endforelse
        @if($runs->count() || $runs->hasPages())
            <footer class="pi-pagination">
                @if($runs->count())<span>Showing {{ $runs->firstItem() }}–{{ $runs->lastItem() }} of {{ $runs->total() }} payrolls</span>@endif
                {{ $runs->links() }}
            </footer>
        @endif
    </section>
    <p class="pi-note">Processing and payment are tracked separately. Completed payrolls can still have unpaid salaries or contributions.</p>
</div>
@endsection
