@extends('reports.layout')
@section('title', 'Cash Flow Statement')

@section('report-content')
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Cash Flow Statement</h1>
        <p class="subtitle" style="margin-top:0.3rem;">Cash basis inflows and outflows from {{ $from }} to {{ $to }}.</p>
    </div>
</header>

<div style="margin-bottom: 1.5rem;">
    <x-date-range-picker :from="$from" :to="$to" />
</div>

<div class="card" style="padding:1.5rem; background:var(--bg-card); border-radius:12px; border:1px solid var(--border); max-width:700px;">
    <h3 style="font-size:1.1rem; font-weight:700; color:var(--primary); margin-bottom:1rem;">Operating Cash Activities</h3>
    <div style="display:flex; justify-content:space-between; padding:0.6rem 0; border-bottom:1px solid var(--border-light);">
        <span>Cash Collections & Income Inflows</span>
        <span style="font-weight:700; color:var(--success);">+LKR {{ number_format($operatingInflows, 2) }}</span>
    </div>
    <div style="display:flex; justify-content:space-between; padding:0.6rem 0; border-bottom:1px solid var(--border-light);">
        <span>Operating Expense Outflows</span>
        <span style="font-weight:700; color:var(--danger);">-LKR {{ number_format($operatingOutflows, 2) }}</span>
    </div>
    <div style="display:flex; justify-content:space-between; padding:0.6rem 0; font-weight:700; border-bottom:2px solid var(--border);">
        <span>Net Operating Cash Flow</span>
        <span style="color:{{ $netOperatingCash >= 0 ? 'var(--success)' : 'var(--danger)' }};">LKR {{ number_format($netOperatingCash, 2) }}</span>
    </div>

    <h3 style="font-size:1.1rem; font-weight:700; color:var(--primary); margin-top:1.5rem; margin-bottom:1rem;">Financing Cash Activities</h3>
    <div style="display:flex; justify-content:space-between; padding:0.6rem 0; border-bottom:1px solid var(--border-light);">
        <span>Loan Principal Draws</span>
        <span style="font-weight:700; color:var(--success);">+LKR {{ number_format($financingInflows, 2) }}</span>
    </div>
    <div style="display:flex; justify-content:space-between; padding:0.6rem 0; border-bottom:1px solid var(--border-light);">
        <span>Loan Principal Repayments</span>
        <span style="font-weight:700; color:var(--danger);">-LKR {{ number_format($financingOutflows, 2) }}</span>
    </div>
    <div style="display:flex; justify-content:space-between; padding:0.6rem 0; font-weight:700; border-bottom:2px solid var(--border);">
        <span>Net Financing Cash Flow</span>
        <span style="color:{{ $netFinancingCash >= 0 ? 'var(--success)' : 'var(--danger)' }};">LKR {{ number_format($netFinancingCash, 2) }}</span>
    </div>

    <div style="display:flex; justify-content:space-between; padding:1rem 0 0 0; font-size:1.2rem; font-weight:800; color:var(--text-heading); margin-top:1rem; border-top:2px solid var(--primary);">
        <span>NET CASH CHANGE FOR PERIOD</span>
        <span style="color:{{ $netCashFlow >= 0 ? 'var(--success)' : 'var(--danger)' }};">LKR {{ number_format($netCashFlow, 2) }}</span>
    </div>
</div>
@endsection
