@extends('layouts.app')
@section('title', 'Loan Details - ' . $loan->lender_name)

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
    </nav>
</aside>
@endsection

@section('content')
@php
    $totalDraws = $principalRecords->where('record_type', 'draw')->sum('amount');
    $totalRepaid = $principalRecords->where('record_type', 'repayment')->sum('amount');
    $totalBorrowed = $loan->principal_amount + $totalDraws;
    
    $totalInterestPaid = $schedules->sum('paid_amount');
    $totalInterestDue = $schedules->whereIn('status', ['pending', 'partially_paid', 'overdue'])->sum(function($s) {
        return max(0, $s->interest_amount - ($s->paid_amount ?? 0));
    });
    $totalContractedInterest = $schedules->sum('interest_amount');
    if ($totalContractedInterest == 0 && !empty($loan->interest_amount) && $loan->interest_method === 'fixed_amount') {
        $term = !empty($loan->term_months) ? (int)$loan->term_months : 1;
        $totalContractedInterest = $term * $loan->interest_amount;
    } elseif ($totalContractedInterest == 0 && !empty($loan->total_interest)) {
        $totalContractedInterest = $loan->total_interest;
    }

    // Total Obligation (Principal + Scheduled Interest)
    $totalLoanObligation = $totalBorrowed + $totalContractedInterest;
    
    // Total Paid so far (Principal Repaid + Interest Paid)
    $totalPaidAll = $totalRepaid + $totalInterestPaid;
    
    // Total Remaining Balance Owed (Outstanding Principal + Pending Interest)
    $totalRemainingPayable = $loan->outstanding_principal + $totalInterestDue;
    
    $overallRepaymentPct = $totalLoanObligation > 0 ? min(100, round(($totalPaidAll / $totalLoanObligation) * 100, 1)) : 0;
@endphp

<!-- Page Header -->
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem;">
            <a href="/loans" style="color:var(--text-muted); text-decoration:none; font-size:0.85rem; display:flex; align-items:center; gap:0.3rem;">
                <ion-icon name="arrow-back-outline"></ion-icon> Back to Loans
            </a>
        </div>
        <div style="display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
            <span class="badge" style="background:rgba(139,92,246,0.15); color:var(--primary); font-size:1rem; font-weight:800; padding:0.35rem 0.8rem; border-radius:6px;">{{ $loan->loan_code ?: ('LN-' . str_pad($loan->id, 4, '0', STR_PAD_LEFT)) }}</span>
            <h1 style="margin:0; font-size:1.75rem; font-weight:800; color:var(--text-heading);">Loan: {{ $loan->lender_name }}</h1>
            @if($loan->status === 'active')
                <span class="badge" style="background:var(--primary-light); color:var(--primary); font-weight:600; padding:0.35rem 0.8rem; font-size:0.85rem;">Active Loan</span>
            @elseif($loan->status === 'settled')
                <span class="badge" style="background:#dcfce7; color:#166534; font-weight:600; padding:0.35rem 0.8rem; font-size:0.85rem;">Settled</span>
            @elseif($loan->status === 'closed')
                <span class="badge" style="background:#f1f5f9; color:#475569; font-weight:600; padding:0.35rem 0.8rem; font-size:0.85rem;">Closed</span>
            @else
                <span class="badge" style="background:#fef3c7; color:#b45309; font-weight:600; padding:0.35rem 0.8rem; font-size:0.85rem;">Pending Activation</span>
            @endif
        </div>
        <p class="subtitle" style="margin-top:0.3rem;">
            {{ strip_tags($loan->purpose) ?: 'General Loan Facility' }} | Initial Principal: {{ $loan->currency }} {{ number_format($loan->principal_amount, 2) }}
            @if($loan->status === 'settled' && !empty($loan->settled_date))
                | <strong style="color:var(--success);"><ion-icon name="checkmark-done-outline" style="vertical-align:middle;"></ion-icon> Fully Settled on {{ date('M d, Y', strtotime($loan->settled_date)) }}</strong>
            @endif
        </p>
    </div>
    
    <div class="header-actions" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap:wrap;">
        @if($loan->status === 'pending')
            <form action="/loans/{{ $loan->id }}/activate" method="POST" onsubmit="return confirm('Activate this loan and generate its interest schedule?');" style="margin: 0;">
                @csrf
                <button type="submit" class="btn btn-primary-gradient btn-pill">
                    <ion-icon name="play-outline" style="vertical-align:middle;"></ion-icon> Activate Loan & Generate Schedule
                </button>
            </form>
        @elseif($loan->status === 'active')
            <button type="button" class="btn btn-primary-gradient btn-pill" onclick="openModal('settlePrincipalFullyModal')">
                <ion-icon name="checkmark-done-outline" style="vertical-align:middle;"></ion-icon> Settle Principal Fully
            </button>
        @endif
        <button class="btn btn-primary-gradient btn-pill" onclick="openModal('editLoanModal')">
            <ion-icon name="create-outline" style="vertical-align:middle;"></ion-icon> Edit Loan
        </button>
        <button class="btn btn-outline btn-pill" onclick="openModal('changeStatusModal')">
            <ion-icon name="swap-horizontal-outline" style="vertical-align:middle;"></ion-icon> Change Status
        </button>
        <form action="/loans/{{ $loan->id }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this loan and all associated schedules and records? This action cannot be undone.');" style="margin: 0; display:inline;">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline btn-pill" style="color:var(--danger); border-color:var(--danger);">
                <ion-icon name="trash-outline" style="vertical-align:middle;"></ion-icon> Delete Loan
            </button>
        </form>
    </div>
</header>

<!-- Total Loan Settlement Progress Banner -->
<div class="glass-card" style="background: var(--bg-card); border: 1px solid var(--border); border-left: 6px solid var(--primary); margin-bottom: 1.5rem; padding: 1.25rem 1.5rem; border-radius: 12px;">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
        <div>
            <span style="font-size:0.8rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; color:var(--text-muted); display:flex; align-items:center; gap:0.4rem;">
                <ion-icon name="analytics-outline" style="color:var(--primary);"></ion-icon> Total Loan Settlement Realization
            </span>
            <h2 style="font-size:1.8rem; font-weight:800; color:var(--text-heading); margin:0.2rem 0 0 0;">
                {{ $loan->currency }} {{ number_format($totalRemainingPayable, 2) }}
                <small style="font-size:0.85rem; font-weight:500; color:var(--danger);">(Total Remaining Payable: Principal {{ number_format($loan->outstanding_principal, 2) }} + Interest {{ number_format($totalInterestDue, 2) }})</small>
            </h2>
        </div>
        <div style="display:flex; gap:1rem; align-items:center;">
            <div style="background:var(--primary-light); padding:0.5rem 1rem; border-radius:10px; text-align:center;">
                <span style="font-size:0.7rem; color:var(--primary); font-weight:600; text-transform:uppercase;">Overall Settled Rate</span>
                <div style="font-size:1.25rem; font-weight:700; color:var(--primary);">{{ $overallRepaymentPct }}%</div>
            </div>
        </div>
    </div>

    <!-- Progress Bar -->
    <div style="margin-top: 1rem;">
        <div style="display:flex; justify-content:space-between; font-size:0.8rem; font-weight:600; color:var(--text-main); margin-bottom:0.3rem;">
            <span>Total Paid To Date: {{ $loan->currency }} {{ number_format($totalPaidAll, 2) }}</span>
            <span>Total Loan Obligation (Principal + Interest): {{ $loan->currency }} {{ number_format($totalLoanObligation, 2) }}</span>
        </div>
        <div style="background:var(--bg-page); border-radius:8px; height:10px; overflow:hidden; border:1px solid var(--border-light);">
            <div style="width:{{ $overallRepaymentPct }}%; background: linear-gradient(90deg, #10b981, #059669); height:100%; border-radius:8px; transition:width 0.8s ease;"></div>
        </div>
    </div>
</div>

<!-- 4 KPI Metric Cards Grid -->
<div class="metric-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; margin-bottom:1.5rem;">
    <!-- Tile 1: Total Loan Obligation (Payable) -->
    <div class="metric-card" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.85rem; font-weight:600; opacity:0.9;">Total Loan Obligation</h3>
            <ion-icon name="calculator-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.5rem; font-weight:700; margin-top:0.3rem;">{{ $loan->currency }} {{ number_format($totalLoanObligation, 2) }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Principal ({{ number_format($totalBorrowed, 2) }}) + Interest ({{ number_format($totalContractedInterest, 2) }})</div>
    </div>

    <!-- Tile 2: Total Remaining Balance Payable -->
    <div class="metric-card" style="background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.85rem; font-weight:600; opacity:0.9;">Total Remaining Payable</h3>
            <ion-icon name="alert-circle-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.5rem; font-weight:700; margin-top:0.3rem;">{{ $loan->currency }} {{ number_format($totalRemainingPayable, 2) }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Rem. Principal + Rem. Interest</div>
    </div>

    <!-- Tile 3: Outstanding Principal -->
    <div class="metric-card" style="background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.85rem; font-weight:600; opacity:0.9;">Outstanding Principal</h3>
            <ion-icon name="wallet-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.5rem; font-weight:700; margin-top:0.3rem;">{{ $loan->currency }} {{ number_format($loan->outstanding_principal, 2) }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Repaid: {{ $loan->currency }} {{ number_format($totalRepaid, 2) }}</div>
    </div>

    <!-- Tile 4: Total Amount Paid To Date -->
    <div class="metric-card" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.85rem; font-weight:600; opacity:0.9;">Total Paid To Date</h3>
            <ion-icon name="checkmark-circle-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.5rem; font-weight:700; margin-top:0.3rem;">{{ $loan->currency }} {{ number_format($totalPaidAll, 2) }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Principal ({{ number_format($totalRepaid, 2) }}) + Interest ({{ number_format($totalInterestPaid, 2) }})</div>
    </div>
</div>

<!-- Main Details Grid -->
<div class="details-grid" style="display:grid; grid-template-columns: 320px 1fr; gap:1.5rem; align-items:start;">
    <!-- Left Sidebar: Info & Actions -->
    <div style="display:flex; flex-direction:column; gap:1.25rem;">
        <!-- Loan Terms Card -->
        <div class="card" style="padding:1.25rem;">
            <h3 style="font-size:1rem; margin:0 0 1rem 0; color:var(--text-heading); font-weight:700; display:flex; align-items:center; gap:0.4rem;">
                <ion-icon name="document-text-outline" style="color:var(--primary);"></ion-icon> Loan Terms & Breakdown
            </h3>
            
            <div style="display:flex; flex-direction:column; gap:0.75rem; font-size:0.85rem;">
                <div style="display:flex; justify-content:space-between; border-bottom:1px dashed var(--border); padding-bottom:0.5rem; background:var(--primary-light); padding:0.5rem; border-radius:6px;">
                    <span style="font-weight:600; color:var(--primary);">Total Loan Obligation:</span>
                    <strong style="color:var(--primary); font-size:0.95rem;">{{ $loan->currency }} {{ number_format($totalLoanObligation, 2) }}</strong>
                </div>

                <div style="display:flex; justify-content:space-between; border-bottom:1px dashed var(--border); padding-bottom:0.5rem;">
                    <span class="text-muted">Interest Method:</span>
                    <strong style="color:var(--text-heading); text-align:right;">
                        @if($loan->interest_method === 'fixed_amount') Fixed Amount
                        @elseif($loan->interest_method === 'percentage_rate') Percentage ({{ ucfirst($loan->rate_basis ?? 'flat') }})
                        @elseif($loan->interest_method === 'equal_installments') Equal Installments
                        @elseif($loan->interest_method === 'custom_schedule') Custom Schedule
                        @else No Interest @endif
                    </strong>
                </div>

                @if($loan->interest_method === 'fixed_amount')
                    <div style="display:flex; justify-content:space-between; border-bottom:1px dashed var(--border); padding-bottom:0.5rem;">
                        <span class="text-muted">Fixed Interest Amount:</span>
                        <strong style="color:var(--text-heading);">{{ $loan->currency }} {{ number_format($loan->interest_amount, 2) }}</strong>
                    </div>
                @endif

                @if($loan->interest_method === 'percentage_rate')
                    <div style="display:flex; justify-content:space-between; border-bottom:1px dashed var(--border); padding-bottom:0.5rem;">
                        <span class="text-muted">Interest Rate:</span>
                        <strong style="color:var(--primary);">{{ $loan->interest_rate }}%</strong>
                    </div>
                @endif

                @if(!empty($loan->is_upfront_interest ?? null))
                    <div style="display:flex; justify-content:space-between; border-bottom:1px dashed var(--border); padding-bottom:0.5rem; background:#fef3c7; color:#92400e; padding:0.5rem; border-radius:6px;">
                        <span style="font-weight:600;">Net Cash In-Hand:</span>
                        <strong style="font-size:0.95rem;">{{ $loan->currency }} {{ number_format($loan->net_disbursed ?? $loan->principal_amount, 2) }}</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; border-bottom:1px dashed var(--border); padding-bottom:0.5rem;">
                        <span class="text-muted">Upfront Interest Paid:</span>
                        <strong style="color:var(--success);">{{ $loan->currency }} {{ number_format(($loan->upfront_interest_amount ?? null) ?: $loan->interest_amount, 2) }}</strong>
                    </div>
                @endif

                @if(!in_array($loan->interest_method, ['no_interest', 'custom_schedule']))
                    <div style="display:flex; justify-content:space-between; border-bottom:1px dashed var(--border); padding-bottom:0.5rem;">
                        <span class="text-muted">Frequency:</span>
                        <strong style="color:var(--text-heading);">{{ ucfirst($loan->frequency ?? 'monthly') }} (Day {{ $loan->due_day ?? '1' }})</strong>
                    </div>
                @endif

                <div style="display:flex; justify-content:space-between; border-bottom:1px dashed var(--border); padding-bottom:0.5rem;">
                    <span class="text-muted">Claimed / Start Date:</span>
                    <strong style="color:var(--text-heading);">{{ $loan->claimed_date ?? 'N/A' }}</strong>
                </div>

                @if($loan->status === 'settled' && !empty($loan->settled_date))
                    <div style="display:flex; justify-content:space-between; border-bottom:1px dashed var(--border); padding-bottom:0.5rem; background:rgba(16,185,129,0.08); padding:0.5rem; border-radius:6px;">
                        <span style="font-weight:700; color:var(--success); display:flex; align-items:center; gap:0.3rem;">
                            <ion-icon name="checkmark-done-circle-outline"></ion-icon> Settlement Date:
                        </span>
                        <strong style="color:var(--success); font-size:0.95rem;">{{ date('M d, Y', strtotime($loan->settled_date)) }}</strong>
                    </div>
                @endif

                <div style="display:flex; justify-content:space-between; border-bottom:1px dashed var(--border); padding-bottom:0.5rem;">
                    <span class="text-muted">Full Principal Due Date:</span>
                    <strong style="color:var(--text-heading);">
                        {{ $loan->maturity_date ?: 'Not Specified (Open Term)' }}
                        @if(!empty($loan->maturity_date ?? null))
                            <span class="badge" style="background:var(--primary-light); color:var(--primary); font-size:0.7rem; margin-left:0.25rem;">
                                Reminder: {{ $loan->reminder_days ?? 3 }}d
                            </span>
                        @endif
                    </strong>
                </div>

                <div style="display:flex; justify-content:space-between; border-bottom:1px dashed var(--border); padding-bottom:0.5rem;">
                    <span class="text-muted">Term (Months):</span>
                    <strong style="color:var(--text-heading);">{{ $loan->term_months ?? '-' }} Months</strong>
                </div>
            </div>

            @if($loan->status === 'active')
                <div style="margin-top: 1.25rem; display:flex; flex-direction:column; gap:0.5rem;">
                    <button class="btn btn-primary" style="width:100%; font-size:0.85rem;" onclick="openModal('recordRepaymentModal')">
                        <ion-icon name="arrow-down-circle-outline"></ion-icon> Record Principal Repayment
                    </button>
                    <button class="btn btn-outline" style="width:100%; font-size:0.85rem;" onclick="openModal('addDrawModal')">
                        <ion-icon name="add-circle-outline"></ion-icon> Add Additional Draw
                    </button>
                </div>
            @endif
        </div>

        @if(!empty($loan->purpose))
        <!-- Facility Purpose & Terms Card -->
        <div class="card" style="padding:1.25rem;">
            <h3 style="font-size:0.95rem; margin:0 0 0.75rem 0; color:var(--text-heading); font-weight:700; display:flex; align-items:center; gap:0.4rem;">
                <ion-icon name="document-text-outline" style="color:var(--primary);"></ion-icon> Purpose & Facility Terms
            </h3>
            <div class="prose" style="font-size:0.85rem; color:var(--text-main); line-height:1.6;">
                {!! $loan->purpose !!}
            </div>
        </div>
        @endif

        <!-- Attachments Card -->
        <div class="card" style="padding:1.25rem;">
            <h3 style="font-size:1rem; margin:0 0 1rem 0; color:var(--text-heading); font-weight:700; display:flex; align-items:center; gap:0.4rem;">
                <ion-icon name="attach-outline" style="color:var(--primary);"></ion-icon> Attachments & Documents
            </h3>
            @if(isset($attachments) && $attachments->count() > 0)
                <ul style="list-style:none; padding:0; margin:0;">
                    @foreach($attachments as $attachment)
                        <li style="margin-bottom:0.5rem; display:flex; align-items:center; gap:0.5rem; background:var(--bg-page); padding:0.5rem 0.75rem; border-radius:8px; border:1px solid var(--border-light);">
                            <ion-icon name="document-attach-outline" style="color:var(--primary); font-size:1.1rem; flex-shrink:0;"></ion-icon>
                            <a href="{{ Storage::url($attachment->file_path) }}" target="_blank" style="color:var(--text-heading); text-decoration:none; font-size:0.85rem; word-break: break-all; font-weight:500;">
                                {{ $attachment->file_name }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @else
                <div style="text-align:center; padding:1.5rem 0; color:var(--text-muted);">
                    <ion-icon name="folder-open-outline" style="font-size:2rem; opacity:0.4; margin-bottom:0.3rem;"></ion-icon>
                    <p style="margin:0; font-size:0.85rem;">No attachments uploaded.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Right Main Tabbed Content -->
    <div class="loan-details-content" style="min-width:0;">
        <!-- Navigation Tabs -->
        <div style="background:var(--bg-card); border-radius:12px; border:1px solid var(--border); padding:0.5rem 0.5rem 0 0.5rem; margin-bottom:1rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--border-light); padding-right:0.5rem;">
                <div style="display:flex; gap:1rem;">
                    <button type="button" onclick="switchTab('schedule')" id="tab-schedule" style="padding:0.75rem 1.25rem; font-weight:600; color:var(--primary); border:none; background:transparent; border-bottom:3px solid var(--primary); cursor:pointer; font-size:0.9rem; display:flex; align-items:center; gap:0.4rem;">
                        <ion-icon name="calendar-outline"></ion-icon> Interest Schedule
                        <span class="badge" style="background:var(--primary-light); color:var(--primary); border-radius:12px; padding:0.1rem 0.5rem; font-size:0.75rem;">{{ $schedules->count() }}</span>
                    </button>
                    <button type="button" onclick="switchTab('principal')" id="tab-principal" style="padding:0.75rem 1.25rem; font-weight:500; color:var(--text-muted); border:none; background:transparent; border-bottom:3px solid transparent; cursor:pointer; font-size:0.9rem; display:flex; align-items:center; gap:0.4rem;">
                        <ion-icon name="swap-vertical-outline"></ion-icon> Principal Records
                        <span class="badge" style="background:var(--bg-page); color:var(--text-muted); border-radius:12px; padding:0.1rem 0.5rem; font-size:0.75rem;">{{ $principalRecords->count() }}</span>
                    </button>
                </div>
                <button type="button" onclick="openModal('addScheduleModal')" class="btn btn-outline" style="font-size:0.8rem; padding:0.35rem 0.75rem; border-radius:6px; display:flex; align-items:center; gap:0.3rem;">
                    <ion-icon name="add-outline"></ion-icon> Add Interest Row
                </button>
            </div>
        </div>

        <!-- TAB 1: Interest Schedule -->
        <div id="content-schedule">
            <div class="card" style="padding:0; overflow:visible; background:var(--bg-card); border-radius:12px; border:1px solid var(--border);">
                <table class="data-table" style="margin:0; width:100%; border-collapse:collapse;">
                    <thead style="background:var(--bg-page); border-bottom:1px solid var(--border);">
                        <tr>
                            <th style="padding: 0.85rem 1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">Due Date</th>
                            <th style="padding: 0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">Amount Due</th>
                            <th style="padding: 0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">Paid Amount</th>
                            <th style="padding: 0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">Remaining</th>
                            <th style="padding: 0.85rem 1rem; text-align:center; font-size:0.8rem; color:var(--text-muted);">Status</th>
                            <th style="padding: 0.85rem 1rem; text-align:center; font-size:0.8rem; color:var(--text-muted);">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($schedules as $sched)
                        @php
                            $paidAmt = $sched->paid_amount ?? 0;
                            $remAmt = max(0, $sched->interest_amount - $paidAmt);
                        @endphp
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.85rem 1rem; text-align:left;">
                                <span class="font-medium {{ \Carbon\Carbon::parse($sched->due_date)->isPast() && in_array($sched->status, ['pending', 'partially_paid']) ? 'text-danger' : '' }}" style="font-size:0.85rem;">
                                    {{ $sched->due_date }}
                                </span>
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align:right; font-weight:600; color:var(--text-heading); font-size:0.85rem;">
                                {{ $loan->currency }} {{ number_format($sched->interest_amount, 2) }}
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align:right; color:var(--success); font-weight:500; font-size:0.85rem;">
                                {{ $loan->currency }} {{ number_format($paidAmt, 2) }}
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align:right; color:{{ $remAmt > 0 ? 'var(--danger)' : 'var(--text-muted)' }}; font-weight:500; font-size:0.85rem;">
                                {{ $loan->currency }} {{ number_format($remAmt, 2) }}
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align:center;">
                                @if($sched->status === 'paid')
                                    <span class="badge" style="background:#dcfce7; color:#166534; font-weight:600;">Paid</span>
                                @elseif($sched->status === 'skipped')
                                    <span class="badge" style="background:#f1f5f9; color:#475569; font-weight:600;">Skipped</span>
                                @elseif($sched->status === 'partially_paid')
                                    <span class="badge" style="background:#fef3c7; color:#b45309; font-weight:600;">Partially Paid</span>
                                @elseif(\Carbon\Carbon::parse($sched->due_date)->isPast())
                                    <span class="badge" style="background:#fee2e2; color:#991b1b; font-weight:600;">Overdue</span>
                                @else
                                    <span class="badge" style="background:#e2e8f0; color:#334155; font-weight:600;">Pending</span>
                                @endif
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align:center; position:relative;">
                                @if(in_array($sched->status, ['pending', 'partially_paid', 'overdue']))
                                <div class="dropdown" style="position:relative; display:inline-block;">
                                    <button type="button" class="action-btn" onclick="toggleDropdown('sched-actions-{{ $sched->id }}', event)" style="background:var(--bg-page); border:1px solid var(--border); border-radius:6px; padding:0.35rem 0.65rem; cursor:pointer; color:var(--text-heading); display:inline-flex; align-items:center; justify-content:center;">
                                        <ion-icon name="ellipsis-horizontal-outline" style="font-size:1.1rem;"></ion-icon>
                                    </button>
                                    <div class="dropdown-menu" id="sched-actions-{{ $sched->id }}" style="position:absolute; right:0; top:100%; z-index:1000; background:var(--bg-card); border:1px solid var(--border); box-shadow:var(--shadow-card); border-radius:8px; min-width:165px; text-align:left; display:none; margin-top:0.25rem;">
                                        <a href="javascript:void(0)" onclick="openSettleModal({{ $sched->id }}, {{ $remAmt }}, '{{ $sched->due_date }}')" style="display:flex; align-items:center; gap:0.5rem; padding:0.55rem 0.85rem; color:var(--text-main); font-size:0.85rem; text-decoration:none;">
                                            <ion-icon name="cash-outline" style="color:var(--success); font-size:1rem;"></ion-icon> Settle Payment
                                        </a>
                                        <a href="javascript:void(0)" onclick="openEditInterestModal({{ $sched->id }}, {{ $sched->interest_amount }}, '{{ $sched->due_date }}')" style="display:flex; align-items:center; gap:0.5rem; padding:0.55rem 0.85rem; color:var(--text-main); font-size:0.85rem; text-decoration:none;">
                                            <ion-icon name="create-outline" style="color:var(--primary); font-size:1rem;"></ion-icon> Edit Schedule (Date & Amount)
                                        </a>
                                        <form action="/loans/{{ $loan->id }}/schedule/{{ $sched->id }}/skip" method="POST" style="margin:0;">
                                            @csrf
                                            <button type="submit" style="display:flex; align-items:center; gap:0.5rem; width:100%; text-align:left; background:none; border:none; padding:0.55rem 0.85rem; cursor:pointer; color:var(--text-muted); font-size:0.85rem;">
                                                <ion-icon name="close-circle-outline" style="font-size:1rem;"></ion-icon> Mark Not Needed
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                @else
                                    <span class="text-muted" style="font-size:0.8rem;">Completed</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach

                        @if($schedules->isEmpty())
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4" style="padding:2.5rem; text-align:center;">
                                <ion-icon name="calendar-outline" style="font-size:2.5rem; opacity:0.4; margin-bottom:0.5rem;"></ion-icon><br>
                                @if($loan->interest_method === 'no_interest' || (($loan->interest_amount ?? 0) <= 0 && empty($loan->total_interest) && empty($loan->interest_rate)))
                                    This loan has no interest / zero periodic interest (Principal only). No interest schedules required.
                                @elseif($loan->status === 'pending')
                                    Loan is currently pending. Activate the loan above to generate the interest payment schedule.
                                @else
                                    No interest schedules found for this loan.
                                @endif
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 2: Principal Records -->
        <div id="content-principal" style="display:none;">
            <div class="card" style="padding:0; overflow:visible; background:var(--bg-card); border-radius:12px; border:1px solid var(--border);">
                <table class="data-table" style="margin:0; width:100%; border-collapse:collapse;">
                    <thead style="background:var(--bg-page); border-bottom:1px solid var(--border);">
                        <tr>
                            <th style="padding: 0.85rem 1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">Date</th>
                            <th style="padding: 0.85rem 1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">Type</th>
                            <th style="padding: 0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">Amount</th>
                            <th style="padding: 0.85rem 1rem; text-align:center; font-size:0.8rem; color:var(--text-muted);">Payment Mode</th>
                            <th style="padding: 0.85rem 1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($principalRecords as $record)
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.85rem 1rem; font-weight:500; text-align:left; font-size:0.85rem;">{{ $record->record_date }}</td>
                            <td style="padding: 0.85rem 1rem; text-align:left;">
                                @if($record->record_type === 'repayment')
                                    <span style="color:var(--success); font-weight:600; display:inline-flex; align-items:center; gap:0.3rem; font-size:0.85rem;">
                                        <ion-icon name="arrow-down-circle-outline" style="font-size:1.1rem;"></ion-icon> Principal Repayment
                                    </span>
                                @else
                                    <span style="color:var(--danger); font-weight:600; display:inline-flex; align-items:center; gap:0.3rem; font-size:0.85rem;">
                                        <ion-icon name="arrow-up-circle-outline" style="font-size:1.1rem;"></ion-icon> Additional Draw
                                    </span>
                                @endif
                            </td>
                            <td style="padding: 0.85rem 1rem; font-weight:700; color:var(--text-heading); text-align:right; font-size:0.85rem;">
                                {{ $loan->currency }} {{ number_format($record->amount, 2) }}
                            </td>
                            <td style="padding: 0.85rem 1rem; text-align:center;">
                                <span class="badge" style="background:var(--bg-page); color:var(--text-heading); border:1px solid var(--border);">
                                    {{ $record->payment_mode ?? 'Default' }}
                                </span>
                            </td>
                            <td class="text-muted" style="padding: 0.85rem 1rem; text-align:left; font-size:0.85rem;">{{ $record->notes ?? '-' }}</td>
                        </tr>
                        @endforeach

                        @if($principalRecords->isEmpty())
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4" style="padding:2.5rem; text-align:center;">
                                <ion-icon name="swap-vertical-outline" style="font-size:2.5rem; opacity:0.4; margin-bottom:0.3rem;"></ion-icon><br>
                                No principal repayments or draw records logged yet.
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('modals')
<!-- Settle Principal Fully Modal -->
<div class="modal-backdrop" id="settlePrincipalFullyModal">
    <div class="modal-card" style="max-width:540px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display:flex; align-items:center; gap:0.5rem;">
                <ion-icon name="checkmark-done-circle-outline" style="color:var(--success); font-size:1.4rem;"></ion-icon> Settle Principal Fully
            </h3>
            <button type="button" class="btn-close" onclick="closeModal('settlePrincipalFullyModal')">&times;</button>
        </div>
        <form action="/loans/{{ $loan->id }}/settle-fully" method="POST">
            @csrf
            <div class="modal-body">
                <!-- Outstanding Balance Overview Banner -->
                <div style="background:var(--primary-light); border:1px solid var(--border-light); border-radius:10px; padding:1.1rem 1.25rem; margin-bottom:1.25rem; text-align:center;">
                    <span style="font-size:0.75rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.5px; display:block;">Total Remaining Principal</span>
                    <div style="font-size:1.65rem; font-weight:800; color:var(--text-heading); margin-top:0.25rem;">
                        {{ $loan->currency }} {{ number_format($loan->outstanding_principal, 2) }}
                    </div>
                    <span style="font-size:0.75rem; color:var(--text-muted); margin-top:0.2rem; display:block;">
                        Settles the remaining principal obligation and records the ledger expense transaction.
                    </span>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight:700;">Paid Date / Transaction Date *</label>
                    <input type="date" name="settlement_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    <small class="text-muted" style="font-size:0.75rem; margin-top:0.25rem; display:block;">
                        This date will be applied to the principal repayment record and ledger transaction.
                    </small>
                </div>

                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;">Payment Mode</label>
                        <select name="payment_method" class="form-control" required>
                            <option value="Normal">Normal</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Petty Cash">Petty Cash</option>
                            <option value="Credit Card">Credit Card</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Reference Number (Optional)</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="E.g. TXN-1049 / CHQ-882">
                    </div>
                </div>

                <div class="form-group" style="margin-top:1.25rem;">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" value="Full Principal Settlement" placeholder="E.g. Final principal clearance">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('settlePrincipalFullyModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Confirm & Settle Principal</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Interest Schedule Modal -->
<div class="modal-backdrop" id="addScheduleModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Add Interest Schedule Row</h3>
            <button type="button" class="btn-close" onclick="closeModal('addScheduleModal')">&times;</button>
        </div>
        <form action="/loans/{{ $loan->id }}/schedule" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Interest Amount ({{ $loan->currency }})</label>
                    <x-amount-input name="interest_amount" required="true" />
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addScheduleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Add Schedule Row</button>
            </div>
        </form>
    </div>
</div>

<!-- Change Status Modal -->
<div class="modal-backdrop" id="changeStatusModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Change Loan Status</h3>
            <button type="button" class="btn-close" onclick="closeModal('changeStatusModal')">&times;</button>
        </div>
        <form action="/loans/{{ $loan->id }}/status" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">New Status</label>
                    <select name="status" class="form-control" required>
                        <option value="pending" {{ $loan->status === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="active" {{ $loan->status === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="closed" {{ $loan->status === 'closed' ? 'selected' : '' }}>Closed</option>
                        <option value="settled" {{ $loan->status === 'settled' ? 'selected' : '' }}>Settled</option>
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
        <form action="/loans/{{ $loan->id }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="form-row" style="margin-bottom:1.25rem;">
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;">Loan Reference Code</label>
                        <input type="text" name="loan_code" id="edit_loan_code" class="form-control" value="{{ $loan->loan_code ?: ('LN-' . str_pad($loan->id, 4, '0', STR_PAD_LEFT)) }}" placeholder="E.g. LN-0016">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;">Select Party / Lender</label>
                        <select name="party_id" id="edit_party_id" class="form-control">
                            <option value="">-- None / Custom Lender --</option>
                            @foreach($parties as $p)
                                <option value="{{ $p->id }}" {{ $loan->party_id == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;">Lender Name / Entity *</label>
                        <input type="text" name="lender_name" id="edit_lender_name" class="form-control" value="{{ $loan->lender_name }}" required>
                    </div>
                </div>

                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col">
                        <label class="form-label">Principal Amount *</label>
                        <x-amount-input name="principal_amount" id="show_edit_principal_amount" required="true" :value="$loan->principal_amount" />
                    </div>
                    <div class="form-col">
                        <label class="form-label">Currency</label>
                        <x-currency-selector name="currency" id="edit_currency" :selected="$loan->currency" required />
                    </div>
                </div>

                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col">
                        <label class="form-label">Claimed / Start Date *</label>
                        <input type="date" name="claimed_date" id="edit_claimed_date" class="form-control" value="{{ $loan->claimed_date ?: $loan->start_date }}" required>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Term (Months) *</label>
                        <input type="number" name="term_months" id="edit_term_months" class="form-control" value="{{ $loan->term_months ?: 12 }}" min="1" required>
                    </div>
                </div>

                <div class="form-group" style="margin-top:1.25rem;">
                    <label class="form-label" style="font-weight:700;">Purpose / Description & Terms</label>
                    <x-rich-editor name="purpose" id="show_edit_purpose" :value="$loan->purpose" placeholder="E.g. Capital investment / terms (type /loan or /employee)..." />
                </div>

                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col">
                        <label class="form-label">Interest Calculation Method</label>
                        <select name="interest_method" id="edit_interest_method" class="form-control" onchange="toggleEditInterestFields(this.value)" required>
                            <option value="fixed_amount" {{ $loan->interest_method === 'fixed_amount' ? 'selected' : '' }}>Fixed Amount per Period</option>
                            <option value="percentage_rate" {{ $loan->interest_method === 'percentage_rate' ? 'selected' : '' }}>Percentage Rate (%)</option>
                            <option value="equal_installments" {{ $loan->interest_method === 'equal_installments' ? 'selected' : '' }}>Equal Installments</option>
                            <option value="custom_schedule" {{ $loan->interest_method === 'custom_schedule' ? 'selected' : '' }}>Custom Schedule</option>
                            <option value="no_interest" {{ $loan->interest_method === 'no_interest' ? 'selected' : '' }}>No Interest</option>
                        </select>
                    </div>
                    <div class="form-col" id="edit_field_frequency">
                        <label class="form-label">Payment Frequency</label>
                        <select name="frequency" id="edit_frequency" class="form-control">
                            <option value="monthly" {{ $loan->frequency === 'monthly' ? 'selected' : '' }}>Monthly</option>
                            <option value="quarterly" {{ $loan->frequency === 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                        </select>
                    </div>
                </div>

                <!-- Method Dynamic Fields -->
                <div id="edit_field_fixed_amount" class="form-group" style="margin-top:1.25rem; {{ $loan->interest_method === 'fixed_amount' ? '' : 'display:none;' }}">
                    <label class="form-label">Fixed Interest Amount per Period</label>
                    <x-amount-input name="interest_amount" id="show_edit_interest_amount" :value="$loan->interest_amount" placeholder="E.g. 2500.00" />
                </div>

                <div id="edit_field_percentage_rate" class="form-row" style="margin-top:1.25rem; {{ $loan->interest_method === 'percentage_rate' ? '' : 'display:none;' }}">
                    <div class="form-col">
                        <label class="form-label">Interest Rate (%)</label>
                        <input type="number" step="0.01" name="interest_rate" id="edit_interest_rate" class="form-control" value="{{ $loan->interest_rate }}" placeholder="E.g. 10.5">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Rate Basis</label>
                        <select name="rate_basis" id="edit_rate_basis" class="form-control">
                            <option value="flat" {{ $loan->rate_basis === 'flat' ? 'selected' : '' }}>Flat Interest Rate</option>
                            <option value="reducing" {{ $loan->rate_basis === 'reducing' ? 'selected' : '' }}>Reducing Balance</option>
                        </select>
                    </div>
                </div>

                <div id="edit_field_equal_installments" class="form-group" style="margin-top:1.25rem; {{ $loan->interest_method === 'equal_installments' ? '' : 'display:none;' }}">
                    <label class="form-label">Total Agreed Interest Amount</label>
                    <x-amount-input name="total_interest" id="show_edit_total_interest" :value="$loan->total_interest" placeholder="E.g. 50000.00" />
                </div>

                <!-- Upfront Interest Deduction Feature -->
                <div class="glass-card" style="margin-top:1.25rem; padding:1rem; border-radius:10px; background:var(--bg-page); border:1px solid var(--border-light);">
                    <label style="display:flex; align-items:center; gap:0.6rem; cursor:pointer; font-weight:600; color:var(--text-heading); font-size:0.9rem; margin-bottom:0.35rem;">
                        <input type="checkbox" name="is_upfront_interest" id="edit_is_upfront_interest" value="1" {{ !empty($loan->is_upfront_interest ?? null) ? 'checked' : '' }} onchange="toggleUpfrontInterest('edit')" style="width:1.15rem; height:1.15rem; accent-color:var(--primary);">
                        <span>Deduct Interest Upfront (Paid on Claimed Date)</span>
                    </label>
                    <p class="text-muted" style="margin:0 0 0.5rem 1.75rem; font-size:0.8rem;">
                        Check if interest is paid immediately when loan is taken (e.g. Receive 42,500 for a 45,000 loan with 2,500 interest paid upfront on Day 1).
                    </p>
                    <div id="edit_upfront_interest_container" style="{{ !empty($loan->is_upfront_interest ?? null) ? '' : 'display:none;' }}; margin-left:1.75rem; margin-top:0.5rem;">
                        <div class="form-group" style="margin-bottom:0.5rem;">
                            <label class="form-label" style="font-size:0.85rem;">Upfront Interest Amount (Leave empty to use 1st period interest)</label>
                            <x-amount-input name="upfront_interest_amount" id="edit_upfront_interest_amount" :value="$loan->upfront_interest_amount ?? ''" placeholder="Auto-calculated from period interest" />
                        </div>
                        <div id="edit_upfront_summary" style="font-size:0.82rem; color:var(--primary); font-weight:600; background:var(--primary-light); padding:0.4rem 0.75rem; border-radius:6px; display:inline-block;">
                            💡 Net Cash Received: <span id="edit_net_disbursed_label">{{ number_format($loan->net_disbursed ?? $loan->principal_amount, 2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Loan Maturity / Full Principal Due Date & Reminders -->
                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col">
                        <label class="form-label" style="font-weight:600;">Loan Full Principal Due Date (Optional)</label>
                        <input type="date" name="maturity_date" id="edit_maturity_date" class="form-control" value="{{ $loan->maturity_date }}">
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
                            <option value="1" {{ ($loan->reminder_days ?? 3) == 1 ? 'selected' : '' }}>1 day before due date</option>
                            <option value="2" {{ ($loan->reminder_days ?? 3) == 2 ? 'selected' : '' }}>2 days before due date</option>
                            <option value="3" {{ ($loan->reminder_days ?? 3) == 3 ? 'selected' : '' }}>3 days before due date</option>
                            <option value="5" {{ ($loan->reminder_days ?? 3) == 5 ? 'selected' : '' }}>5 days before due date</option>
                            <option value="7" {{ ($loan->reminder_days ?? 3) == 7 ? 'selected' : '' }}>1 week before due date</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col" id="edit_field_due_day">
                        <label class="form-label">Due Day of Month (Optional)</label>
                        <input type="number" name="due_day" id="edit_due_day" class="form-control" value="{{ $loan->due_day }}" placeholder="Dynamic (Loan Claim Date)" min="1" max="31">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Guarantor (Optional)</label>
                        <input type="text" name="guarantor" id="edit_guarantor" class="form-control" value="{{ $loan->guarantor }}" placeholder="Guarantor name / contact">
                    </div>
                </div>

                <div class="form-group" style="margin-top:1.25rem;">
                    <label class="form-label">Collateral / Security (Optional)</label>
                    <textarea name="collateral" id="edit_collateral" class="form-control" rows="2" placeholder="Pledged assets or guarantees">{{ $loan->collateral }}</textarea>
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

<!-- Settle Interest Modal -->
<div class="modal-backdrop" id="settleModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Settle Interest Payment</h3>
            <button type="button" class="btn-close" onclick="closeModal('settleModal')">&times;</button>
        </div>
        <form id="settleForm" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Amount Paid ({{ $loan->currency }})</label>
                    <x-amount-input name="paid_amount" id="settle_amount" required="true" />
                </div>
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Date Paid</label>
                    <input type="date" name="paid_date" id="settle_paid_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('settleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Record Payment</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Interest Schedule Modal (Date & Amount) -->
<div class="modal-backdrop" id="editScheduleModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Edit Interest Schedule</h3>
            <button type="button" class="btn-close" onclick="closeModal('editScheduleModal')">&times;</button>
        </div>
        <form id="editScheduleForm" method="POST">
            @csrf
            <div class="modal-body">
                <p class="text-muted" style="margin-top:0; font-size:0.85rem;">Modify the scheduled due date or expected interest amount for this installment.</p>
                <div class="form-group">
                    <label class="form-label">Due Date *</label>
                    <input type="date" name="due_date" id="edit_sched_due_date" class="form-control" required>
                </div>
                <div class="form-group" style="margin-top:1.25rem;">
                    <label class="form-label">Interest Amount Due ({{ $loan->currency }}) *</label>
                    <x-amount-input name="interest_amount" id="edit_sched_interest_amount" required="true" />
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editScheduleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Update Schedule</button>
            </div>
        </form>
    </div>
</div>

<!-- Record Repayment Modal -->
<div class="modal-backdrop" id="recordRepaymentModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Record Principal Repayment</h3>
            <button type="button" class="btn-close" onclick="closeModal('recordRepaymentModal')">&times;</button>
        </div>
        <form action="/loans/{{ $loan->id }}/repayment" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Repayment Amount ({{ $loan->currency }})</label>
                    <x-amount-input name="amount" required="true" />
                </div>
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Date</label>
                    <input type="date" name="record_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
                <div style="margin-top: 1rem;">
                    <x-payment-modes />
                </div>
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" placeholder="E.g. Bank Transfer / Monthly principal portion">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('recordRepaymentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Record Repayment</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Draw Modal -->
<div class="modal-backdrop" id="addDrawModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Record Additional Draw</h3>
            <button type="button" class="btn-close" onclick="closeModal('addDrawModal')">&times;</button>
        </div>
        <form action="/loans/{{ $loan->id }}/draw" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Amount Borrowed ({{ $loan->currency }})</label>
                    <x-amount-input name="amount" required="true" />
                </div>
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Date</label>
                    <input type="date" name="record_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" placeholder="E.g. Additional facility draw down">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addDrawModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Record Draw</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(tab) {
    document.getElementById('content-schedule').style.display = tab === 'schedule' ? 'block' : 'none';
    document.getElementById('content-principal').style.display = tab === 'principal' ? 'block' : 'none';
    
    const tabSched = document.getElementById('tab-schedule');
    const tabPrinc = document.getElementById('tab-principal');

    if (tab === 'schedule') {
        tabSched.style.color = 'var(--primary)';
        tabSched.style.borderBottom = '3px solid var(--primary)';
        tabSched.style.fontWeight = '600';
        
        tabPrinc.style.color = 'var(--text-muted)';
        tabPrinc.style.borderBottom = '3px solid transparent';
        tabPrinc.style.fontWeight = '500';
    } else {
        tabPrinc.style.color = 'var(--primary)';
        tabPrinc.style.borderBottom = '3px solid var(--primary)';
        tabPrinc.style.fontWeight = '600';
        
        tabSched.style.color = 'var(--text-muted)';
        tabSched.style.borderBottom = '3px solid transparent';
        tabSched.style.fontWeight = '500';
    }
}

function toggleDropdown(id, event) {
    if (event) {
        event.stopPropagation();
    }
    const el = document.getElementById(id);
    const isShowing = el && el.style.display === 'block';
    
    document.querySelectorAll('.dropdown-menu').forEach(menu => {
        menu.style.display = 'none';
    });
    
    if (el && !isShowing) {
        el.style.display = 'block';
    }
}

window.addEventListener('click', function(event) {
    if (!event.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu').forEach(el => {
            el.style.display = 'none';
        });
    }
});

function setAmountInputValue(id, val) {
    const input = document.getElementById(id);
    if (!input) return;
    const hidden = input.parentElement ? input.parentElement.querySelector('.amount-hidden') : null;
    if (val !== null && val !== undefined && val !== '' && !isNaN(val)) {
        const num = parseFloat(val);
        input.value = num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        if (hidden) hidden.value = num.toFixed(2);
    } else {
        input.value = '';
        if (hidden) hidden.value = '';
    }
}

function openSettleModal(id, suggestedAmount, dueDate) {
    document.getElementById('settleForm').action = "/loans/{{ $loan->id }}/schedule/" + id + "/settle";
    setAmountInputValue('settle_amount', suggestedAmount);
    const dateInput = document.getElementById('settle_paid_date') || document.querySelector('#settleModal input[name="paid_date"]');
    if (dateInput && dueDate) {
        dateInput.value = dueDate;
    }
    openModal('settleModal');
}

function openEditInterestModal(id, currentAmount, currentDate) {
    document.getElementById('editScheduleForm').action = "/loans/{{ $loan->id }}/schedule/" + id + "/edit";
    document.getElementById('edit_sched_due_date').value = currentDate || '';
    setAmountInputValue('edit_sched_interest_amount', currentAmount);
    openModal('editScheduleModal');
}

function toggleUpfrontInterest(prefix) {
    const isChecked = document.getElementById(prefix + '_is_upfront_interest').checked;
    const container = document.getElementById(prefix + '_upfront_interest_container');
    if (container) container.style.display = isChecked ? 'block' : 'none';
    calculateNetDisbursed(prefix);
}

function calculateNetDisbursed(prefix) {
    const principalInput = document.getElementById('show_edit_principal_amount');
    const principalHidden = principalInput ? (principalInput.parentElement.querySelector('.amount-hidden') || principalInput) : null;
    const principal = parseFloat(principalHidden ? principalHidden.value : 0) || 0;

    const upfrontInput = document.getElementById('edit_upfront_interest_amount');
    const upfrontHidden = upfrontInput ? (upfrontInput.parentElement.querySelector('.amount-hidden') || upfrontInput) : null;
    let upfront = parseFloat(upfrontHidden ? upfrontHidden.value : 0) || 0;

    if (upfront === 0) {
        const method = document.getElementById('edit_interest_method').value;
        if (method === 'fixed_amount') {
            const intInput = document.getElementById('show_edit_interest_amount');
            const intHidden = intInput ? (intInput.parentElement.querySelector('.amount-hidden') || intInput) : null;
            upfront = parseFloat(intHidden ? intHidden.value : 0) || 0;
        } else if (method === 'percentage_rate') {
            const rate = parseFloat(document.getElementById('edit_interest_rate').value) || 0;
            upfront = principal * (rate / 100);
        } else if (method === 'equal_installments') {
            const totInput = document.getElementById('show_edit_total_interest');
            const totHidden = totInput ? (totInput.parentElement.querySelector('.amount-hidden') || totInput) : null;
            const term = parseInt(document.getElementById('edit_term_months').value) || 1;
            const tot = parseFloat(totHidden ? totHidden.value : 0) || 0;
            upfront = tot / Math.max(1, term);
        }
    }

    const net = Math.max(0, principal - upfront);
    const label = document.getElementById('edit_net_disbursed_label');
    if (label) {
        label.textContent = net.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
}

function setMaturityMonths(prefix, months) {
    const claimedDateInput = document.getElementById('edit_claimed_date');
    const maturityInput = document.getElementById('edit_maturity_date');
    const termInput = document.getElementById('edit_term_months');
    
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

function toggleEditInterestFields(method) {
    document.getElementById('edit_field_fixed_amount').style.display = method === 'fixed_amount' ? 'block' : 'none';
    document.getElementById('edit_field_percentage_rate').style.display = method === 'percentage_rate' ? 'flex' : 'none';
    document.getElementById('edit_field_equal_installments').style.display = method === 'equal_installments' ? 'block' : 'none';
    const hasFrequency = (method !== 'no_interest' && method !== 'custom_schedule');
    document.getElementById('edit_field_frequency').style.display = hasFrequency ? 'block' : 'none';
    document.getElementById('edit_field_due_day').style.display = hasFrequency ? 'block' : 'none';
    calculateNetDisbursed('edit');
}
</script>
@endsection
