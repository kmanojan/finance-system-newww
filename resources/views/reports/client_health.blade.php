@extends('reports.layout')

@section('report-title', 'Client payment health')

@section('report-content')
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Client payment health</h1>
        <p class="subtitle" style="margin-top:0.3rem;">Outstanding balances as of {{ $to }}. At risk means more than 30 days overdue. Amounts in {{ \App\Services\FinancialReportingService::baseCurrency() }}.</p>
    </div>
</header>

<div style="margin-bottom: 1.5rem;">
    <x-date-range-picker :from="$from" :to="$to" />
</div>

<div class="data-table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>Client Name</th>
                <th>Status</th>
                <th>Period billed</th><th>Outstanding</th><th>Overdue</th><th>Oldest overdue</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['clients'] as $client)
                <tr>
                    <td data-label="Client" style="color: var(--text-heading); font-weight: 500;">{{ $client->name }}</td>
                    <td data-label="Health"><span class="badge {{ $client->health === 'At risk' ? 'badge-danger' : ($client->health === 'Attention' ? 'badge-warning' : 'badge-neutral') }}">{{ $client->health }}</span></td>
                    <td data-label="Period billed" class="amount-cell">{{ number_format($client->period_billed,2) }}</td>
                    <td data-label="Outstanding" class="amount-cell">{{ number_format($client->outstanding,2) }}</td>
                    <td data-label="Overdue" class="amount-cell">{{ number_format($client->overdue,2) }}</td>
                    <td data-label="Oldest overdue">{{ $client->oldest_days ? $client->oldest_days.' days' : '—' }}</td>
                </tr>
            @endforeach
            @if(count($data['clients']) === 0)
                <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">No clients recorded.</td></tr>
            @endif
        </tbody>
    </table>
</div>
@endsection
