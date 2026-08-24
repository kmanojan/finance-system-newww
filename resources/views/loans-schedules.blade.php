@extends('layouts.app')
@section('title', 'Repayment Schedules')

@section('secondary-sidebar')
<aside class="sidebar-secondary" id="sidebarSecondary">
    <h2 class="sidebar-title">Loan Management</h2>
    <nav class="nav-links">
        <a href="/loans" class="nav-link {{ request()->is('loans') ? 'active' : '' }}">Active Loans</a>
        <a href="/loans/schedules" class="nav-link {{ request()->is('loans/schedules') ? 'active' : '' }}">Schedules</a>
        <a href="/loans/settlements" class="nav-link {{ request()->is('loans/settlements') ? 'active' : '' }}">Settlements</a>
        <a href="/loans/party-report" class="nav-link {{ request()->is('loans/party-report') ? 'active' : '' }}">Party Payables & Paids</a>
    </nav>
</aside>

@endsection

@section('content')
<header class="page-header" style="margin-bottom: 1.5rem;">
    <div class="header-titles">
        <h1 style="font-size:1.75rem; font-weight:800; color:var(--text-heading); margin:0;">Repayment Schedules</h1>
        <p class="subtitle" style="margin-top:0.3rem;">All upcoming and past repayment schedules across all active loans.</p>
    </div>
</header>

<!-- KPI Stat Tiles -->
<div class="metric-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; margin-bottom:1.5rem;">
    <!-- Tile 1: Total Scheduled -->
    <div class="metric-card" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; opacity:0.9;">Total Scheduled</h3>
            <ion-icon name="calendar-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.4rem; font-weight:800; margin-top:0.3rem;">LKR {{ number_format($totalScheduledInterest, 2) }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">{{ $schedules->count() }} Total Schedule Installments</div>
    </div>

    <!-- Tile 2: Settled / Paid -->
    <div class="metric-card" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; opacity:0.9;">Interest Paid</h3>
            <ion-icon name="checkmark-circle-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.4rem; font-weight:800; margin-top:0.3rem;">LKR {{ number_format($totalPaidInterest, 2) }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Settled interest to date</div>
    </div>

    <!-- Tile 3: Pending / Due -->
    <div class="metric-card" style="background: linear-gradient(135deg, #dc2626 0%, #f43f5e 100%); padding: 1.25rem; border-radius: 12px; color: white;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:0.8rem; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; opacity:0.9;">Pending Due</h3>
            <ion-icon name="time-outline" style="font-size:1.3rem; opacity:0.85;"></ion-icon>
        </div>
        <div class="value" style="font-size:1.4rem; font-weight:800; margin-top:0.3rem;">LKR {{ number_format($totalPendingInterest, 2) }}</div>
        <div style="font-size:0.75rem; opacity:0.85; margin-top:0.2rem;">Remaining balance to settle</div>
    </div>
</div>

<!-- Filter Toolbar -->
<div class="card" style="padding:1rem 1.25rem; margin-bottom:1.5rem; border:1px solid var(--border); border-radius:12px; background:var(--bg-card);">
    <form method="GET" action="/loans/schedules" style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap; margin:0;">
        <div style="display:flex; gap:0.5rem; align-items:center;">
            <label style="font-size:0.8rem; font-weight:600; color:var(--text-muted); margin:0;">Party / Lender:</label>
            <select name="party_id" class="form-control" style="width:auto; min-width:180px; font-size:0.85rem; padding:0.4rem 0.6rem;">
                <option value="all">All Parties / Lenders</option>
                @foreach($parties as $p)
                    <option value="{{ $p->id }}" {{ request('party_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>

        <div style="display:flex; gap:0.5rem; align-items:center;">
            <label style="font-size:0.8rem; font-weight:600; color:var(--text-muted); margin:0;">Start Date:</label>
            <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}" style="width:145px; font-size:0.85rem; padding:0.4rem 0.6rem;">
        </div>

        <div style="display:flex; gap:0.5rem; align-items:center;">
            <label style="font-size:0.8rem; font-weight:600; color:var(--text-muted); margin:0;">End Date:</label>
            <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}" style="width:145px; font-size:0.85rem; padding:0.4rem 0.6rem;">
        </div>

        <div style="display:flex; gap:0.5rem; align-items:center;">
            <label style="font-size:0.8rem; font-weight:600; color:var(--text-muted); margin:0;">Status:</label>
            <select name="status" class="form-control" style="width:auto; font-size:0.85rem; padding:0.4rem 0.6rem;">
                <option value="all" {{ request('status') === 'all' || !request('status') ? 'selected' : '' }}>All Statuses</option>
                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="partially_paid" {{ request('status') === 'partially_paid' ? 'selected' : '' }}>Partially Paid</option>
                <option value="paid" {{ request('status') === 'paid' ? 'selected' : '' }}>Paid</option>
                <option value="overdue" {{ request('status') === 'overdue' ? 'selected' : '' }}>Overdue</option>
            </select>
        </div>

        <div style="display:flex; gap:0.5rem; align-items:center;">
            <button class="btn btn-outline" type="submit" style="padding:0.4rem 1rem; font-size:0.85rem;">
                <ion-icon name="funnel-outline" style="vertical-align:middle;"></ion-icon> Filter
            </button>
            @if(request('party_id') || request('start_date') || request('end_date') || request('status'))
                <a href="/loans/schedules" class="btn btn-outline" style="color:var(--text-muted); padding:0.4rem 1rem; font-size:0.85rem; text-decoration:none;">Reset</a>
            @endif
        </div>
    </form>
</div>

<div class="card" style="padding:0; overflow-x: auto;">
    <table class="data-table" style="margin:0; width:100%;">
        <thead>
            <tr>
                <th>Due Date</th>
                <th>Lender</th>
                <th>Amount Due</th>
                <th>Paid Amount</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($schedules as $sched)
            <tr>
                <td data-label="Due Date"><span class="font-medium {{ \Carbon\Carbon::parse($sched->due_date)->isPast() && in_array($sched->status, ['pending', 'partially_paid']) ? 'text-danger' : '' }}">{{ $sched->due_date }}</span></td>
                <td data-label="Lender"><a href="/loans/{{ $sched->loan_id }}" style="text-decoration:none; color:var(--primary); font-weight:600;">{{ $sched->lender_name }}</a></td>
                <td data-label="Amount Due">{{ $sched->currency }} {{ number_format($sched->interest_amount, 2) }}</td>
                <td data-label="Paid Amount">{{ $sched->currency }} {{ number_format($sched->paid_amount, 2) }}</td>
                <td data-label="Status">
                    @if($sched->status === 'paid')
                        <span class="badge" style="background:#dcfce7;color:#166534;">Paid</span>
                    @elseif($sched->status === 'skipped')
                        <span class="badge" style="background:#f1f5f9;color:#475569;">Skipped</span>
                    @elseif($sched->status === 'partially_paid')
                        <span class="badge" style="background:#fef3c7;color:#b45309;">Partial</span>
                    @elseif(\Carbon\Carbon::parse($sched->due_date)->isPast())
                        <span class="badge" style="background:#fee2e2;color:#991b1b;">Overdue</span>
                    @else
                        <span class="badge badge-draft">Pending</span>
                    @endif
                </td>
                <td data-label="Actions">
                    @if(in_array($sched->status, ['pending', 'partially_paid', 'overdue']))
                    <div class="dropdown">
                        <button class="action-btn" onclick="toggleDropdown('sched-actions-{{ $sched->id }}')"><ion-icon name="ellipsis-vertical"></ion-icon></button>
                        <div class="dropdown-menu" id="sched-actions-{{ $sched->id }}">
                            <a href="#" onclick="openSettleModal({{ $sched->loan_id }}, {{ $sched->id }}, {{ $sched->interest_amount - $sched->paid_amount }}, '{{ $sched->due_date }}')">Settle Payment</a>
                            <a href="#" onclick="openEditModal({{ $sched->loan_id }}, {{ $sched->id }}, {{ $sched->interest_amount }})">Edit Amount</a>
                            <form action="/loans/{{ $sched->loan_id }}/schedule/{{ $sched->id }}/skip" method="POST" style="margin:0;">
                                @csrf
                                <button type="submit" style="width:100%; text-align:left; background:none; border:none; padding:8px 12px; cursor:pointer;">Mark Not Needed</button>
                            </form>
                        </div>
                    </div>
                    @endif
                </td>
            </tr>
            @endforeach
            @if($schedules->isEmpty())
            <tr><td colspan="6" class="text-center text-muted py-4">No schedules found.</td></tr>
            @endif
        </tbody>
    </table>
</div>
@endsection

@section('modals')
<!-- Settle Interest Modal -->
<div class="modal-backdrop" id="settleModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Settle Interest</h3>
            <button class="btn-close" onclick="closeModal('settleModal')">&times;</button>
        </div>
        <form id="settleForm" method="POST">
            @csrf
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Amount Paid</label>
                    <x-amount-input name="paid_amount" id="settle_amount" required="true" />
                </div>
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Date Paid</label>
                    <input type="date" name="paid_date" id="sched_settle_paid_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Withholding Tax (WHT)</label>
                    <x-tax-selector name="wht_type_id" category="wht" appliesTo="loan_interest" />
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('settleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Record Payment</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Amount Modal -->
<div class="modal-backdrop" id="editAmountModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Override Interest Amount</h3>
            <button class="btn-close" onclick="closeModal('editAmountModal')">&times;</button>
        </div>
        <form id="editAmountForm" method="POST">
            @csrf
            <div class="modal-body">
                <p class="text-muted" style="margin-top:0;">Change the calculated/fixed interest amount for this specific period only.</p>
                <div class="form-group">
                    <label class="form-label">New Amount</label>
                    <x-amount-input name="interest_amount" id="edit_interest_amount" required="true" />
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('editAmountModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Update Amount</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleDropdown(id) {
    const el = document.getElementById(id);
    el.classList.toggle('show');
}
window.onclick = function(event) {
    if (!event.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu.show').forEach(el => el.classList.remove('show'));
    }
}

function openSettleModal(loanId, schedId, suggestedAmount, dueDate) {
    document.getElementById('settleForm').action = "/loans/" + loanId + "/schedule/" + schedId + "/settle";
    if (typeof setAmountInputValue === 'function') {
        setAmountInputValue('settle_amount', suggestedAmount);
    } else {
        document.getElementById('settle_amount').nextElementSibling.value = suggestedAmount;
        if (typeof formatAmountBlur === 'function') formatAmountBlur(document.getElementById('settle_amount'));
    }
    const dateInput = document.getElementById('sched_settle_paid_date') || document.querySelector('#settleModal input[name="paid_date"]');
    if (dateInput && dueDate) {
        dateInput.value = dueDate;
    }
    openModal('settleModal');
}

function openEditModal(loanId, schedId, currentAmount) {
    document.getElementById('editAmountForm').action = "/loans/" + loanId + "/schedule/" + schedId + "/edit";
    if (typeof setAmountInputValue === 'function') {
        setAmountInputValue('edit_interest_amount', currentAmount);
    } else {
        document.getElementById('edit_interest_amount').nextElementSibling.value = currentAmount;
        if (typeof formatAmountBlur === 'function') formatAmountBlur(document.getElementById('edit_interest_amount'));
    }
    openModal('editAmountModal');
}
</script>
@endsection
