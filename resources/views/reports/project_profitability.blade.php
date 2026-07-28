@extends('reports.layout')
@section('title', 'Project Profitability')

@section('report-content')
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Project Profitability Matrix</h1>
        <p class="subtitle" style="margin-top:0.3rem;">Ranked project financial performance from {{ $from }} to {{ $to }}.</p>
    </div>
</header>

<div style="margin-bottom: 1.5rem;">
    <x-date-range-picker :from="$from" :to="$to" />
</div>

<div class="card" style="padding:0; overflow:hidden; background:var(--bg-card); border-radius:12px; border:1px solid var(--border);">
    <table class="data-table" style="margin:0; width:100%; border-collapse:collapse;">
        <thead style="background:var(--bg-page); border-bottom:1px solid var(--border);">
            <tr>
                <th style="padding:0.85rem 1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">Project Name</th>
                <th style="padding:0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">Invoiced</th>
                <th style="padding:0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">Collected</th>
                <th style="padding:0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">Cost Allocations</th>
                <th style="padding:0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">Direct Expenses</th>
                <th style="padding:0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">Net Profit</th>
                <th style="padding:0.85rem 1rem; text-align:center; font-size:0.8rem; color:var(--text-muted);">Margin %</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report as $p)
            <tr style="border-bottom:1px solid var(--border-light);">
                <td style="padding:0.85rem 1rem; font-weight:700; color:var(--text-heading);">{{ $p->project_name }}</td>
                <td style="padding:0.85rem 1rem; text-align:right; font-weight:600;">{{ $p->currency }} {{ number_format($p->invoiced, 2) }}</td>
                <td style="padding:0.85rem 1rem; text-align:right; color:var(--success);">{{ $p->currency }} {{ number_format($p->collected, 2) }}</td>
                <td style="padding:0.85rem 1rem; text-align:right; color:#d97706;">{{ $p->currency }} {{ number_format($p->cost_allocations, 2) }}</td>
                <td style="padding:0.85rem 1rem; text-align:right; color:var(--danger);">{{ $p->currency }} {{ number_format($p->direct_expenses, 2) }}</td>
                <td style="padding:0.85rem 1rem; text-align:right; font-weight:800; color:{{ $p->net_profit >= 0 ? 'var(--success)' : 'var(--danger)' }};">
                    {{ $p->currency }} {{ number_format($p->net_profit, 2) }}
                </td>
                <td style="padding:0.85rem 1rem; text-align:center;">
                    <span class="badge" style="background:{{ $p->margin >= 20 ? 'var(--success-light)' : ($p->margin >= 0 ? '#fef3c7' : '#fee2e2') }}; color:{{ $p->margin >= 20 ? 'var(--success)' : ($p->margin >= 0 ? '#b45309' : '#b91c1c') }}; font-weight:700; font-size:0.75rem; padding:0.2rem 0.5rem; border-radius:4px;">
                        {{ number_format($p->margin, 1) }}%
                    </span>
                </td>
            </tr>
            @endforeach
            @if(empty($report))
            <tr><td colspan="7" class="text-center text-muted py-4" style="padding:2.5rem; text-align:center;">No project profitability records available for this period.</td></tr>
            @endif
        </tbody>
    </table>
</div>
@endsection
