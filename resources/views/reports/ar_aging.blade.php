@extends('reports.layout')
@section('title', 'Accounts Receivable Aging')

@section('report-content')
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Accounts Receivable Aging Report</h1>
        <p class="subtitle" style="margin-top:0.3rem;">Outstanding client invoices bucketed by overdue periods as of {{ $to }}.</p>
    </div>
</header>

<div style="margin-bottom: 1.5rem;">
    <x-date-range-picker :from="$from" :to="$to" />
</div>

<div class="card" style="padding:0; overflow:hidden; background:var(--bg-card); border-radius:12px; border:1px solid var(--border);">
    <table class="data-table" style="margin:0; width:100%; border-collapse:collapse;">
        <thead style="background:var(--bg-page); border-bottom:1px solid var(--border);">
            <tr>
                <th style="padding:0.85rem 1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">Client Name</th>
                <th style="padding:0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">0 - 30 Days</th>
                <th style="padding:0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">31 - 60 Days</th>
                <th style="padding:0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">61 - 90 Days</th>
                <th style="padding:0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">90+ Days</th>
                <th style="padding:0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">Total Outstanding</th>
            </tr>
        </thead>
        <tbody>
            @foreach($agingData as $row)
            <tr style="border-bottom:1px solid var(--border-light);">
                <td style="padding:0.85rem 1rem; font-weight:700; color:var(--text-heading);">{{ $row->client_name }}</td>
                <td style="padding:0.85rem 1rem; text-align:right;">LKR {{ number_format($row->current, 2) }}</td>
                <td style="padding:0.85rem 1rem; text-align:right; color:#d97706; font-weight:600;">LKR {{ number_format($row->b30_60, 2) }}</td>
                <td style="padding:0.85rem 1rem; text-align:right; color:#ea580c; font-weight:600;">LKR {{ number_format($row->b60_90, 2) }}</td>
                <td style="padding:0.85rem 1rem; text-align:right; color:var(--danger); font-weight:700;">LKR {{ number_format($row->b90_plus, 2) }}</td>
                <td style="padding:0.85rem 1rem; text-align:right; font-weight:800; color:var(--primary);">LKR {{ number_format($row->total, 2) }}</td>
            </tr>
            @endforeach
            @if(empty($agingData))
            <tr><td colspan="6" class="text-center text-muted py-4" style="padding:2.5rem; text-align:center;">No outstanding accounts receivable found for this period.</td></tr>
            @endif
        </tbody>
    </table>
</div>
@endsection
