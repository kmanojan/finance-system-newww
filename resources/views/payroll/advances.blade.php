@extends('payroll.layout')
@section('payroll-content')
<link rel="stylesheet" href="{{ asset('salary-advances.css') }}?v={{ hash_file('sha256',public_path('salary-advances.css')) }}">
@php
    $canManage = in_array(strtolower(auth()->user()->role), ['admin','manager']);
    $hasFilters = filled($filters['q'] ?? null) || filled($filters['company_id'] ?? null) || filled($filters['recovery_month'] ?? null) || $filters['status'] !== 'all';
@endphp
<div class="sa-workspace">
    <header class="pr-header sa-header">
        <div><h1>Salary advances</h1><p>Track paid advances and the amounts still to recover from payroll.</p></div>
        <div class="pr-actions"><a class="pr-btn" href="/payroll/advances/import-history"><ion-icon name="cloud-upload-outline" aria-hidden="true"></ion-icon>Import history</a><a class="pr-btn pr-btn-primary" href="#new-advance"><ion-icon name="add-outline" aria-hidden="true"></ion-icon>Record advance</a></div>
    </header>
    @forelse($summaries as $summary)
    <section class="sa-overview" aria-label="{{ $summary->currency }} advance balances for this view">
        <div class="sa-balance"><span>Remaining to recover</span><strong><small>{{ $summary->currency }}</small> {{ number_format($summary->remaining/100,2) }}</strong><span>Across {{ $summary->employees }} {{ Str::plural('employee',$summary->employees) }} · {{ $hasFilters ? 'filtered advances' : 'all posted advances' }}</span></div>
        <dl class="sa-metrics"><div><dt>Advances paid</dt><dd><small>{{ $summary->currency }}</small> {{ number_format($summary->issued/100,2) }}</dd></div><div><dt>Recovered through payroll</dt><dd><small>{{ $summary->currency }}</small> {{ number_format($summary->recovered/100,2) }}</dd></div></dl>
    </section>
    @empty
    <section class="sa-overview sa-overview-empty"><div class="sa-balance"><span>Remaining to recover</span><strong><small>LKR</small> 0.00</strong><span>No posted advances {{ $hasFilters ? 'match this view' : 'recorded yet' }}.</span></div><p>Paid advances stay outstanding until their recovery payroll is approved.</p></section>
    @endforelse
    <section class="pr-panel sa-register" aria-labelledby="advance-register-title">
        <div class="sa-register-heading"><div><h2 id="advance-register-title">Advance register</h2><p>Payment history and payroll recovery, together.</p></div><span class="sa-result-count">{{ number_format($payments->total()) }} {{ Str::plural('advance',$payments->total()) }}</span></div>
        <nav class="sa-status-tabs" aria-label="Filter advances by recovery status">
            @foreach(['all'=>['All advances',$counts->total],'outstanding'=>['Outstanding',$counts->outstanding],'recovered'=>['Recovered',$counts->recovered],'reversed'=>['Reversed',$counts->reversed]] as $key=>$tab)
            <a href="{{ url('/payroll/advances').'?'.http_build_query(array_merge(request()->except('page','status'), ['status'=>$key])) }}" @if($filters['status'] === $key) aria-current="page" @endif>{{ $tab[0] }}<span>{{ number_format($tab[1]) }}</span></a>
            @endforeach
        </nav>
        <form method="get" action="/payroll/advances" class="sa-filters" role="search" aria-label="Search salary advances">
            <input type="hidden" name="status" value="{{ $filters['status'] }}">
            <div class="pr-field sa-search"><label for="advance-search">Search advances</label><div class="sa-search-input"><ion-icon name="search-outline" aria-hidden="true"></ion-icon><input type="search" id="advance-search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Employee, code or payment reference" maxlength="150"></div></div>
            <details class="sa-more-filters" open @if(filled($filters['company_id'] ?? null) || filled($filters['recovery_month'] ?? null)) data-filtered @endif>
                <summary aria-label="Company and deduction month filters"><ion-icon name="options-outline" aria-hidden="true"></ion-icon><span>Filters</span></summary>
                <div class="sa-filter-popover">
                    <div class="pr-field"><label for="filter-company">Company</label><select id="filter-company" name="company_id"><option value="">All companies</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected(($filters['company_id'] ?? '') == $company->id)>{{ $company->name }}</option>@endforeach</select></div>
                    <div class="pr-field"><label for="filter-month">Deduction month</label><input type="month" id="filter-month" name="recovery_month" value="{{ $filters['recovery_month'] ?? '' }}"></div>
                    <button class="pr-btn sa-mobile-apply" type="submit">Apply filters</button>
                </div>
            </details>
            <div class="sa-filter-actions"><button class="pr-btn sa-apply" type="submit"><ion-icon name="search-outline" aria-hidden="true"></ion-icon><span>Apply filters</span></button>@if($hasFilters)<a class="sa-text-link" href="/payroll/advances">Clear filters</a>@endif</div>
        </form>
        <div class="sa-register-table" role="region" aria-label="Salary advance payments" tabindex="0">
            <table class="sa-table"><thead><tr><th scope="col">Employee</th><th scope="col">Payment</th><th scope="col" class="sa-number">Paid</th><th scope="col" class="sa-number">Recovered</th><th scope="col" class="sa-number">Remaining</th><th scope="col">Deduction month</th><th scope="col">Status</th><th scope="col"><span class="pr-visually-hidden">Actions</span></th></tr></thead><tbody>
            @forelse($payments as $payment)
            @php
                $remaining = $payment->status === 'reversed' ? 0 : $payment->amount_cents - $payment->recovered_cents;
                $state = $payment->status === 'reversed' ? 'Reversed' : ($remaining <= 0 ? 'Recovered' : ($payment->recovered_cents > 0 ? 'Partly recovered' : 'Outstanding'));
            @endphp
            <tr>
                <td data-label="Employee" class="sa-employee-cell"><div class="sa-person"><span class="sa-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($payment->full_name,0,1)) }}</span><div><a href="/employees/{{ $payment->employee_id }}">{{ $payment->full_name }}</a><small>{{ $payment->employee_code ?: 'Employee #'.$payment->employee_id }} · {{ $payment->company_name }}</small></div></div></td>
                <td data-label="Payment"><time datetime="{{ $payment->paid_on }}">{{ $payment->paid_on }}</time><small>{{ $payment->bank_name ?: 'Cash' }}</small><span class="sa-reference">{{ $payment->reference }}</span></td>
                <td data-label="Paid" class="sa-number"><strong>{{ number_format($payment->amount_cents/100,2) }}</strong><small>{{ $payment->currency }}</small></td>
                <td data-label="Recovered" class="sa-number" aria-label="Recovered: {{ number_format($payment->recovered_cents/100,2) }}"><span>{{ number_format($payment->recovered_cents/100,2) }}</span><small>{{ $payment->currency }}</small>@foreach($payment->recoveries as $recovery)<small><a href="/payroll/{{ $recovery->run_id }}/employees/{{ $recovery->payroll_item_id }}">{{ Carbon\Carbon::parse($recovery->month)->format('M Y') }} · {{ number_format($recovery->amount_cents/100,2) }}</a></small>@endforeach</td>
                <td data-label="Remaining" class="sa-number sa-remaining"><strong>{{ number_format($remaining/100,2) }}</strong><small>{{ $payment->currency }}</small></td>
                <td data-label="Deduction month">{{ $payment->recovery_month ? Carbon\Carbon::parse($payment->recovery_month)->format('F Y') : 'Not scheduled' }}@if($payment->historical_payroll_run_id)<small><a href="/payroll/{{ $payment->historical_payroll_run_id }}">Source payroll #{{ $payment->historical_payroll_run_id }}</a></small>@endif</td>
                <td data-label="Status"><span class="sa-status sa-status-{{ $payment->status === 'reversed' ? 'reversed' : ($remaining <= 0 ? 'recovered' : 'outstanding') }}">{{ $state }}</span>@if($payment->status === 'reversed')<small>{{ $payment->reversed_on }}</small><span class="sa-reference">{{ $payment->reversal_reason }}</span>@endif</td>
                <td data-label="Actions" class="sa-row-actions"><button type="button" class="sa-manage" data-advance-manage="advance-{{ $payment->id }}" aria-label="View advance details for {{ $payment->full_name }} paid {{ $payment->paid_on }}"><ion-icon name="ellipsis-horizontal" aria-hidden="true"></ion-icon></button> @if($payment->receipt_path)<a class="sa-receipt" href="/payroll/payments/{{ $payment->id }}/receipt" aria-label="Download receipt for {{ $payment->full_name }} paid {{ $payment->paid_on }}"><ion-icon name="document-attach-outline" aria-hidden="true"></ion-icon></a>@endif</td>
            </tr>
            @empty
            <tr><td colspan="8"><div class="sa-empty"><ion-icon name="wallet-outline" aria-hidden="true"></ion-icon><h3>{{ $hasFilters ? 'No matching advances' : 'No advances recorded yet' }}</h3><p>{{ $hasFilters ? 'Try another employee, company or deduction month.' : 'Record an advance already paid to an employee, then choose its payroll deduction month.' }}</p><a class="pr-btn" href="{{ $hasFilters ? '/payroll/advances' : '#new-advance' }}">{{ $hasFilters ? 'Clear filters' : 'Record first advance' }}</a></div></td></tr>
            @endforelse
            </tbody></table>
        </div>
        @if($payments->total())<div class="sa-pagination"><span>Showing {{ $payments->firstItem() }}–{{ $payments->lastItem() }} of {{ number_format($payments->total()) }} advances</span>{{ $payments->links() }}</div>@endif
        <p class="sa-register-note"><ion-icon name="information-circle-outline" aria-hidden="true"></ion-icon>Recovery is recorded when payroll is approved. Draft payroll deductions do not reduce these balances.</p>
    </section>
    @if($balances->count())
    <details class="pr-panel sa-employee-balances"><summary><span>Employee balances <small>{{ $balances->count() }} with an outstanding balance</small></span><ion-icon name="chevron-down-outline" aria-hidden="true"></ion-icon></summary><div class="sa-balance-list" role="region" aria-label="Outstanding employee balances" tabindex="0">@foreach($balances as $balance)<div><a href="/employees/{{ $balance->employee_id }}">{{ $balance->full_name }}<small>{{ $balance->company_name }}</small></a><strong>{{ $balance->currency }} {{ number_format($balance->balance/100,2) }}</strong></div>@endforeach</div></details>
    @endif
    @include('payroll.advance-form')
    @include('payroll.advance-actions')
</div>
<script src="{{ asset('salary-advances.js') }}?v={{ hash_file('sha256',public_path('salary-advances.js')) }}" defer></script>
@endsection
