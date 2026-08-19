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
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Third-Party Loans</h1>
        <p class="subtitle" style="margin-top:0.3rem;">Track borrowing facilities, principal repayments, scheduled interest, and total debt obligations.</p>
    </div>
    <button class="btn btn-primary-gradient btn-pill" onclick="openModal('createLoanModal')">
        <ion-icon name="add-outline" style="vertical-align:middle;"></ion-icon> Record New Loan
    </button>
</header>

<!-- 5 KPI Stat Tiles Grid -->
<div class="metric-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1.25rem; margin-bottom:1.5rem;">
    <!-- Tile 1: Total Borrowed -->
    <div class="metric-card" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; opacity:0.9;">Total Borrowed Base</h3>
            <ion-icon name="wallet-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.4rem; font-weight:800; margin-top:0.3rem;">LKR {{ number_format($totalBorrowed, 2) }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Total principal borrowed</div>
    </div>

    <!-- Tile 2: Total Paid To Date -->
    <div class="metric-card" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; opacity:0.9;">Total Paid To Date</h3>
            <ion-icon name="checkmark-circle-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.4rem; font-weight:800; margin-top:0.3rem;">LKR {{ number_format($totalPaidAll, 2) }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Principal: {{ number_format($totalPrincipalRepaid, 2) }} | Interest: {{ number_format($totalInterestPaid, 2) }}</div>
    </div>

    <!-- Tile 3: Total Interest Paid -->
    <div class="metric-card" style="background: linear-gradient(135deg, #0284c7 0%, #06b6d4 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; opacity:0.9;">Interest Paid</h3>
            <ion-icon name="cash-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.4rem; font-weight:800; margin-top:0.3rem;">LKR {{ number_format($totalInterestPaid, 2) }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Total interest settled</div>
    </div>

    <!-- Tile 4: Total Outstanding Obligation -->
    <div class="metric-card" style="background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; opacity:0.9;">Total Outstanding (P+I)</h3>
            <ion-icon name="alert-circle-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.4rem; font-weight:800; margin-top:0.3rem;">LKR {{ number_format($totalWantToPaid, 2) }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Rem. Principal + Rem. Interest</div>
    </div>

    <!-- Tile 5: This Month Payable -->
    <div class="metric-card" style="background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; opacity:0.9;">This Month Payable</h3>
            <ion-icon name="calendar-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.4rem; font-weight:800; margin-top:0.3rem;">LKR {{ number_format($thisMonthPayable, 2) }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Interest due current month</div>
    </div>
</div>

<!-- Advanced Filter Toolbar -->
<div class="card" style="padding:1rem 1.25rem; margin-bottom:1.5rem; border:1px solid var(--border); border-radius:12px; background:var(--bg-card);">
    <form method="GET" action="/loans" style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap; margin:0;">
        <div style="display:flex; gap:0.5rem; align-items:center;">
            <label style="font-size:0.8rem; font-weight:600; color:var(--text-muted); margin:0;">Start Date:</label>
            <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}" style="width:140px; font-size:0.85rem; padding:0.4rem 0.6rem;">
        </div>

        <div style="display:flex; gap:0.5rem; align-items:center;">
            <label style="font-size:0.8rem; font-weight:600; color:var(--text-muted); margin:0;">End Date:</label>
            <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}" style="width:140px; font-size:0.85rem; padding:0.4rem 0.6rem;">
        </div>

        <div style="display:flex; gap:0.5rem; align-items:center;">
            <label style="font-size:0.8rem; font-weight:600; color:var(--text-muted); margin:0;">Status:</label>
            <select name="status" class="form-control" style="width:140px; font-size:0.85rem; padding:0.4rem 0.6rem;">
                <option value="all">All Statuses</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="settled" {{ request('status') === 'settled' ? 'selected' : '' }}>Settled</option>
                <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
            </select>
        </div>

        <div style="flex:1; min-width:180px;">
            <input type="text" name="search" class="form-control" placeholder="Search lender name or purpose..." value="{{ request('search') }}" style="font-size:0.85rem; padding:0.4rem 0.8rem;">
        </div>

        <div style="display:flex; gap:0.5rem; align-items:center;">
            <button class="btn btn-outline" type="submit" style="padding:0.4rem 1rem; font-size:0.85rem;">
                <ion-icon name="funnel-outline" style="vertical-align:middle;"></ion-icon> Filter
            </button>
            @if(request('start_date') || request('end_date') || (request('status') && request('status') !== 'all') || request('search'))
                <a href="/loans" class="btn btn-outline" style="color:var(--text-muted); padding:0.4rem 1rem; font-size:0.85rem; text-decoration:none;">Reset</a>
            @endif
        </div>
    </form>
</div>

<!-- Loans Table -->
<div class="card" style="padding:0; overflow:visible; background:var(--bg-card); border-radius:12px; border:1px solid var(--border);">
    <table class="data-table" style="margin:0; width:100%; border-collapse:collapse;">
        <thead style="background:var(--bg-page); border-bottom:1px solid var(--border);">
            <tr>
                <th style="padding:0.85rem 1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">Lender & Purpose</th>
                <th style="padding:0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">Principal</th>
                <th style="padding:0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">Total Paid (P+I)</th>
                <th style="padding:0.85rem 1rem; text-align:right; font-size:0.8rem; color:var(--text-muted);">Total Outstanding (P+I)</th>
                <th style="padding:0.85rem 1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">Interest Method</th>
                <th style="padding:0.85rem 1rem; text-align:left; font-size:0.8rem; color:var(--text-muted);">Next Due Date</th>
                <th style="padding:0.85rem 1rem; text-align:center; font-size:0.8rem; color:var(--text-muted);">Status</th>
                <th style="padding:0.85rem 1rem; text-align:center; font-size:0.8rem; color:var(--text-muted);">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($loans as $loan)
            <tr style="border-bottom: 1px solid var(--border-light);">
                <td style="padding:0.85rem 1rem; text-align:left;">
                    <a href="/loans/{{ $loan->id }}" style="font-weight:700; color:var(--text-heading); text-decoration:none; font-size:0.9rem;">
                        {{ $loan->lender_name }}
                    </a>
                    @if($loan->purpose)
                        <div style="font-size:0.78rem; color:var(--text-muted); margin-top:0.1rem;">{{ $loan->purpose }}</div>
                    @endif
                </td>
                <td style="padding:0.85rem 1rem; text-align:right; font-weight:600; color:var(--text-heading); font-size:0.85rem;">
                    <div>{{ $loan->currency }} {{ number_format($loan->principal_amount, 2) }}</div>
                    @if(!empty($loan->is_upfront_interest ?? null))
                        <div style="font-size:0.74rem; color:var(--primary); font-weight:700;" title="Disbursed after upfront interest deduction">
                            Net Recv: {{ number_format($loan->net_disbursed ?? $loan->principal_amount, 2) }}
                        </div>
                    @endif
                </td>
                <td style="padding:0.85rem 1rem; text-align:right;">
                    <span style="color:var(--success); font-weight:800; font-size:0.9rem;">
                        {{ $loan->currency }} {{ number_format($loan->total_paid, 2) }}
                    </span>
                    <div style="font-size:0.75rem; color:var(--text-muted);">
                        Prin: {{ number_format($loan->principal_repaid ?? 0, 2) }} | Int: {{ number_format($loan->interest_paid ?? 0, 2) }}
                    </div>
                </td>
                <td style="padding:0.85rem 1rem; text-align:right;">
                    <span style="color:var(--danger); font-weight:800; font-size:0.9rem;">
                        {{ $loan->currency }} {{ number_format($loan->total_outstanding, 2) }}
                    </span>
                    <div style="font-size:0.75rem; color:var(--text-muted);">
                        Prin: {{ number_format($loan->outstanding_principal, 2) }} | Int: {{ number_format($loan->pending_interest, 2) }}
                    </div>
                </td>
                <td style="padding:0.85rem 1rem; text-align:left;">
                    @if($loan->interest_method === 'fixed_amount')
                        <span class="badge" style="background:#e0e7ff; color:#3730a3; font-size:0.75rem;">Fixed: {{ $loan->currency }} {{ number_format($loan->interest_amount, 2) }}</span>
                    @elseif($loan->interest_method === 'percentage_rate')
                        <span class="badge" style="background:#fce7f3; color:#9d174d; font-size:0.75rem;">Rate: {{ $loan->interest_rate }}% ({{ ucfirst($loan->rate_basis ?? 'flat') }})</span>
                    @elseif($loan->interest_method === 'equal_installments')
                        <span class="badge" style="background:#e0f2fe; color:#0369a1; font-size:0.75rem;">Equal Installments</span>
                    @elseif($loan->interest_method === 'custom_schedule')
                        <span class="badge" style="background:#f3e8ff; color:#6b21a8; font-size:0.75rem;">Custom Schedule</span>
                    @else
                        <span class="text-muted" style="font-size:0.8rem;">No Interest</span>
                    @endif
                </td>
                <td style="padding:0.85rem 1rem; text-align:left;">
                    <div class="font-medium {{ $loan->next_due_date !== 'N/A' && \Carbon\Carbon::parse($loan->next_due_date)->isPast() ? 'text-danger' : '' }}" style="font-size:0.85rem;">
                        {{ $loan->next_due_date }}
                    </div>
                    @if(!empty($loan->is_upfront_interest ?? null))
                        <span class="badge" style="background:#fef3c7; color:#b45309; font-size:0.68rem; margin-top:0.2rem; display:inline-block;">Upfront Int. Paid</span>
                    @endif
                    @if(!empty($loan->maturity_date ?? null) && ($loan->maturity_date ?? null) !== $loan->next_due_date)
                        <div style="font-size:0.72rem; color:var(--text-muted); margin-top:0.15rem;">
                            Maturity: {{ $loan->maturity_date }}
                        </div>
                    @endif
                </td>
                <td style="padding:0.85rem 1rem; text-align:center;">
                    @if($loan->status === 'pending')
                        <span class="badge" style="background:#fef3c7; color:#b45309; font-weight:600;">Pending</span>
                    @elseif($loan->status === 'active')
                        <span class="badge" style="background:var(--primary-light); color:var(--primary); font-weight:600;">Active</span>
                    @elseif($loan->status === 'settled')
                        <span class="badge" style="background:#dcfce7; color:#166534; font-weight:600;">Settled</span>
                    @elseif($loan->status === 'closed')
                        <span class="badge" style="background:#f1f5f9; color:#475569; font-weight:600;">Closed</span>
                    @else
                        <span class="badge" style="background:#fee2e2; color:#991b1b; font-weight:600;">{{ ucfirst($loan->status) }}</span>
                    @endif
                </td>
                <td style="padding:0.85rem 1rem; text-align:center;">
                    <div style="display:flex; justify-content:center; align-items:center; gap:0.4rem;">
                        <a href="/loans/{{ $loan->id }}" class="btn btn-outline" style="padding:0.25rem 0.65rem; font-size:0.8rem; text-decoration:none; border-radius:6px;">
                            View
                        </a>
                        <button type="button" class="action-btn" title="Edit Loan" onclick='openEditLoanModal(@json($loan))' style="background:var(--bg-page); border:1px solid var(--border); border-radius:6px; padding:0.25rem 0.5rem; cursor:pointer; color:var(--primary);">
                            <ion-icon name="create-outline" style="font-size:0.95rem;"></ion-icon>
                        </button>
                        <button type="button" class="action-btn" title="Change Status" onclick="openChangeStatusModal({{ $loan->id }}, '{{ $loan->status }}')" style="background:var(--bg-page); border:1px solid var(--border); border-radius:6px; padding:0.25rem 0.5rem; cursor:pointer; color:var(--text-heading);">
                            <ion-icon name="swap-horizontal-outline" style="font-size:0.95rem;"></ion-icon>
                        </button>
                        <form action="/loans/{{ $loan->id }}" method="POST" style="display:inline; margin:0;" onsubmit="return confirm('Delete this loan and all associated schedule/repayment records?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="action-btn" title="Delete Loan" style="background:var(--bg-page); border:1px solid var(--border); border-radius:6px; padding:0.25rem 0.5rem; cursor:pointer; color:var(--danger);">
                                <ion-icon name="trash-outline" style="font-size:0.95rem;"></ion-icon>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach

            @if($loans->isEmpty())
            <tr>
                <td colspan="8" class="text-center text-muted py-4" style="padding:2.5rem; text-align:center;">
                    <ion-icon name="cash-outline" style="font-size:2.5rem; opacity:0.4; margin-bottom:0.5rem;"></ion-icon><br>
                    No loans found matching the specified criteria.
                </td>
            </tr>
            @endif
        </tbody>
    </table>
</div>
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
                    <label class="form-label">Purpose / Description</label>
                    <input type="text" name="purpose" id="edit_purpose" class="form-control" placeholder="E.g. Capital investment / Equipment purchase">
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
                <div class="glass-card" style="margin-top:1.25rem; padding:1rem; border-radius:10px; background:var(--bg-page); border:1px solid var(--border-light);">
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
                        <label class="form-label" style="font-weight:600;">Loan Full Principal Due Date *</label>
                        <input type="date" name="maturity_date" id="edit_maturity_date" class="form-control" required>
                        <div style="display:flex; gap:0.3rem; margin-top:0.35rem; flex-wrap:wrap;">
                            <button type="button" class="btn btn-outline" style="font-size:0.72rem; padding:0.15rem 0.45rem;" onclick="setMaturityMonths('edit', 1)">+1 Mo</button>
                            <button type="button" class="btn btn-outline" style="font-size:0.72rem; padding:0.15rem 0.45rem;" onclick="setMaturityMonths('edit', 2)">+2 Mo</button>
                            <button type="button" class="btn btn-outline" style="font-size:0.72rem; padding:0.15rem 0.45rem;" onclick="setMaturityMonths('edit', 3)">+3 Mo</button>
                            <button type="button" class="btn btn-outline" style="font-size:0.72rem; padding:0.15rem 0.45rem;" onclick="setMaturityMonths('edit', 6)">+6 Mo</button>
                            <button type="button" class="btn btn-outline" style="font-size:0.72rem; padding:0.15rem 0.45rem;" onclick="setMaturityMonths('edit', 12)">+1 Yr</button>
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
                        <input type="date" name="claimed_date" id="create_claimed_date" class="form-control" value="{{ date('Y-m-d') }}" onchange="autoSetMaturity('create')" required>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Term (Months) *</label>
                        <input type="number" name="term_months" id="create_term_months" class="form-control" value="1" min="1" onchange="autoSetMaturity('create')" required>
                    </div>
                </div>

                <div class="form-group" style="margin-top:1.25rem;">
                    <label class="form-label">Purpose / Description</label>
                    <input type="text" name="purpose" class="form-control" placeholder="E.g. Capital investment / Equipment purchase">
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
                <div class="glass-card" style="margin-top:1.25rem; padding:1rem; border-radius:10px; background:var(--bg-page); border:1px solid var(--border-light);">
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
                        <label class="form-label" style="font-weight:600;">Loan Full Principal Due Date *</label>
                        <input type="date" name="maturity_date" id="create_maturity_date" class="form-control" required>
                        <div style="display:flex; gap:0.3rem; margin-top:0.35rem; flex-wrap:wrap;">
                            <button type="button" class="btn btn-outline" style="font-size:0.72rem; padding:0.15rem 0.45rem;" onclick="setMaturityMonths('create', 1)">+1 Mo</button>
                            <button type="button" class="btn btn-outline" style="font-size:0.72rem; padding:0.15rem 0.45rem;" onclick="setMaturityMonths('create', 2)">+2 Mo</button>
                            <button type="button" class="btn btn-outline" style="font-size:0.72rem; padding:0.15rem 0.45rem;" onclick="setMaturityMonths('create', 3)">+3 Mo</button>
                            <button type="button" class="btn btn-outline" style="font-size:0.72rem; padding:0.15rem 0.45rem;" onclick="setMaturityMonths('create', 6)">+6 Mo</button>
                            <button type="button" class="btn btn-outline" style="font-size:0.72rem; padding:0.15rem 0.45rem;" onclick="setMaturityMonths('create', 12)">+1 Yr</button>
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
                        <label class="form-label">Due Day of Month</label>
                        <input type="number" name="due_day" class="form-control" value="5" min="1" max="31">
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
function openChangeStatusModal(id, currentStatus) {
    document.getElementById('changeStatusForm').action = '/loans/' + id + '/status';
    document.getElementById('modal_status').value = currentStatus;
    openModal('changeStatusModal');
}

function toggleInterestFields(method) {
    document.getElementById('field_fixed_amount').style.display = method === 'fixed_amount' ? 'block' : 'none';
    document.getElementById('field_percentage_rate').style.display = method === 'percentage_rate' ? 'flex' : 'none';
    document.getElementById('field_equal_installments').style.display = method === 'equal_installments' ? 'block' : 'none';
    document.getElementById('field_frequency').style.display = (method === 'no_interest' || method === 'custom_schedule') ? 'none' : 'flex';
}

function toggleEditInterestFields(method) {
    document.getElementById('edit_field_fixed_amount').style.display = method === 'fixed_amount' ? 'block' : 'none';
    document.getElementById('edit_field_percentage_rate').style.display = method === 'percentage_rate' ? 'flex' : 'none';
    document.getElementById('edit_field_equal_installments').style.display = method === 'equal_installments' ? 'block' : 'none';
    const hasFrequency = (method !== 'no_interest' && method !== 'custom_schedule');
    document.getElementById('edit_field_frequency').style.display = hasFrequency ? 'block' : 'none';
    document.getElementById('edit_field_due_day').style.display = hasFrequency ? 'block' : 'none';
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

document.addEventListener('DOMContentLoaded', function() {
    autoSetMaturity('create');
    
    // Live update Net Cash Received on create form changes
    ['create_principal_amount', 'create_interest_amount', 'create_total_interest', 'create_upfront_interest_amount'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', () => calculateNetDisbursed('create'));
            el.addEventListener('blur', () => calculateNetDisbursed('create'));
        }
    });
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
    document.getElementById('edit_purpose').value = loan.purpose || '';
    
    const method = loan.interest_method || 'fixed_amount';
    document.getElementById('edit_interest_method').value = method;
    
    setAmountInputValue('edit_interest_amount', loan.interest_amount);
    document.getElementById('edit_interest_rate').value = loan.interest_rate || '';
    document.getElementById('edit_rate_basis').value = loan.rate_basis || 'flat';
    setAmountInputValue('edit_total_interest', loan.total_interest);
    document.getElementById('edit_frequency').value = loan.frequency || 'monthly';
    document.getElementById('edit_due_day').value = loan.due_day || 5;
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
