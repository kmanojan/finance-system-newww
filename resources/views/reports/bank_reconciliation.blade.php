@extends('reports.layout')
@section('title', 'Bank Reconciliation')

@section('report-content')
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Bank Reconciliation Report</h1>
        <p class="subtitle" style="margin-top:0.3rem;">System book balances vs unreconciled transaction lists from {{ $from }} to {{ $to }}.</p>
    </div>
</header>

<div style="margin-bottom: 1.5rem;">
    <x-date-range-picker :from="$from" :to="$to" />
</div>

<div style="display:flex; flex-direction:column; gap:1.5rem;">
    @foreach($bankAccounts as $acc)
    <div class="card" style="padding:1.5rem; background:var(--bg-card); border-radius:12px; border:1px solid var(--border);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid var(--border-light); padding-bottom:0.75rem;">
            <div>
                <h3 style="margin:0; font-size:1.15rem; font-weight:800; color:var(--text-heading);">{{ $acc->bank_name }} ({{ $acc->account_no }})</h3>
                <span style="font-size:0.8rem; color:var(--text-muted);">Currency: {{ $acc->currency }}</span>
            </div>
            <div style="text-align:right;">
                <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600;">System Book Balance</div>
                <div style="font-size:1.3rem; font-weight:800; color:var(--primary);">{{ $acc->currency }} {{ number_format($acc->current_balance, 2) }}</div>
            </div>
        </div>

        <div style="font-size:0.85rem; font-weight:700; color:var(--text-heading); margin-bottom:0.5rem;">
            Unreconciled Transactions ({{ $acc->unreconciled_tx->count() }}) - Total: {{ $acc->currency }} {{ number_format($acc->unreconciled_total, 2) }}
        </div>
        <table class="data-table" style="margin:0; width:100%; border-collapse:collapse;">
            <thead style="background:var(--bg-page); border-bottom:1px solid var(--border);">
                <tr>
                    <th style="padding:0.6rem 0.8rem; font-size:0.78rem;">Date</th>
                    <th style="padding:0.6rem 0.8rem; font-size:0.78rem;">Type</th>
                    <th style="padding:0.6rem 0.8rem; font-size:0.78rem;">Description</th>
                    <th style="padding:0.6rem 0.8rem; text-align:right; font-size:0.78rem;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($acc->unreconciled_tx as $tx)
                <tr style="border-bottom:1px solid var(--border-light);">
                    <td style="padding:0.6rem 0.8rem; font-size:0.82rem;">{{ $tx->transaction_date }}</td>
                    <td style="padding:0.6rem 0.8rem;"><span class="badge" style="background:{{ $tx->type === 'income' ? 'var(--success-light)' : '#fee2e2' }}; color:{{ $tx->type === 'income' ? 'var(--success)' : '#b91c1c' }}; font-size:0.7rem;">{{ ucfirst($tx->type) }}</span></td>
                    <td style="padding:0.6rem 0.8rem; font-size:0.82rem;">{{ $tx->notes ?? 'Transaction #' . $tx->id }}</td>
                    <td style="padding:0.6rem 0.8rem; text-align:right; font-weight:700;">{{ $acc->currency }} {{ number_format($tx->amount, 2) }}</td>
                </tr>
                @endforeach
                @if($acc->unreconciled_tx->isEmpty())
                <tr><td colspan="4" class="text-center text-muted py-3" style="padding:1rem; text-align:center; font-size:0.82rem;">All transactions reconciled cleanly for this account in the selected date range.</td></tr>
                @endif
            </tbody>
        </table>
    </div>
    @endforeach
</div>
@endsection
