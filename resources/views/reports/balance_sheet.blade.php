@extends('reports.layout')
@section('title', 'Balance Sheet')

@section('report-content')
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Balance Sheet Statement</h1>
        <p class="subtitle" style="margin-top:0.3rem;">Point-in-time financial position: Assets vs Liabilities vs Equity as of {{ $asOfDate }}.</p>
    </div>
</header>

<div style="margin-bottom: 1.5rem;">
    <x-date-range-picker :from="$from" :to="$to" />
</div>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:1.5rem;">
    <!-- Assets -->
    <div class="card" style="padding:1.5rem; background:var(--bg-card); border-radius:12px; border:1px solid var(--border);">
        <h3 style="font-size:1.1rem; font-weight:700; color:var(--primary); margin-bottom:1rem; border-bottom:2px solid var(--primary-light); padding-bottom:0.5rem;">
            Assets
        </h3>
        <div style="display:flex; justify-content:space-between; padding:0.6rem 0; border-bottom:1px solid var(--border-light);">
            <span>Bank & Cash Balances</span>
            <span style="font-weight:700;">LKR {{ number_format($totalBankAssets, 2) }}</span>
        </div>
        <div style="display:flex; justify-content:space-between; padding:0.6rem 0; border-bottom:1px solid var(--border-light);">
            <span>Accounts Receivable (Unpaid Invoices)</span>
            <span style="font-weight:700;">LKR {{ number_format($accountsReceivable, 2) }}</span>
        </div>
        <div style="display:flex; justify-content:space-between; padding:0.8rem 0; font-size:1.1rem; font-weight:800; color:var(--primary); margin-top:0.5rem;">
            <span>TOTAL ASSETS</span>
            <span>LKR {{ number_format($totalAssets, 2) }}</span>
        </div>
    </div>

    <!-- Liabilities & Equity -->
    <div class="card" style="padding:1.5rem; background:var(--bg-card); border-radius:12px; border:1px solid var(--border);">
        <h3 style="font-size:1.1rem; font-weight:700; color:var(--danger); margin-bottom:1rem; border-bottom:2px solid #fee2e2; padding-bottom:0.5rem;">
            Liabilities & Equity
        </h3>
        <div style="display:flex; justify-content:space-between; padding:0.6rem 0; border-bottom:1px solid var(--border-light);">
            <span>Accounts Payable (Pending Expenses)</span>
            <span style="font-weight:700;">LKR {{ number_format($accountsPayable, 2) }}</span>
        </div>
        <div style="display:flex; justify-content:space-between; padding:0.6rem 0; border-bottom:1px solid var(--border-light);">
            <span>Outstanding Loan Principal</span>
            <span style="font-weight:700;">LKR {{ number_format($outstandingLoans, 2) }}</span>
        </div>
        <div style="display:flex; justify-content:space-between; padding:0.6rem 0; border-bottom:1px solid var(--border-light); font-weight:700;">
            <span>Total Liabilities</span>
            <span>LKR {{ number_format($totalLiabilities, 2) }}</span>
        </div>
        <div style="display:flex; justify-content:space-between; padding:0.6rem 0; border-bottom:1px solid var(--border-light); margin-top:0.5rem;">
            <span>Retained Earnings (P&L Accumulation)</span>
            <span style="font-weight:700; color:var(--success);">LKR {{ number_format($retainedEarnings, 2) }}</span>
        </div>
        <div style="display:flex; justify-content:space-between; padding:0.8rem 0; font-size:1.1rem; font-weight:800; color:var(--text-heading); margin-top:0.5rem;">
            <span>TOTAL LIABILITIES & EQUITY</span>
            <span>LKR {{ number_format($totalEquity, 2) }}</span>
        </div>
    </div>
</div>
@endsection
