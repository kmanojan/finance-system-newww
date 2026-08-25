@extends('layouts.app')
@section('title', 'Loan Settlements Ledger')

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
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Loan Settlements & Payment Ledger</h1>
        <p class="subtitle" style="margin-top:0.3rem;">Complete audit history of principal repayments, interest settlements, and additional draws across all loans.</p>
    </div>
</header>

<!-- KPI Stat Tiles -->
<div class="metric-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1.25rem; margin-bottom:1.5rem;">
    <!-- Tile 1: Total Paid To Date -->
    <div class="metric-card" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; opacity:0.9;">Total Paid To Date (P+I)</h3>
            <ion-icon name="checkmark-done-circle-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.4rem; font-weight:800; margin-top:0.3rem;">LKR {{ number_format($totalPaidAll, 2) }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">All Repayments + Interest Paid</div>
    </div>

    <!-- Tile 2: Total Principal Repaid -->
    <div class="metric-card" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; opacity:0.9;">Principal Repaid</h3>
            <ion-icon name="arrow-down-circle-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.4rem; font-weight:800; margin-top:0.3rem;">LKR {{ number_format($totalPrincipalRepaid, 2) }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Total principal paid back</div>
    </div>

    <!-- Tile 3: Total Interest Paid -->
    <div class="metric-card" style="background: linear-gradient(135deg, #0284c7 0%, #06b6d4 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; opacity:0.9;">Interest Settled</h3>
            <ion-icon name="cash-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.4rem; font-weight:800; margin-top:0.3rem;">LKR {{ number_format($totalInterestPaid, 2) }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Total interest paid</div>
    </div>

    <!-- Tile 4: Additional Draws -->
    <div class="metric-card" style="background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; opacity:0.9;">Additional Draws</h3>
            <ion-icon name="arrow-up-circle-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.4rem; font-weight:800; margin-top:0.3rem;">LKR {{ number_format($totalDraws, 2) }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Additional borrowings drawn</div>
    </div>
</div>

<!-- Date Filter Toolbar -->
<div class="card" style="padding:1rem 1.25rem; margin-bottom:1.5rem; border:1px solid var(--border); border-radius:12px; background:var(--bg-card);">
    <form method="GET" action="/loans/settlements" style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap; margin:0;">
        <div style="display:flex; gap:0.5rem; align-items:center;">
            <label style="font-size:0.8rem; font-weight:600; color:var(--text-muted); margin:0;">Start Date:</label>
            <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}" style="width:150px; font-size:0.85rem; padding:0.4rem 0.6rem;">
        </div>

        <div style="display:flex; gap:0.5rem; align-items:center;">
            <label style="font-size:0.8rem; font-weight:600; color:var(--text-muted); margin:0;">End Date:</label>
            <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}" style="width:150px; font-size:0.85rem; padding:0.4rem 0.6rem;">
        </div>

        <div style="display:flex; gap:0.5rem; align-items:center;">
            <button class="btn btn-outline" type="submit" style="padding:0.4rem 1rem; font-size:0.85rem;">
                <ion-icon name="funnel-outline" style="vertical-align:middle;"></ion-icon> Filter
            </button>
            @if(request('start_date') || request('end_date'))
                <a href="/loans/settlements" class="btn btn-outline" style="color:var(--text-muted); padding:0.4rem 1rem; font-size:0.85rem; text-decoration:none;">Reset</a>
            @endif
        </div>
    </form>
</div>

<!-- Settlements Ledger Table -->
<div class="card" style="padding:0; overflow-x:auto; background:var(--bg-card); border-radius:12px; border:1px solid var(--border);">
    <table class="data-table" style="margin:0; width:100%; border-collapse:collapse;">
        <thead style="background:var(--bg-page); border-bottom:1px solid var(--border);">
            <tr>
                <th style="padding:0.85rem 1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">Date</th>
                <th style="padding:0.85rem 1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">Lender Name</th>
                <th style="padding:0.85rem 1rem; text-align:center; font-size:0.8rem; color:var(--text-muted);">Type</th>
                <th style="padding:0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">Payment Amount</th>
                <th style="padding:0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">Loan Total Paid (P+I)</th>
                <th style="padding:0.85rem 1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">Mode & Ref</th>
                <th style="padding:0.85rem 1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">Notes</th>
            </tr>
        </thead>
        <tbody>
            @foreach($settlements as $record)
            <tr style="border-bottom:1px solid var(--border-light);">
                <td style="padding:0.85rem 1rem; font-weight:600; font-size:0.85rem; color:var(--text-heading);">
                    {{ $record->date }}
                </td>
                <td style="padding:0.85rem 1rem;">
                    <a href="/loans/{{ $record->loan_id }}" style="text-decoration:none; color:var(--primary); font-weight:700; font-size:0.9rem;">
                        {{ $record->lender_name }}
                    </a>
                </td>
                <td style="padding:0.85rem 1rem; text-align:center;">
                    @if($record->category === 'repayment')
                        <span class="badge" style="background:#dcfce7; color:#15803d; font-weight:600; font-size:0.75rem;">
                            <ion-icon name="arrow-down-outline" style="vertical-align:middle;"></ion-icon> Principal Repayment
                        </span>
                    @elseif($record->category === 'interest')
                        <span class="badge" style="background:#e0f2fe; color:#0369a1; font-weight:600; font-size:0.75rem;">
                            <ion-icon name="cash-outline" style="vertical-align:middle;"></ion-icon> Interest Settlement
                        </span>
                    @else
                        <span class="badge" style="background:#fef3c7; color:#b45309; font-weight:600; font-size:0.75rem;">
                            <ion-icon name="arrow-up-outline" style="vertical-align:middle;"></ion-icon> Additional Draw
                        </span>
                    @endif
                </td>
                <td style="padding:0.85rem 1rem; text-align:right; font-weight:700; font-size:0.9rem; {{ $record->category === 'draw' ? 'color:var(--danger);' : 'color:var(--success);' }}">
                    {{ $record->currency }} {{ number_format($record->amount, 2) }}
                </td>
                <td style="padding:0.85rem 1rem; text-align:right;">
                    <span style="color:var(--text-heading); font-weight:800; font-size:0.9rem;">
                        {{ $record->currency }} {{ number_format($record->loan_total_paid, 2) }}
                    </span>
                    <div style="font-size:0.75rem; color:var(--text-muted);">
                        Prin: {{ number_format($record->loan_principal_paid, 2) }} | Int: {{ number_format($record->loan_interest_paid, 2) }}
                    </div>
                </td>
                <td style="padding:0.85rem 1rem; font-size:0.85rem; color:var(--text-heading);">
                    {{ ucfirst($record->payment_mode ?? 'Normal') }}
                    @if($record->reference_no)
                        <div style="font-size:0.75rem; color:var(--text-muted);">Ref: {{ $record->reference_no }}</div>
                    @endif
                </td>
                <td style="padding:0.85rem 1rem; font-size:0.85rem; color:var(--text-muted);">
                    {{ $record->notes ?? '-' }}
                </td>
            </tr>
            @endforeach

            @if($settlements->isEmpty())
            <tr>
                <td colspan="7" style="padding:2.5rem; text-align:center; color:var(--text-muted);">
                    <ion-icon name="receipt-outline" style="font-size:2.5rem; opacity:0.4; margin-bottom:0.5rem;"></ion-icon><br>
                    No settlement records found for the selected date range.
                </td>
            </tr>
            @endif
        </tbody>
    </table>
</div>

@if(!$settlements->isEmpty())
<div style="margin-top:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
    <div style="font-size:0.85rem; color:var(--text-muted);">
        Showing {{ $settlements->firstItem() ?? 0 }} to {{ $settlements->lastItem() ?? 0 }} of {{ $settlements->total() }} settlement records
    </div>
    <div>
        {{ $settlements->links() }}
    </div>
</div>
@endif
@endsection
