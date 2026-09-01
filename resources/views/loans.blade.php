@extends('layouts.app')
@section('title', 'Loans Management')

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
<style>
/* Loan Index Minimal & Accordion Styles */
.loan-accordion-item {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 10px;
    margin-bottom: 0.75rem;
    transition: all 0.2s ease;
    overflow: hidden;
}

.loan-accordion-item:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-sm);
}

.loan-accordion-item.expanded {
    border-color: var(--primary);
    box-shadow: 0 4px 16px rgba(0,0,0,0.06);
}

.loan-summary-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.9rem 1.25rem;
    cursor: pointer;
    user-select: none;
    gap: 1rem;
    flex-wrap: wrap;
}

.loan-summary-main {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
    min-width: 260px;
}

.loan-summary-metrics {
    display: flex;
    align-items: center;
    gap: 1.75rem;
    flex-wrap: wrap;
}

.loan-summary-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.loan-chevron {
    transition: transform 0.25s ease;
    font-size: 1.15rem;
    color: var(--text-muted);
}

.loan-accordion-item.expanded .loan-chevron {
    transform: rotate(180deg);
    color: var(--primary);
}

.loan-drawer {
    display: none;
    padding: 1.25rem;
    background: var(--bg-page);
    border-top: 1px dashed var(--border);
    animation: fadeInDrawer 0.2s ease;
}

.loan-accordion-item.expanded .loan-drawer {
    display: block;
}

@keyframes fadeInDrawer {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}

.loan-drawer-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
    margin-bottom: 1.25rem;
}

.loan-drawer-box {
    background: var(--bg-card);
    border: 1px solid var(--border-light);
    border-radius: 8px;
    padding: 1rem;
}

.loan-drawer-title {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.loan-stat-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.35rem 0;
    font-size: 0.85rem;
    border-bottom: 1px solid var(--border-light);
}

.loan-stat-row:last-child {
    border-bottom: none;
}

.loan-stat-label {
    color: var(--text-muted);
}

.loan-stat-val {
    font-weight: 600;
    color: var(--text-heading);
}

.loan-progress-bar-wrap {
    background: var(--border-light);
    height: 6px;
    border-radius: 999px;
    overflow: hidden;
    margin: 0.5rem 0 0.75rem 0;
}

.loan-progress-bar-fill {
    height: 100%;
    background: var(--primary);
    border-radius: 999px;
    transition: width 0.3s ease;
}

.loan-filter-tab {
    padding: 0.4rem 0.85rem;
    border-radius: 20px;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    color: var(--text-muted);
    background: var(--bg-page);
    border: 1px solid var(--border);
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.loan-filter-tab:hover, .loan-filter-tab.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.loan-filter-tab .tab-badge {
    background: rgba(0,0,0,0.12);
    border-radius: 10px;
    padding: 0.1rem 0.45rem;
    font-size: 0.72rem;
}

.loan-filter-tab.active .tab-badge {
    background: rgba(255,255,255,0.25);
    color: white;
}
</style>

<!-- Page Header -->
<header class="page-header" style="margin-bottom: 1.25rem;">
    <div class="header-titles">
        <h1 style="font-size:1.65rem; font-weight:800; color:var(--text-heading); margin:0;">Third-Party Loans</h1>
        <p class="subtitle" style="margin-top:0.25rem; font-size:0.85rem; color:var(--text-muted);">
            Overview of borrowing facilities, repayments, scheduled interest, and debt obligations.
        </p>
    </div>
    <div style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap;">
        <button type="button" class="btn btn-outline" onclick="toggleAllAccordions()" id="btnToggleAll" style="font-size:0.82rem; padding:0.45rem 0.85rem; border-radius:8px;">
            <ion-icon name="reorder-four-outline" style="vertical-align:middle;"></ion-icon> <span id="toggleAllText">Expand All</span>
        </button>
        <button class="btn btn-primary-gradient btn-pill" onclick="openModal('createLoanModal')">
            <ion-icon name="add-outline" style="vertical-align:middle; font-size:1.1rem;"></ion-icon> Record New Loan
        </button>
    </div>
</header>

<!-- Streamlined Metric Summary Cards (4 Sleek Cards) -->
<div class="metric-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap:1rem; margin-bottom:1.25rem;">
    <!-- 1. Total Facilities Borrowed -->
    <div class="metric-card" style="background:var(--bg-card); border:1px solid var(--border); padding:1rem 1.15rem; border-radius:10px;">
        <div style="display:flex; justify-content:space-between; align-items:center; color:var(--text-muted); font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">
            <span>Total Borrowed</span>
            <ion-icon name="wallet-outline" style="font-size:1.2rem; color:var(--primary);"></ion-icon>
        </div>
        <div style="font-size:1.35rem; font-weight:800; color:var(--text-heading); margin-top:0.35rem;">
            LKR {{ number_format($totalBorrowed, 2) }}
        </div>
        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.2rem;">
            {{ $totalLoansCount }} facilities ({{ $activeLoansCount }} active)
        </div>
    </div>

    <!-- 2. Total Paid To Date -->
    <div class="metric-card" style="background:var(--bg-card); border:1px solid var(--border); padding:1rem 1.15rem; border-radius:10px; display:flex; flex-direction:column; justify-content:space-between;">
        <div>
            <div style="display:flex; justify-content:space-between; align-items:center; color:var(--text-muted); font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">
                <span>Total Paid To Date</span>
                <ion-icon name="checkmark-circle-outline" style="font-size:1.2rem; color:var(--success);"></ion-icon>
            </div>
            <div style="font-size:1.35rem; font-weight:800; color:var(--success); margin-top:0.35rem;">
                LKR {{ number_format($totalPaidAll, 2) }}
            </div>
        </div>
        <div style="margin-top:0.5rem; padding-top:0.35rem; border-top:1px dashed var(--border-light); font-size:0.75rem; display:flex; justify-content:space-between; gap:0.5rem; flex-wrap:wrap;">
            <span style="color:var(--text-muted);">
                <strong style="color:var(--text-heading);">Loan:</strong> {{ number_format($totalPrincipalRepaid, 2) }}
            </span>
            <span style="color:var(--text-muted);">
                <strong style="color:var(--primary);">Interest:</strong> {{ number_format($totalInterestPaid, 2) }}
            </span>
        </div>
    </div>

    <!-- 3. Outstanding Debt Obligation -->
    <div class="metric-card" style="background:var(--bg-card); border:1px solid var(--border); padding:1rem 1.15rem; border-radius:10px; display:flex; flex-direction:column; justify-content:space-between;">
        <div>
            <div style="display:flex; justify-content:space-between; align-items:center; color:var(--text-muted); font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">
                <span>Outstanding Balance</span>
                <ion-icon name="alert-circle-outline" style="font-size:1.2rem; color:var(--danger);"></ion-icon>
            </div>
            <div style="font-size:1.35rem; font-weight:800; color:var(--danger); margin-top:0.35rem;">
                LKR {{ number_format($totalWantToPaid, 2) }}
            </div>
        </div>
        <div style="margin-top:0.5rem; padding-top:0.35rem; border-top:1px dashed var(--border-light); font-size:0.75rem; display:flex; justify-content:space-between; gap:0.5rem; flex-wrap:wrap;">
            <span style="color:var(--text-muted);">
                <strong style="color:var(--danger);">Loan:</strong> {{ number_format($totalOutstandingPrincipal, 2) }}
            </span>
            <span style="color:var(--text-muted);">
                <strong style="color:#f59e0b;">Interest:</strong> {{ number_format($totalOutstandingInterest, 2) }}
            </span>
        </div>
    </div>

    <!-- 4. This Month Due -->
    <div class="metric-card" style="background:var(--bg-card); border:1px solid var(--border); padding:1rem 1.15rem; border-radius:10px;">
        <div style="display:flex; justify-content:space-between; align-items:center; color:var(--text-muted); font-size:0.75rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">
            <span>This Month Due</span>
            <ion-icon name="calendar-outline" style="font-size:1.2rem; color:#f59e0b;"></ion-icon>
        </div>
        <div style="font-size:1.35rem; font-weight:800; color:var(--text-heading); margin-top:0.35rem;">
            LKR {{ number_format($thisMonthPayable, 2) }}
        </div>
        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:0.2rem;">
            Interest payable this month
        </div>
    </div>
</div>

<!-- Status Filter Tabs & Advanced Filter Toolbar -->
<div class="card" style="padding:1rem 1.25rem; margin-bottom:1.25rem; border:1px solid var(--border); border-radius:10px; background:var(--bg-card);">
    @php 
        $currStatus = request('status', 'all'); 
        $queryParams = request()->except('status');
    @endphp

    <!-- Top Row: Quick Status Tabs -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; margin-bottom:1rem; padding-bottom:0.85rem; border-bottom:1px solid var(--border-light);">
        <div style="display:flex; gap:0.4rem; align-items:center; flex-wrap:wrap;">
            <a href="/loans?{{ http_build_query(array_merge($queryParams, ['status' => 'all'])) }}" class="loan-filter-tab {{ $currStatus === 'all' ? 'active' : '' }}">
                All <span class="tab-badge">{{ $totalLoansCount }}</span>
            </a>
            <a href="/loans?{{ http_build_query(array_merge($queryParams, ['status' => 'active'])) }}" class="loan-filter-tab {{ $currStatus === 'active' ? 'active' : '' }}">
                Active <span class="tab-badge">{{ $activeLoansCount }}</span>
            </a>
            <a href="/loans?{{ http_build_query(array_merge($queryParams, ['status' => 'pending'])) }}" class="loan-filter-tab {{ $currStatus === 'pending' ? 'active' : '' }}">
                Pending
            </a>
            <a href="/loans?{{ http_build_query(array_merge($queryParams, ['status' => 'settled'])) }}" class="loan-filter-tab {{ $currStatus === 'settled' ? 'active' : '' }}">
                Settled <span class="tab-badge">{{ $settledLoansCount }}</span>
            </a>
            <a href="/loans?{{ http_build_query(array_merge($queryParams, ['status' => 'closed'])) }}" class="loan-filter-tab {{ $currStatus === 'closed' ? 'active' : '' }}">
                Closed
            </a>
        </div>
        
        @if(request('party_id') || request('start_date') || request('end_date') || request('from') || request('to') || request('search') || (request('status') && request('status') !== 'all'))
            <a href="/loans" class="btn btn-outline" style="color:var(--danger); border-color:var(--border); padding:0.35rem 0.75rem; font-size:0.8rem; text-decoration:none; display:inline-flex; align-items:center; gap:0.3rem; border-radius:6px;">
                <ion-icon name="close-circle-outline"></ion-icon> Clear All Filters
            </a>
        @endif
    </div>

    <!-- Bottom Row: Party Selector, Date Between Range, Search & Filter -->
    <form method="GET" action="/loans" style="display:flex; gap:0.85rem; align-items:flex-end; flex-wrap:wrap; margin:0;">
        <input type="hidden" name="status" value="{{ $currStatus }}">

        <!-- Party Filter (Using Party Selector Component) -->
        <div style="flex:1; min-width:220px; max-width:300px;">
            <label style="font-size:0.75rem; font-weight:700; color:var(--text-muted); margin-bottom:0.35rem; display:block; text-transform:uppercase; letter-spacing:0.5px;">Party / Lender</label>
            <x-party-selector name="party_id" :parties="$parties" :selected="request('party_id')" placeholder="All Parties / Lenders" />
        </div>

        <!-- Date Between (From & To) -->
        <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
            <div>
                <label style="font-size:0.75rem; font-weight:700; color:var(--text-muted); margin-bottom:0.35rem; display:block; text-transform:uppercase; letter-spacing:0.5px;">From Date</label>
                <input type="date" name="start_date" class="form-control" value="{{ request('start_date', request('from')) }}" style="font-size:0.82rem; padding:0.45rem 0.65rem; height:42px; border-radius:8px;">
            </div>
            <div>
                <label style="font-size:0.75rem; font-weight:700; color:var(--text-muted); margin-bottom:0.35rem; display:block; text-transform:uppercase; letter-spacing:0.5px;">To Date</label>
                <input type="date" name="end_date" class="form-control" value="{{ request('end_date', request('to')) }}" style="font-size:0.82rem; padding:0.45rem 0.65rem; height:42px; border-radius:8px;">
            </div>
        </div>

        <!-- Keyword Search -->
        <div style="flex:1; min-width:180px;">
            <label style="font-size:0.75rem; font-weight:700; color:var(--text-muted); margin-bottom:0.35rem; display:block; text-transform:uppercase; letter-spacing:0.5px;">Search</label>
            <input type="text" name="search" class="form-control" placeholder="Search lender / purpose..." value="{{ request('search') }}" style="font-size:0.82rem; padding:0.45rem 0.85rem; height:42px; border-radius:8px;">
        </div>

        <!-- Action Buttons -->
        <div style="display:flex; gap:0.4rem;">
            <button class="btn btn-primary-gradient" type="submit" style="padding:0.45rem 1.15rem; font-size:0.85rem; height:42px; border-radius:8px; display:inline-flex; align-items:center; gap:0.35rem;">
                <ion-icon name="funnel-outline"></ion-icon> Apply Filter
            </button>
        </div>
    </form>
</div>

<!-- Accordion Loans List -->
<div class="loans-accordion-container" id="loansAccordionContainer">
    @forelse($loans as $loan)
    @php
        $principalRepaidPct = $loan->principal_amount > 0 ? min(100, round(($loan->principal_repaid / $loan->principal_amount) * 100)) : 0;
        $isOverdue = ($loan->next_due_date !== 'N/A' && \Carbon\Carbon::parse($loan->next_due_date)->isPast() && $loan->status === 'active');
    @endphp
    <div class="loan-accordion-item" id="loan-item-{{ $loan->id }}">
        <!-- Minimal Summary Row (Primary Collapsed View) -->
        <div class="loan-summary-row" onclick="toggleLoanAccordion({{ $loan->id }})">
            <!-- 1. Lender Info & Purpose -->
            <div class="loan-summary-main">
                <div style="width:36px; height:36px; border-radius:8px; background:var(--primary-light); color:var(--primary); display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:1.15rem;">
                    <ion-icon name="business-outline"></ion-icon>
                </div>
                <div>
                    <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                        <a href="/loans/{{ $loan->id }}" onclick="event.stopPropagation();" style="font-weight:700; color:var(--text-heading); font-size:0.95rem; text-decoration:none;" class="hover-underline">
                            {{ $loan->lender_name }}
                        </a>
                        <!-- Status Badge -->
                        @if($loan->status === 'pending')
                            <span class="badge" style="background:#fef3c7; color:#b45309; font-size:0.7rem; padding:0.15rem 0.5rem;">Pending</span>
                        @elseif($loan->status === 'active')
                            <span class="badge" style="background:var(--primary-light); color:var(--primary); font-size:0.7rem; padding:0.15rem 0.5rem;">Active</span>
                        @elseif($loan->status === 'settled')
                            <span class="badge" style="background:#dcfce7; color:#166534; font-size:0.7rem; padding:0.15rem 0.5rem;">Settled</span>
                        @elseif($loan->status === 'closed')
                            <span class="badge" style="background:#f1f5f9; color:#475569; font-size:0.7rem; padding:0.15rem 0.5rem;">Closed</span>
                        @endif
                    </div>
                    <div style="font-size:0.78rem; color:var(--text-muted); margin-top:0.15rem;">
                        {{ strip_tags($loan->purpose) ?: 'General Borrowing Facility' }} &bull; Claimed {{ $loan->claimed_date ? date('M d, Y', strtotime($loan->claimed_date)) : 'N/A' }}
                    </div>
                </div>
            </div>

            <!-- 2. Minimal High-Level Numbers -->
            <div class="loan-summary-metrics">
                <!-- Principal -->
                <div style="text-align:right;">
                    <div style="font-size:0.72rem; color:var(--text-muted); text-transform:uppercase; font-weight:600;">Principal</div>
                    <div style="font-weight:700; font-size:0.88rem; color:var(--text-heading);">
                        {{ $loan->currency }} {{ number_format($loan->principal_amount, 2) }}
                    </div>
                </div>

                <!-- Outstanding (P+I) -->
                <div style="text-align:right;">
                    <div style="font-size:0.72rem; color:var(--text-muted); text-transform:uppercase; font-weight:600;">Outstanding Balance</div>
                    <div style="font-weight:800; font-size:0.95rem; color: {{ $loan->total_outstanding > 0 ? 'var(--danger)' : 'var(--success)' }};">
                        {{ $loan->currency }} {{ number_format($loan->total_outstanding, 2) }}
                    </div>
                </div>

                <!-- Next Due -->
                <div style="text-align:left; min-width:90px;">
                    <div style="font-size:0.72rem; color:var(--text-muted); text-transform:uppercase; font-weight:600;">Next Due</div>
                    <div style="font-size:0.82rem; font-weight:600; color: {{ $isOverdue ? 'var(--danger)' : 'var(--text-main)' }};">
                        {{ $loan->next_due_date !== 'N/A' ? date('M d, Y', strtotime($loan->next_due_date)) : 'N/A' }}
                    </div>
                </div>
            </div>

            <!-- 3. Actions & Accordion Expander -->
            <div class="loan-summary-actions" onclick="event.stopPropagation();">
                <!-- Direct View Button -->
                <a href="/loans/{{ $loan->id }}" class="btn btn-outline" style="padding:0.35rem 0.75rem; font-size:0.8rem; font-weight:600; border-radius:6px; display:inline-flex; align-items:center; gap:0.3rem;">
                    <ion-icon name="eye-outline"></ion-icon> View
                </a>

                <!-- Quick Edit Button -->
                <button type="button" class="btn btn-outline" title="Edit Loan" onclick='openEditLoanModal(@json($loan))' style="padding:0.35rem 0.55rem; font-size:0.85rem; border-radius:6px; color:var(--primary);">
                    <ion-icon name="create-outline"></ion-icon>
                </button>

                <!-- Accordion Toggle Chevron -->
                <button type="button" class="btn btn-outline" onclick="toggleLoanAccordion({{ $loan->id }})" style="padding:0.35rem 0.55rem; font-size:0.85rem; border-radius:6px; color:var(--text-muted);" title="Toggle Details">
                    <ion-icon name="chevron-down-outline" class="loan-chevron"></ion-icon>
                </button>
            </div>
        </div>

        <!-- Accordion Expanded Drawer (Detailed breakdown revealed cleanly) -->
        <div class="loan-drawer" id="loan-drawer-{{ $loan->id }}">
            <div class="loan-drawer-grid">
                <!-- Box 1: Principal & Payment Breakdown -->
                <div class="loan-drawer-box">
                    <div class="loan-drawer-title">
                        <ion-icon name="pie-chart-outline"></ion-icon> Principal & Repayment
                    </div>
                    <div>
                        <div style="display:flex; justify-content:space-between; font-size:0.78rem; color:var(--text-muted);">
                            <span>Repayment Progress</span>
                            <span style="font-weight:700; color:var(--text-heading);">{{ $principalRepaidPct }}%</span>
                        </div>
                        <div class="loan-progress-bar-wrap">
                            <div class="loan-progress-bar-fill" style="width: {{ $principalRepaidPct }}%;"></div>
                        </div>
                    </div>
                    <div class="loan-stat-row">
                        <span class="loan-stat-label">Initial Principal</span>
                        <span class="loan-stat-val">{{ $loan->currency }} {{ number_format($loan->principal_amount, 2) }}</span>
                    </div>
                    @if(!empty($loan->is_upfront_interest ?? null))
                    <div class="loan-stat-row">
                        <span class="loan-stat-label">Net Disbursed Received</span>
                        <span class="loan-stat-val" style="color:var(--primary);">{{ $loan->currency }} {{ number_format($loan->net_disbursed, 2) }}</span>
                    </div>
                    @endif
                    <div class="loan-stat-row">
                        <span class="loan-stat-label">Principal Repaid</span>
                        <span class="loan-stat-val" style="color:var(--success);">{{ $loan->currency }} {{ number_format($loan->principal_repaid, 2) }}</span>
                    </div>
                    <div class="loan-stat-row">
                        <span class="loan-stat-label">Remaining Principal</span>
                        <span class="loan-stat-val" style="color:var(--danger);">{{ $loan->currency }} {{ number_format($loan->outstanding_principal, 2) }}</span>
                    </div>
                </div>

                <!-- Box 2: Interest & Scheduled Obligations -->
                <div class="loan-drawer-box">
                    <div class="loan-drawer-title">
                        <ion-icon name="cash-outline"></ion-icon> Interest & Obligations
                    </div>
                    <div class="loan-stat-row">
                        <span class="loan-stat-label">Interest Method</span>
                        <span class="loan-stat-val">
                            @if($loan->interest_method === 'fixed_amount')
                                Fixed: {{ $loan->currency }} {{ number_format($loan->interest_amount, 2) }}
                            @elseif($loan->interest_method === 'percentage_rate')
                                {{ $loan->interest_rate }}% ({{ ucfirst($loan->rate_basis ?? 'flat') }})
                            @elseif($loan->interest_method === 'equal_installments')
                                Equal Installments
                            @elseif($loan->interest_method === 'custom_schedule')
                                Custom Schedule
                            @else
                                No Interest
                            @endif
                        </span>
                    </div>
                    <div class="loan-stat-row">
                        <span class="loan-stat-label">Payment Frequency</span>
                        <span class="loan-stat-val">{{ ucfirst($loan->frequency ?? 'monthly') }}</span>
                    </div>
                    <div class="loan-stat-row">
                        <span class="loan-stat-label">Interest Paid</span>
                        <span class="loan-stat-val" style="color:var(--success);">{{ $loan->currency }} {{ number_format($loan->interest_paid, 2) }}</span>
                    </div>
                    <div class="loan-stat-row">
                        <span class="loan-stat-label">Pending / Scheduled Interest</span>
                        <span class="loan-stat-val" style="color:var(--warning);">{{ $loan->currency }} {{ number_format($loan->pending_interest, 2) }}</span>
                    </div>
                    <div class="loan-stat-row">
                        <span class="loan-stat-label">Total Paid (P + I)</span>
                        <span class="loan-stat-val" style="color:var(--success); font-weight:700;">{{ $loan->currency }} {{ number_format($loan->total_paid, 2) }}</span>
                    </div>
                </div>

                <!-- Box 3: Dates, Terms & Security Details -->
                <div class="loan-drawer-box">
                    <div class="loan-drawer-title">
                        <ion-icon name="document-text-outline"></ion-icon> Terms & Security
                    </div>
                    <div class="loan-stat-row">
                        <span class="loan-stat-label">Start / Claimed Date</span>
                        <span class="loan-stat-val">{{ $loan->claimed_date ?: ($loan->start_date ?: 'N/A') }}</span>
                    </div>
                    <div class="loan-stat-row">
                        <span class="loan-stat-label">Term & Maturity</span>
                        <span class="loan-stat-val">{{ $loan->term_months }} mo @if($loan->maturity_date) &bull; Due {{ $loan->maturity_date }} @endif</span>
                    </div>
                    <div class="loan-stat-row">
                        <span class="loan-stat-label">Due Day of Month</span>
                        <span class="loan-stat-val">{{ $loan->due_day ? 'Day '.$loan->due_day : 'N/A' }}</span>
                    </div>
                    <div class="loan-stat-row">
                        <span class="loan-stat-label">Guarantor</span>
                        <span class="loan-stat-val">{{ $loan->guarantor ?: 'None specified' }}</span>
                    </div>
                    <div class="loan-stat-row">
                        <span class="loan-stat-label">Collateral / Security</span>
                        <span class="loan-stat-val">{{ $loan->collateral ?: 'None' }}</span>
                    </div>
                </div>
            </div>

            @if(!empty($loan->purpose))
            <!-- Purpose & Facility Terms Notes -->
            <div style="background:var(--bg-page); border:1px solid var(--border-light); border-radius:8px; padding:0.85rem 1.15rem; margin-bottom:1rem;">
                <span style="font-size:0.72rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.5px; display:block; margin-bottom:0.35rem;">
                    <ion-icon name="document-text-outline" style="vertical-align:middle;"></ion-icon> Purpose & Facility Terms
                </span>
                <div class="prose" style="font-size:0.85rem; color:var(--text-main); line-height:1.6;">
                    {!! $loan->purpose !!}
                </div>
            </div>
            @endif

            <!-- Drawer Bottom Action Toolbar -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; padding-top:0.75rem; border-top:1px solid var(--border-light);">
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <a href="/loans/{{ $loan->id }}" class="btn btn-primary-gradient" style="font-size:0.82rem; padding:0.4rem 1rem; border-radius:6px; font-weight:600; display:inline-flex; align-items:center; gap:0.35rem;">
                        <ion-icon name="open-outline"></ion-icon> View Full Loan Workspace & Schedules
                    </a>
                </div>

                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <button type="button" class="btn btn-outline" onclick='openEditLoanModal(@json($loan))' style="font-size:0.8rem; padding:0.35rem 0.75rem; border-radius:6px;">
                        <ion-icon name="create-outline" style="vertical-align:middle;"></ion-icon> Edit Details
                    </button>
                    <button type="button" class="btn btn-outline" onclick="openChangeStatusModal({{ $loan->id }}, '{{ $loan->status }}')" style="font-size:0.8rem; padding:0.35rem 0.75rem; border-radius:6px;">
                        <ion-icon name="swap-horizontal-outline" style="vertical-align:middle;"></ion-icon> Status ({{ ucfirst($loan->status) }})
                    </button>
                    <form id="delete_loan_{{ $loan->id }}" action="/loans/{{ $loan->id }}" method="POST" style="display:inline; margin:0;">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-outline" onclick="return confirmAction({title:'Delete Loan?', message:'Delete loan from {{ addslashes($loan->lender_name) }} and all associated schedules?', confirmText:'Delete Loan', formId:'delete_loan_{{ $loan->id }}'})" style="font-size:0.8rem; padding:0.35rem 0.75rem; border-radius:6px; color:var(--danger); border-color:var(--danger);">
                            <ion-icon name="trash-outline" style="vertical-align:middle;"></ion-icon> Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @empty
    <x-empty-state 
        icon="card-outline" 
        title="No Loans Found" 
        description="Track third-party borrowing facilities, interest payment schedules, deductions, and settlements." 
        actionModal="createLoanModal" 
        actionText="Record New Loan" 
    />
    @endforelse
</div>

@if(!$loans->isEmpty())
<div style="margin-top:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
    <div style="font-size:0.85rem; color:var(--text-muted);">
        Showing {{ $loans->firstItem() ?? 0 }} to {{ $loans->lastItem() ?? 0 }} of {{ $loans->total() }} loans
    </div>
    <div>
        {{ $loans->links() }}
    </div>
</div>
@endif
@endsection

@section('modals')
<!-- Change Status Modal -->
<div class="modal-backdrop" id="changeStatusModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Change Loan Status</h3>
            <button type="button" class="btn-close" onclick="closeModal('changeStatusModal')">&times;</button>
        </div>
        <form id="changeStatusForm" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">New Status</label>
                    <select name="status" id="modal_status" class="form-control" required>
                        <option value="pending">Pending</option>
                        <option value="active">Active</option>
                        <option value="closed">Closed</option>
                        <option value="settled">Settled</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('changeStatusModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Save Status</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Loan Modal -->
<div class="modal-backdrop" id="editLoanModal">
    <div class="modal-card" style="max-width:720px;">
        <div class="modal-header">
            <h3 class="modal-title">Edit Loan Details</h3>
            <button type="button" class="btn-close" onclick="closeModal('editLoanModal')">&times;</button>
        </div>
        <form id="editLoanForm" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;">Select Party / Lender</label>
                        <select name="party_id" id="edit_party_id" class="form-control">
                            <option value="">-- None / Custom Lender --</option>
                            @foreach($parties as $p)
                                <option value="{{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;">Lender Name / Entity *</label>
                        <input type="text" name="lender_name" id="edit_lender_name" class="form-control" required>
                    </div>
                </div>

                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col">
                        <label class="form-label">Principal Amount *</label>
                        <x-amount-input name="principal_amount" id="edit_principal_amount" required="true" />
                    </div>
                    <div class="form-col">
                        <label class="form-label">Currency</label>
                        <x-currency-selector name="currency" id="edit_currency" selected="LKR" required />
                    </div>
                </div>

                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col">
                        <label class="form-label">Claimed / Start Date *</label>
                        <input type="date" name="claimed_date" id="edit_claimed_date" class="form-control" required>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Term (Months) *</label>
                        <input type="number" name="term_months" id="edit_term_months" class="form-control" min="1" required>
                    </div>
                </div>

                <div class="form-group" style="margin-top:1.25rem;">
                    <label class="form-label" style="font-weight:700;">Purpose / Description & Terms</label>
                    <textarea name="purpose" id="edit_purpose" class="form-control" rows="3" placeholder="E.g. Capital investment / Equipment purchase / terms..."></textarea>
                </div>

                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col">
                        <label class="form-label">Interest Calculation Method</label>
                        <select name="interest_method" id="edit_interest_method" class="form-control" onchange="toggleEditInterestFields(this.value)" required>
                            <option value="fixed_amount">Fixed Amount per Period</option>
                            <option value="percentage_rate">Percentage Rate (%)</option>
                            <option value="equal_installments">Equal Installments</option>
                            <option value="custom_schedule">Custom Schedule</option>
                            <option value="no_interest">No Interest</option>
                        </select>
                    </div>
                    <div class="form-col" id="edit_field_frequency">
                        <label class="form-label">Payment Frequency</label>
                        <select name="frequency" id="edit_frequency" class="form-control">
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                        </select>
                    </div>
                </div>

                <!-- Method Dynamic Fields -->
                <div id="edit_field_fixed_amount" class="form-group" style="margin-top:1.25rem;">
                    <label class="form-label">Fixed Interest Amount per Period</label>
                    <x-amount-input name="interest_amount" id="edit_interest_amount" />
                </div>

                <div id="edit_field_percentage_rate" class="form-row" style="margin-top:1.25rem; display:none;">
                    <div class="form-col">
                        <label class="form-label">Interest Rate (%)</label>
                        <input type="number" step="0.01" name="interest_rate" id="edit_interest_rate" class="form-control" placeholder="E.g. 10.5">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Rate Basis</label>
                        <select name="rate_basis" id="edit_rate_basis" class="form-control">
                            <option value="flat">Flat Interest Rate</option>
                            <option value="reducing">Reducing Balance</option>
                        </select>
                    </div>
                </div>

                <div id="edit_field_equal_installments" class="form-group" style="margin-top:1.25rem; display:none;">
                    <label class="form-label">Total Agreed Interest Amount</label>
                    <x-amount-input name="total_interest" id="edit_total_interest" />
                </div>

                <!-- Upfront Interest Deduction Feature -->
                <div id="edit_upfront_interest_card" class="glass-card" style="margin-top:1.25rem; padding:1rem; border-radius:10px; background:var(--bg-page); border:1px solid var(--border-light);">
                    <label style="display:flex; align-items:center; gap:0.6rem; cursor:pointer; font-weight:600; color:var(--text-heading); font-size:0.9rem; margin-bottom:0.35rem;">
                        <input type="checkbox" name="is_upfront_interest" id="edit_is_upfront_interest" value="1" onchange="toggleUpfrontInterest('edit')" style="width:1.15rem; height:1.15rem; accent-color:var(--primary);">
                        <span>Deduct Interest Upfront (Paid on Claimed Date)</span>
                    </label>
                    <p class="text-muted" style="margin:0 0 0.5rem 1.75rem; font-size:0.8rem;">
                        Check if interest is paid immediately when loan is taken (e.g. Receive 42,500 for a 45,000 loan with 2,500 interest paid upfront on Day 1).
                    </p>
                    <div id="edit_upfront_interest_container" style="display:none; margin-left:1.75rem; margin-top:0.5rem;">
                        <div class="form-group" style="margin-bottom:0.5rem;">
                            <label class="form-label" style="font-size:0.85rem;">Upfront Interest Amount (Leave empty to use 1st period interest)</label>
                            <x-amount-input name="upfront_interest_amount" id="edit_upfront_interest_amount" placeholder="Auto-calculated from period interest" />
                        </div>
                        <div id="edit_upfront_summary" style="font-size:0.82rem; color:var(--primary); font-weight:600; background:var(--primary-light); padding:0.4rem 0.75rem; border-radius:6px; display:inline-block;">
                            💡 Net Cash Received: <span id="edit_net_disbursed_label">-</span>
                        </div>
                    </div>
                </div>

                <!-- Loan Maturity / Full Principal Due Date & Reminders -->
                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col">
                        <label class="form-label" style="font-weight:600;">Loan Full Principal Due Date (Optional)</label>
                        <input type="date" name="maturity_date" id="edit_maturity_date" class="form-control" onchange="autoSetTermFromMaturity('edit')">
                        <div style="display:flex; gap:0.3rem; margin-top:0.35rem; flex-wrap:wrap;">
                            <button type="button" class="btn btn-outline" style="font-size:0.72rem; padding:0.15rem 0.45rem;" onclick="setMaturityMonths('edit', 1)">+1 Mo</button>
                            <button type="button" class="btn btn-outline" style="font-size:0.72rem; padding:0.15rem 0.45rem;" onclick="setMaturityMonths('edit', 2)">+2 Mo</button>
                            <button type="button" class="btn btn-outline" style="font-size:0.72rem; padding:0.15rem 0.45rem;" onclick="setMaturityMonths('edit', 3)">+3 Mo</button>
                            <button type="button" class="btn btn-outline" style="font-size:0.72rem; padding:0.15rem 0.45rem;" onclick="setMaturityMonths('edit', 6)">+6 Mo</button>
                            <button type="button" class="btn btn-outline" style="font-size:0.72rem; padding:0.15rem 0.45rem;" onclick="setMaturityMonths('edit', 12)">+1 Yr</button>
                            <button type="button" class="btn btn-outline" style="font-size:0.72rem; padding:0.15rem 0.45rem; color:var(--text-muted);" onclick="document.getElementById('edit_maturity_date').value=''">Clear</button>
                        </div>
                    </div>
                    <div class="form-col">
                        <label class="form-label" style="font-weight:600;">Reminder Lead Time</label>
                        <select name="reminder_days" id="edit_reminder_days" class="form-control">
                            <option value="1">1 day before due date</option>
                            <option value="2">2 days before due date</option>
                            <option value="3" selected>3 days before due date</option>
                            <option value="5">5 days before due date</option>
                            <option value="7">1 week before due date</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col" id="edit_field_due_day">
                        <label class="form-label">Due Day of Month</label>
                        <input type="number" name="due_day" id="edit_due_day" class="form-control" min="1" max="31">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Guarantor (Optional)</label>
                        <input type="text" name="guarantor" id="edit_guarantor" class="form-control" placeholder="Guarantor name / contact">
                    </div>
                </div>

                <div class="form-group" style="margin-top:1.25rem;">
                    <label class="form-label">Collateral / Security (Optional)</label>
                    <textarea name="collateral" id="edit_collateral" class="form-control" rows="2" placeholder="Pledged assets or guarantees"></textarea>
                </div>

                <div class="form-group" style="margin-top:1.25rem;">
                    <label class="form-label">Add More Attachments</label>
                    <input type="file" name="attachments[]" class="form-control" multiple>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editLoanModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Update Loan Details</button>
            </div>
        </form>
    </div>
</div>

<!-- Create Loan Modal -->
<div class="modal-backdrop" id="createLoanModal">
    <div class="modal-card" style="max-width:700px;">
        <div class="modal-header">
            <h3 class="modal-title">Record New Third-Party Loan</h3>
            <button type="button" class="btn-close" onclick="closeModal('createLoanModal')">&times;</button>
        </div>
        <form action="/loans" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;">Select Party / Lender</label>
                        <x-party-selector name="party_id" :parties="$parties" placeholder="Search & select party..." />
                    </div>
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;">Lender Name / Entity *</label>
                        <input type="text" name="lender_name" class="form-control" required placeholder="E.g. Commercial Bank / Director">
                    </div>
                </div>

                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col">
                        <label class="form-label">Principal Amount *</label>
                        <x-amount-input name="principal_amount" id="create_principal_amount" required="true" />
                    </div>
                    <div class="form-col">
                        <label class="form-label">Currency</label>
                        <x-currency-selector name="currency" id="create_currency" selected="LKR" required />
                    </div>
                </div>

                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col">
                        <label class="form-label">Claimed / Start Date *</label>
                        <input type="date" name="claimed_date" id="create_claimed_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Term (Months) *</label>
                        <input type="number" name="term_months" id="create_term_months" class="form-control" value="1" min="1" required>
                    </div>
                </div>

                <div class="form-group" style="margin-top:1.25rem;">
                    <label class="form-label" style="font-weight:700;">Purpose / Description & Terms</label>
                    <textarea name="purpose" id="create_purpose" class="form-control" rows="3" placeholder="E.g. Capital investment / Equipment purchase / terms..."></textarea>
                </div>

                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col">
                        <label class="form-label">Interest Calculation Method</label>
                        <select name="interest_method" id="create_interest_method" class="form-control" onchange="toggleInterestFields(this.value); calculateNetDisbursed('create');" required>
                            <option value="fixed_amount">Fixed Amount per Period</option>
                            <option value="percentage_rate">Percentage Rate (%)</option>
                            <option value="equal_installments">Equal Installments</option>
                            <option value="custom_schedule">Custom Schedule</option>
                            <option value="no_interest">No Interest</option>
                        </select>
                    </div>
                    <div class="form-col" id="field_frequency_col">
                        <label class="form-label">Payment Frequency</label>
                        <select name="frequency" id="create_frequency" class="form-control">
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                        </select>
                    </div>
                </div>

                <!-- Method Dynamic Fields -->
                <div id="field_fixed_amount" class="form-group" style="margin-top:1.25rem;">
                    <label class="form-label">Fixed Interest Amount per Period</label>
                    <x-amount-input name="interest_amount" id="create_interest_amount" />
                </div>

                <div id="field_percentage_rate" class="form-row" style="margin-top:1.25rem; display:none;">
                    <div class="form-col">
                        <label class="form-label">Interest Rate (%)</label>
                        <input type="number" step="0.01" name="interest_rate" id="create_interest_rate" class="form-control" placeholder="E.g. 10.5" oninput="calculateNetDisbursed('create')">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Rate Basis</label>
                        <select name="rate_basis" id="create_rate_basis" class="form-control">
                            <option value="flat">Flat Interest Rate</option>
                            <option value="reducing">Reducing Balance</option>
                        </select>
                    </div>
                </div>

                <div id="field_equal_installments" class="form-group" style="margin-top:1.25rem; display:none;">
                    <label class="form-label">Total Agreed Interest Amount</label>
                    <x-amount-input name="total_interest" id="create_total_interest" />
                </div>

                <!-- Upfront Interest Deduction Feature -->
                <div id="create_upfront_interest_card" class="glass-card" style="margin-top:1.25rem; padding:1rem; border-radius:10px; background:var(--bg-page); border:1px solid var(--border-light);">
                    <label style="display:flex; align-items:center; gap:0.6rem; cursor:pointer; font-weight:600; color:var(--text-heading); font-size:0.9rem; margin-bottom:0.35rem;">
                        <input type="checkbox" name="is_upfront_interest" id="create_is_upfront_interest" value="1" onchange="toggleUpfrontInterest('create')" style="width:1.15rem; height:1.15rem; accent-color:var(--primary);">
                        <span>Deduct Interest Upfront (Paid on Claimed Date)</span>
                    </label>
                    <p class="text-muted" style="margin:0 0 0.5rem 1.75rem; font-size:0.8rem;">
                        Check if interest is paid immediately when loan is taken (e.g. Receive 42,500 for a 45,000 loan with 2,500 interest paid upfront on Day 1).
                    </p>
                    <div id="create_upfront_interest_container" style="display:none; margin-left:1.75rem; margin-top:0.5rem;">
                        <div class="form-group" style="margin-bottom:0.5rem;">
                            <label class="form-label" style="font-size:0.85rem;">Upfront Interest Amount (Leave empty to use 1st period interest)</label>
                            <x-amount-input name="upfront_interest_amount" id="create_upfront_interest_amount" placeholder="Auto-calculated from period interest" />
                        </div>
                        <div id="create_upfront_summary" style="font-size:0.82rem; color:var(--primary); font-weight:600; background:var(--primary-light); padding:0.4rem 0.75rem; border-radius:6px; display:inline-block;">
                            💡 Net Cash Received: <span id="create_net_disbursed_label">-</span>
                        </div>
                    </div>
                </div>

                <!-- Loan Maturity / Full Principal Due Date & Reminders -->
                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col">
                        <label class="form-label" style="font-weight:600;">Loan Full Principal Due Date (Optional)</label>
                        <input type="date" name="maturity_date" id="create_maturity_date" class="form-control" onchange="autoSetTermFromMaturity('create')">
                        <div style="display:flex; gap:0.3rem; margin-top:0.35rem; flex-wrap:wrap;">
                            <button type="button" class="btn btn-outline" style="font-size:0.72rem; padding:0.15rem 0.45rem;" onclick="setMaturityMonths('create', 1)">+1 Mo</button>
                            <button type="button" class="btn btn-outline" style="font-size:0.72rem; padding:0.15rem 0.45rem;" onclick="setMaturityMonths('create', 2)">+2 Mo</button>
                            <button type="button" class="btn btn-outline" style="font-size:0.72rem; padding:0.15rem 0.45rem;" onclick="setMaturityMonths('create', 3)">+3 Mo</button>
                            <button type="button" class="btn btn-outline" style="font-size:0.72rem; padding:0.15rem 0.45rem;" onclick="setMaturityMonths('create', 6)">+6 Mo</button>
                            <button type="button" class="btn btn-outline" style="font-size:0.72rem; padding:0.15rem 0.45rem;" onclick="setMaturityMonths('create', 12)">+1 Yr</button>
                            <button type="button" class="btn btn-outline" style="font-size:0.72rem; padding:0.15rem 0.45rem; color:var(--text-muted);" onclick="document.getElementById('create_maturity_date').value=''">Clear</button>
                        </div>
                    </div>
                    <div class="form-col">
                        <label class="form-label" style="font-weight:600;">Reminder Lead Time</label>
                        <select name="reminder_days" id="create_reminder_days" class="form-control">
                            <option value="1">1 day before due date</option>
                            <option value="2">2 days before due date</option>
                            <option value="3" selected>3 days before due date</option>
                            <option value="5">5 days before due date</option>
                            <option value="7">1 week before due date</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col" id="field_due_day">
                        <label class="form-label">Due Day of Month (Optional)</label>
                        <input type="number" name="due_day" id="create_due_day" class="form-control" placeholder="Dynamic (e.g. Day from Claim Date)" min="1" max="31">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Guarantor (Optional)</label>
                        <input type="text" name="guarantor" class="form-control" placeholder="Guarantor name / contact">
                    </div>
                </div>

                <div class="form-group" style="margin-top:1.25rem;">
                    <label class="form-label">Collateral / Security (Optional)</label>
                    <textarea name="collateral" class="form-control" rows="2" placeholder="Pledged assets or guarantees"></textarea>
                </div>

                <div class="form-group" style="margin-top:1.25rem;">
                    <label class="form-label">Attachments (Optional)</label>
                    <input type="file" name="attachments[]" class="form-control" multiple>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('createLoanModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Save & Record Loan</button>
            </div>
        </form>
    </div>
</div>

<script>
// Accordion Toggle Functions
function toggleLoanAccordion(loanId) {
    const item = document.getElementById('loan-item-' + loanId);
    if (!item) return;
    item.classList.toggle('expanded');
}

let allExpanded = false;
function toggleAllAccordions() {
    allExpanded = !allExpanded;
    const items = document.querySelectorAll('.loan-accordion-item');
    items.forEach(item => {
        if (allExpanded) {
            item.classList.add('expanded');
        } else {
            item.classList.remove('expanded');
        }
    });
    
    const toggleText = document.getElementById('toggleAllText');
    if (toggleText) {
        toggleText.textContent = allExpanded ? 'Collapse All' : 'Expand All';
    }
}

function openChangeStatusModal(id, currentStatus) {
    document.getElementById('changeStatusForm').action = '/loans/' + id + '/status';
    document.getElementById('modal_status').value = currentStatus;
    openModal('changeStatusModal');
}

function toggleInterestFields(method) {
    document.getElementById('field_fixed_amount').style.display = method === 'fixed_amount' ? 'block' : 'none';
    document.getElementById('field_percentage_rate').style.display = method === 'percentage_rate' ? 'flex' : 'none';
    document.getElementById('field_equal_installments').style.display = method === 'equal_installments' ? 'block' : 'none';
    document.getElementById('field_frequency_col').style.display = (method === 'no_interest' || method === 'custom_schedule') ? 'none' : 'block';
    
    const upfrontCard = document.getElementById('create_upfront_interest_card');
    if (upfrontCard) {
        upfrontCard.style.display = (method === 'no_interest') ? 'none' : 'block';
        if (method === 'no_interest') {
            document.getElementById('create_is_upfront_interest').checked = false;
            const upCont = document.getElementById('create_upfront_interest_container');
            if (upCont) upCont.style.display = 'none';
        }
    }
}

function toggleEditInterestFields(method) {
    document.getElementById('edit_field_fixed_amount').style.display = method === 'fixed_amount' ? 'block' : 'none';
    document.getElementById('edit_field_percentage_rate').style.display = method === 'percentage_rate' ? 'flex' : 'none';
    document.getElementById('edit_field_equal_installments').style.display = method === 'equal_installments' ? 'block' : 'none';
    const hasFrequency = (method !== 'no_interest' && method !== 'custom_schedule');
    document.getElementById('edit_field_frequency').style.display = hasFrequency ? 'block' : 'none';
    document.getElementById('edit_field_due_day').style.display = hasFrequency ? 'block' : 'none';
    
    const upfrontCard = document.getElementById('edit_upfront_interest_card');
    if (upfrontCard) {
        upfrontCard.style.display = (method === 'no_interest') ? 'none' : 'block';
        if (method === 'no_interest') {
            document.getElementById('edit_is_upfront_interest').checked = false;
            const upCont = document.getElementById('edit_upfront_interest_container');
            if (upCont) upCont.style.display = 'none';
        }
    }
}

function toggleUpfrontInterest(prefix) {
    const isChecked = document.getElementById(prefix + '_is_upfront_interest').checked;
    const container = document.getElementById(prefix + '_upfront_interest_container');
    if (container) container.style.display = isChecked ? 'block' : 'none';
    calculateNetDisbursed(prefix);
}

function calculateNetDisbursed(prefix) {
    const principalInput = document.getElementById(prefix + '_principal_amount');
    const principalHidden = principalInput ? (principalInput.parentElement.querySelector('.amount-hidden') || principalInput) : null;
    const principal = parseFloat(principalHidden ? principalHidden.value : 0) || 0;

    const upfrontInput = document.getElementById(prefix + '_upfront_interest_amount');
    const upfrontHidden = upfrontInput ? (upfrontInput.parentElement.querySelector('.amount-hidden') || upfrontInput) : null;
    let upfront = parseFloat(upfrontHidden ? upfrontHidden.value : 0) || 0;

    if (upfront === 0) {
        const method = document.getElementById(prefix + '_interest_method').value;
        if (method === 'fixed_amount') {
            const intInput = document.getElementById(prefix + '_interest_amount');
            const intHidden = intInput ? (intInput.parentElement.querySelector('.amount-hidden') || intInput) : null;
            upfront = parseFloat(intHidden ? intHidden.value : 0) || 0;
        } else if (method === 'percentage_rate') {
            const rate = parseFloat(document.getElementById(prefix + '_interest_rate').value) || 0;
            upfront = principal * (rate / 100);
        } else if (method === 'equal_installments') {
            const totInput = document.getElementById(prefix + '_total_interest');
            const totHidden = totInput ? (totInput.parentElement.querySelector('.amount-hidden') || totInput) : null;
            const term = parseInt(document.getElementById(prefix + '_term_months').value) || 1;
            const tot = parseFloat(totHidden ? totHidden.value : 0) || 0;
            upfront = tot / Math.max(1, term);
        }
    }

    const net = Math.max(0, principal - upfront);
    const label = document.getElementById(prefix + '_net_disbursed_label');
    if (label) {
        label.textContent = net.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
}

function autoSetMaturity(prefix) {
    const claimedDateInput = document.getElementById(prefix + '_claimed_date');
    const termInput = document.getElementById(prefix + '_term_months');
    const maturityInput = document.getElementById(prefix + '_maturity_date');
    if (!claimedDateInput || !termInput || !maturityInput) return;

    if (claimedDateInput.value) {
        const d = new Date(claimedDateInput.value);
        const months = parseInt(termInput.value) || 1;
        d.setMonth(d.getMonth() + months);
        maturityInput.value = d.toISOString().split('T')[0];
    }
}

function autoSetTermFromMaturity(prefix) {
    const claimedDateInput = document.getElementById(prefix + '_claimed_date');
    const termInput = document.getElementById(prefix + '_term_months');
    const maturityInput = document.getElementById(prefix + '_maturity_date');
    if (!claimedDateInput || !termInput || !maturityInput) return;

    if (claimedDateInput.value && maturityInput.value) {
        const d1 = new Date(claimedDateInput.value);
        const d2 = new Date(maturityInput.value);
        const diffYears = d2.getFullYear() - d1.getFullYear();
        const diffMonths = (diffYears * 12) + (d2.getMonth() - d1.getMonth());
        termInput.value = Math.max(1, diffMonths);
    }
}

function setMaturityMonths(prefix, months) {
    const claimedDateInput = document.getElementById(prefix + '_claimed_date');
    const maturityInput = document.getElementById(prefix + '_maturity_date');
    const termInput = document.getElementById(prefix + '_term_months');
    
    let baseDate = new Date();
    if (claimedDateInput && claimedDateInput.value) {
        baseDate = new Date(claimedDateInput.value);
    }
    baseDate.setMonth(baseDate.getMonth() + months);
    if (maturityInput) {
        maturityInput.value = baseDate.toISOString().split('T')[0];
    }
    if (termInput) {
        termInput.value = months;
    }
}

function setAmountInputValue(id, val) {
    const input = document.getElementById(id);
    if (!input) return;
    const hidden = input.parentElement ? input.parentElement.querySelector('.amount-hidden') : null;
    if (val !== null && val !== undefined && val !== '' && !isNaN(val)) {
        const num = parseFloat(val);
        input.value = num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (hidden) {
            hidden.value = num.toFixed(2);
            hidden.dispatchEvent(new Event('input', { bubbles: true }));
        }
    } else {
        input.value = '';
        if (hidden) {
            hidden.value = '';
            hidden.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }
}

let createPurposeEditor = null;
let editPurposeEditor = null;

document.addEventListener('DOMContentLoaded', function() {
    // Live update Net Cash Received on create form changes
    ['create_principal_amount', 'create_interest_amount', 'create_total_interest', 'create_upfront_interest_amount'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', () => calculateNetDisbursed('create'));
            el.addEventListener('blur', () => calculateNetDisbursed('create'));
        }
    });

    // Initialize CKEditor on Create Modal Purpose
    const createEl = document.querySelector('#create_purpose');
    if (createEl && typeof ClassicEditor !== 'undefined') {
        ClassicEditor
            .create(createEl, {
                toolbar: ['heading', '|', 'bold', 'italic', 'underline', 'bulletedList', 'numberedList', 'blockQuote', '|', 'undo', 'redo']
            })
            .then(editor => {
                createPurposeEditor = editor;
            })
            .catch(err => console.error(err));
    }

    // Initialize CKEditor on Edit Modal Purpose
    const editEl = document.querySelector('#edit_purpose');
    if (editEl && typeof ClassicEditor !== 'undefined') {
        ClassicEditor
            .create(editEl, {
                toolbar: ['heading', '|', 'bold', 'italic', 'underline', 'bulletedList', 'numberedList', 'blockQuote', '|', 'undo', 'redo']
            })
            .then(editor => {
                editPurposeEditor = editor;
            })
            .catch(err => console.error(err));
    }
});

function openEditLoanModal(loan) {
    document.getElementById('editLoanForm').action = '/loans/' + loan.id;
    
    const partyEl = document.getElementById('edit_party_id');
    if (partyEl) partyEl.value = loan.party_id || '';
    
    document.getElementById('edit_lender_name').value = loan.lender_name || '';
    setAmountInputValue('edit_principal_amount', loan.principal_amount);
    
    const currEl = document.getElementById('edit_currency');
    if (currEl) currEl.value = loan.currency || 'LKR';
    
    document.getElementById('edit_claimed_date').value = loan.claimed_date || loan.start_date || '';
    document.getElementById('edit_term_months').value = loan.term_months || 1;
    
    if (editPurposeEditor) {
        editPurposeEditor.setData(loan.purpose || '');
    } else {
        document.getElementById('edit_purpose').value = loan.purpose || '';
    }
    
    const method = loan.interest_method || 'fixed_amount';
    document.getElementById('edit_interest_method').value = method;
    
    setAmountInputValue('edit_interest_amount', loan.interest_amount);
    document.getElementById('edit_interest_rate').value = loan.interest_rate || '';
    document.getElementById('edit_rate_basis').value = loan.rate_basis || 'flat';
    setAmountInputValue('edit_total_interest', loan.total_interest);
    document.getElementById('edit_frequency').value = loan.frequency || 'monthly';
    document.getElementById('edit_due_day').value = loan.due_day || (loan.claimed_date ? new Date(loan.claimed_date).getDate() : '');
    document.getElementById('edit_due_day').placeholder = 'Dynamic (Loan Claim Date)';
    document.getElementById('edit_guarantor').value = loan.guarantor || '';
    document.getElementById('edit_collateral').value = loan.collateral || '';

    document.getElementById('edit_is_upfront_interest').checked = !!loan.is_upfront_interest;
    toggleUpfrontInterest('edit');
    setAmountInputValue('edit_upfront_interest_amount', loan.upfront_interest_amount || '');

    document.getElementById('edit_maturity_date').value = loan.maturity_date || '';
    document.getElementById('edit_reminder_days').value = loan.reminder_days || 3;

    toggleEditInterestFields(method);
    calculateNetDisbursed('edit');
    openModal('editLoanModal');
}
</script>
@endsection
