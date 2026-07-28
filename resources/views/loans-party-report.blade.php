@extends('layouts.app')
@section('title', 'Party Loan Payables & Paids Report')

@section('secondary-sidebar')
<aside class="sidebar-secondary" id="sidebarSecondary">
    <h2 class="sidebar-title">Loan Management</h2>
    <nav class="nav-links">
        <a href="/loans" class="nav-link {{ request()->is('loans') ? 'active' : '' }}">
            <ion-icon name="cash-outline"></ion-icon> Active Loans
        </a>
        <a href="/loans/schedules" class="nav-link {{ request()->is('loans/schedules') ? 'active' : '' }}">
            <ion-icon name="calendar-outline"></ion-icon> Schedules
        </a>
        <a href="/loans/settlements" class="nav-link {{ request()->is('loans/settlements') ? 'active' : '' }}">
            <ion-icon name="checkmark-done-circle-outline"></ion-icon> Settlements
        </a>
        <a href="/loans/party-report" class="nav-link {{ request()->is('loans/party-report') ? 'active' : '' }}">
            <ion-icon name="pie-chart-outline"></ion-icon> Party Payables & Paids
        </a>
    </nav>
</aside>
@endsection

@section('content')
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Party Loan Payables & Paids Report</h1>
        <p class="subtitle" style="margin-top:0.3rem;">Comprehensive breakdown of total principal borrowed, total settlements paid, and net payables by party/lender.</p>
    </div>
</header>

<!-- Overall Summary KPI Tiles -->
<div class="metric-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; margin-bottom:1.5rem;">
    <div class="metric-card" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="font-size:0.8rem; font-weight:600; text-transform:uppercase; opacity:0.9;">Total Facilities Borrowed</div>
        <div style="font-size:1.5rem; font-weight:800; margin-top:0.3rem;">LKR {{ number_format($overallBorrowed, 2) }}</div>
    </div>
    <div class="metric-card" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="font-size:0.8rem; font-weight:600; text-transform:uppercase; opacity:0.9;">Total Settlements Paid (Paids)</div>
        <div style="font-size:1.5rem; font-weight:800; margin-top:0.3rem;">LKR {{ number_format($overallPaids, 2) }}</div>
    </div>
    <div class="metric-card" style="background: linear-gradient(135deg, #dc2626 0%, #f43f5e 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="font-size:0.8rem; font-weight:600; text-transform:uppercase; opacity:0.9;">Net Outstanding Debt (Payables)</div>
        <div style="font-size:1.5rem; font-weight:800; margin-top:0.3rem;">LKR {{ number_format($overallPayables, 2) }}</div>
    </div>
</div>

<div class="card" style="padding:0; overflow-x: auto;">
    <table class="data-table" style="margin:0; width:100%;">
        <thead>
            <tr>
                <th>Party / Lender</th>
                <th style="text-align:center;">Facilities</th>
                <th style="text-align:right;">Total Borrowed</th>
                <th style="text-align:right;">Principal Repaid</th>
                <th style="text-align:right;">Interest Paid</th>
                <th style="text-align:right; color:var(--success);">Total Paid (Paids)</th>
                <th style="text-align:right; color:var(--danger);">Net Payable (Payables)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($partyReports as $rep)
            <tr>
                <td data-label="Party / Lender">
                    <strong style="color:var(--text-heading); font-size:1rem;">{{ $rep->party_name }}</strong>
                    <div style="font-size:0.75rem; color:var(--text-muted);">{{ $rep->active_count }} active facilities</div>
                </td>
                <td style="text-align:center;">
                    <span class="badge badge-draft">{{ $rep->loan_count }} Loans</span>
                </td>
                <td style="text-align:right; font-weight:600;">
                    {{ $rep->currency }} {{ number_format($rep->total_borrowed, 2) }}
                </td>
                <td style="text-align:right;">
                    {{ $rep->currency }} {{ number_format($rep->total_principal_repaid, 2) }}
                </td>
                <td style="text-align:right;">
                    {{ $rep->currency }} {{ number_format($rep->total_interest_paid, 2) }}
                </td>
                <td style="text-align:right; font-weight:700; color:var(--success);">
                    {{ $rep->currency }} {{ number_format($rep->total_paids, 2) }}
                </td>
                <td style="text-align:right; font-weight:800; color:var(--danger); font-size:1.05rem;">
                    {{ $rep->currency }} {{ number_format($rep->total_payables, 2) }}
                </td>
            </tr>
            @endforeach
            @if($partyReports->isEmpty())
            <tr>
                <td colspan="7" style="text-align:center; padding:2rem; color:var(--text-muted);">No party loan records found.</td>
            </tr>
            @endif
        </tbody>
    </table>
</div>
@endsection
