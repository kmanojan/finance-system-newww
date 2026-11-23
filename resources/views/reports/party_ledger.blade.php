@extends('reports.layout')
@section('title', 'Party Ledger')

@section('report-content')
<link rel="stylesheet" href="{{ asset('party-ledger.css') }}?v={{ hash_file('sha256', public_path('party-ledger.css')) }}">
@php
    $date = fn ($value) => $value ? \Carbon\CarbonImmutable::parse($value)->format('j M Y') : '—';
    $money = fn ($cents, $currency) => $currency.' '.number_format($cents / 100, 2);
    $filters = array_filter(['q' => $query, 'role' => $role, 'balance' => $balance], fn ($value) => $value !== '' && $value !== 'all');
    $exportQuery = http_build_query($filters + ($party ? ['party_id' => $party->id] : []));
    $labels = ['receivable' => 'Project receivables', 'loan' => 'Loan payable', 'salary' => 'Salary pending', 'other' => 'Bills & commissions', 'staff_receivable' => 'Staff recoveries', 'unapplied' => 'Unapplied receipts'];
    if (!$canViewPayroll) unset($labels['salary'], $labels['staff_receivable']);
    $selectedTotals = $party ? $party->balances : $workspaceTotals;
    $sections = [
        'receivable' => ['Project invoices', 'document-text-outline', 'Invoiced', 'Applied', 'Receivable'],
        'advance' => ['Salary advances', 'wallet-outline', 'Advanced', 'Recovered', 'Receivable'],
        'staff_loan' => ['Employee loans', 'card-outline', 'Opening balance', 'Recovered through payroll', 'Receivable'],
        'unapplied' => ['Unapplied receipts', 'wallet-outline', 'Received', 'Applied', 'Unapplied'],
        'loan' => ['Borrowed loans', 'cash-outline', 'Obligation', 'Paid toward obligation', 'Payable'],
        'salary' => ['Employee salaries', 'person-outline', 'Net salary', 'Paid', 'Salary pending'],
        'vendor' => ['Vendor bills', 'receipt-outline', 'Billed', 'Paid', 'Payable'],
        'commission' => ['Commissions', 'briefcase-outline', 'Earned', 'Paid', 'Payable'],
        'pending_in' => ['Pending ledger income', 'arrow-down-outline', 'Expected', 'Received', 'Pending'],
        'pending_out' => ['Pending ledger expenses', 'arrow-up-outline', 'Expected', 'Paid', 'Pending'],
    ];
    if (!$canViewPayroll) unset($sections['salary'], $sections['advance'], $sections['staff_loan']);
    $statutoryKinds = \App\Services\PayrollStatutoryService::KINDS + ['other' => 'Other payroll deductions'];
    $remittanceBreakdown = $statutory->groupBy('kind')->map(fn ($rows) => $rows->groupBy('currency')->map->sum('balance_cents'));
    $tabs = ['all' => 'All parties', 'payables' => 'Payables', 'receivables' => 'Receivables'];
    $activeTab = match ($balance) {
        'payables', 'loan', 'salary', 'other', 'pending_out' => 'payables',
        'receivables', 'receivable', 'staff_receivable', 'unapplied', 'pending_in' => 'receivables',
        default => 'all',
    };
    $unlinkedGroups = ['Loans without a linked party' => $unlinkedLoans, 'Receipts without a linked client' => $unassignedReceipts, 'Staff receivables without a linked party' => $unlinkedStaff, 'Pending ledger entries without a party' => $unlinkedPending];
@endphp

<div class="party-ledger ds-stack">
    @if($party)
        <a class="party-ledger-back" href="{{ route('reports.party_ledger', $filters) }}">
            <ion-icon name="arrow-back-outline" aria-hidden="true"></ion-icon> All parties
        </a>
    @endif
    <header class="page-header party-ledger-header">
        <div>
            <h1>{{ $party ? $party->name : 'Party ledger' }}</h1>
            <p>Balances through {{ $date($asOf) }}@if($party && $party->employeeCodes) · {{ $party->employeeCodes }}@endif</p>
        </div>
        @unless(request()->is('reports/export/*'))
            <div class="party-ledger-actions" aria-label="Export party ledger">
                <a class="btn btn-outline" href="{{ url('/reports/export/party-ledger/xlsx').'?'.$exportQuery }}">
                    <ion-icon name="download-outline" aria-hidden="true"></ion-icon> Excel
                </a>
                <a class="btn btn-outline" href="{{ url('/reports/export/party-ledger/pdf').'?'.$exportQuery }}">
                    <ion-icon name="document-outline" aria-hidden="true"></ion-icon> PDF
                </a>
            </div>
        @endunless
    </header>

    @include('reports.partials.party-ledger-position')

    @if(!$party)
        @if($errors->any())<p class="ds-notice error" role="alert">{{ $errors->first() }}</p>@endif
        <section class="ds-panel party-ledger-panel" aria-labelledby="party-register-title">
            <div class="party-ledger-panel-header">
                <div>
                    <h2 id="party-register-title">Parties</h2>
                    <p>{{ number_format($registry->total()) }} {{ Str::plural('party', $registry->total()) }} found</p>
                </div>
                @if($canViewPayroll)
                    <a class="party-ledger-jump" href="#statutory-payables">EPF, ETF & tax <ion-icon name="arrow-down-outline" aria-hidden="true"></ion-icon></a>
                @endif
            </div>
            @unless(request()->is('reports/export/*'))
            <nav class="party-ledger-views" aria-label="Party balance views">
                @foreach($tabs as $key => $label)
                    <a href="{{ route('reports.party_ledger', collect($filters)->except('balance')->all() + ['balance' => $key]) }}"
                        @if($activeTab === $key) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
            </nav>
            <form method="get" class="party-ledger-filters">
                <div>
                    <label for="party-ledger-search">Find a party</label>
                    <input id="party-ledger-search" class="form-control" type="search" name="q" value="{{ $query }}" placeholder="{{ $canViewPayroll ? 'Name or employee code' : 'Party name' }}">
                </div>
                <div>
                    <label for="party-ledger-role">Role</label>
                    <select id="party-ledger-role" class="form-control" name="role">
                        <option value="all">All roles</option>
                        @foreach($roleOptions as $option)<option value="{{ $option }}" @selected($role === $option)>{{ Str::headline($option) }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="party-ledger-balance">Show</label>
                    <select id="party-ledger-balance" class="form-control" name="balance">
                        <option value="all" @selected($balance === 'all')>All parties</option>
                        <option value="open" @selected($balance === 'open')>With outstanding balances</option>
                        <option value="payables" @selected($balance === 'payables')>Any payable</option>
                        <option value="receivables" @selected($balance === 'receivables')>Any receivable</option>
                        <option value="pending_in" @selected($balance === 'pending_in')>Pending ledger income</option>
                        <option value="pending_out" @selected($balance === 'pending_out')>Pending ledger expenses</option>
                        @foreach($labels as $key => $label)<option value="{{ $key }}" @selected($balance === $key)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="party-ledger-filter-actions">
                    <button class="btn btn-primary" type="submit">Apply filters</button>
                    @if($filters)<a href="{{ route('reports.party_ledger') }}" class="btn btn-outline">Reset</a>@endif
                </div>
            </form>
            @endunless

            <div class="party-ledger-scroll" role="region" aria-label="Party balances" tabindex="0">
                <table class="party-ledger-table party-ledger-register">
                    <thead><tr><th scope="col">Party</th>@foreach($labels as $label)<th class="party-ledger-numeric" scope="col">{{ $label }}</th>@endforeach</tr></thead>
                    <tbody>
                        @forelse($registry as $row)
                            <tr>
                                <th scope="row">
                                    <a class="party-ledger-name" href="{{ route('reports.party_ledger', $filters + ['party_id' => $row->id]) }}">
                                        {{ $row->name }}<ion-icon name="chevron-forward-outline" aria-hidden="true"></ion-icon>
                                    </a>
                                    <small>{{ collect($row->roles)->map(fn ($value) => Str::headline($value))->implode(' · ') }}@if($row->employeeCodes) · {{ $row->employeeCodes }}@endif @if($row->deleted_at) · Archived @endif</small>
                                </th>
                                @foreach($labels as $key => $label)
                                    <td class="party-ledger-numeric" data-label="{{ $label }}">
                                        @forelse($row->balances->map(fn ($values) => $values[$key])->filter() as $currency => $amount)
                                            <span class="party-ledger-number">{{ $money($amount, $currency) }}</span>
                                        @empty
                                            <span class="party-ledger-dash">—</span>
                                        @endforelse
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td class="party-ledger-empty" colspan="{{ 1 + count($labels) }}">No parties match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($registry->hasPages() && !request()->is('reports/export/*'))
                <nav class="party-ledger-pagination" aria-label="Party pages">
                    <span>{{ $registry->firstItem() }}–{{ $registry->lastItem() }} of {{ $registry->total() }}</span>
                    <div>
                        @if($registry->previousPageUrl())<a class="btn btn-outline" rel="prev" href="{{ $registry->previousPageUrl() }}">Previous</a>@endif
                        @if($registry->nextPageUrl())<a class="btn btn-outline" rel="next" href="{{ $registry->nextPageUrl() }}">Next</a>@endif
                    </div>
                </nav>
            @endif
        </section>

        @if($canViewPayroll)
        <section class="ds-panel party-ledger-panel" id="statutory-payables" aria-labelledby="statutory-title">
            <div class="party-ledger-panel-header">
                <div>
                    <h2 id="statutory-title">EPF, ETF & tax</h2>
                    <p>Company remittances by payroll month. Submission and payment have separate statuses.</p>
                </div>
                <a href="/payroll/statutory" class="party-ledger-jump">Open remittances <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon></a>
            </div>
            @foreach($statutoryKinds as $kind => $label)
                @php
                    $months = $statutory->where('kind', $kind);
                    $outstanding = $months->groupBy('currency')->sortKeys()->map->sum('balance_cents')->filter();
                @endphp
                <details class="party-ledger-disclosure" @if($loop->first) open @endif>
                    <summary>
                        <span>{{ $kind === 'apit' ? 'Tax (APIT)' : $label }}<small>{{ $months->count() }} payroll months</small></span>
                        <span class="party-ledger-disclosure-values">
                            @forelse($outstanding as $currency => $amount)<strong>{{ $money($amount, $currency) }}</strong>@empty<span>—</span>@endforelse
                            <ion-icon name="chevron-down-outline" aria-hidden="true"></ion-icon>
                        </span>
                    </summary>
                    <div class="party-ledger-scroll" role="region" aria-label="{{ $label }} payroll remittances" tabindex="0">
                        <table class="party-ledger-table">
                            <thead><tr><th scope="col">Payroll month</th><th scope="col">Due date</th><th scope="col" class="party-ledger-numeric">Due</th><th scope="col" class="party-ledger-numeric">Paid</th><th scope="col" class="party-ledger-numeric">Remaining</th><th scope="col">Payment</th><th scope="col">Submission</th></tr></thead>
                            <tbody>
                            @forelse($months as $month)
                                <tr>
                                    <th scope="row"><a href="{{ $kind === 'other' ? '/payroll/'.$month->run_id.'#payments' : '/payroll/'.$month->run_id.'/statutory/'.$kind }}">{{ \Carbon\CarbonImmutable::parse($month->month)->format('F Y') }}</a><small>{{ $month->company_name }}</small></th>
                                    <td>{{ $date($month->due_on) }}</td>
                                    <td class="party-ledger-numeric">{{ $money($month->due_cents, $month->currency) }}</td>
                                    <td class="party-ledger-numeric">{{ $money($month->paid_cents, $month->currency) }}</td>
                                    <td class="party-ledger-numeric">{{ $money($month->balance_cents, $month->currency) }}</td>
                                    <td><span @class(['party-ledger-status', 'is-open' => $month->balance_cents > 0])>{{ $month->payment_status }}</span></td>
                                    <td>{{ $kind === 'other' ? '—' : $month->submission }}@if($month->action === 'submitted')<small>{{ $date($month->event_on) }}</small>@endif</td>
                                </tr>
                            @empty
                                <tr><td class="party-ledger-empty" colspan="7">No {{ $label }} obligations from approved payroll.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </details>
            @endforeach
        </section>
        @endif

        @if(collect($unlinkedGroups)->contains(fn ($rows) => $rows->isNotEmpty()))
            <section class="ds-panel party-ledger-panel" aria-labelledby="unlinked-title">
                <div class="party-ledger-panel-header"><div><h2 id="unlinked-title">Unlinked records</h2><p>These records could not be matched to one party.</p></div></div>
                @foreach($unlinkedGroups as $label => $rows)
                    @continue($rows->isEmpty())
                    <details class="party-ledger-disclosure">
                        <summary><span>{{ $label }}</span><span>{{ $rows->count() }}</span></summary>
                        <div class="party-ledger-scroll" role="region" aria-label="{{ $label }}" tabindex="0">
                            <table class="party-ledger-table">
                                <thead><tr><th scope="col">Source</th><th scope="col">Date</th><th class="party-ledger-numeric" scope="col">Remaining</th></tr></thead>
                                <tbody>
                                    @foreach($rows as $row)
                                        <tr><th scope="row"><a href="{{ $row->url }}">{{ $row->label }}</a><small>{{ $row->context ?? $row->project?->name ?? '' }}</small></th><td>{{ $date($row->date) }}</td><td class="party-ledger-numeric">{{ $money($row->balance, $row->currency) }}</td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endforeach
            </section>
        @endif
    @else
        @include('reports.partials.party-ledger-detail')
    @endif
</div>
