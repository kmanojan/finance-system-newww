@extends('layouts.app')
@section('title', 'Repayment Schedules')

@section('secondary-sidebar')
<aside class="sidebar-secondary" id="sidebarSecondary">
    <h2 class="sidebar-title">Loan Management</h2>
    <nav class="nav-links">
        <a href="/loans" class="nav-link {{ request()->is('loans') ? 'active' : '' }}">Active Loans</a>
        <a href="/loans/schedules" class="nav-link {{ request()->is('loans/schedules') ? 'active' : '' }}">Schedules</a>
        <a href="/loans/settlements" class="nav-link {{ request()->is('loans/settlements') ? 'active' : '' }}">Settlements</a>
    </nav>
</aside>
@endsection

@section('content')
<header class="page-header" style="margin-bottom: 2rem;">
    <div class="header-titles">
        <h1>Repayment Schedules</h1>
        <p class="subtitle">All upcoming and past repayment schedules across all active loans.</p>
    </div>
</header>

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
                            <a href="#" onclick="openSettleModal({{ $sched->loan_id }}, {{ $sched->id }}, {{ $sched->interest_amount - $sched->paid_amount }})">Settle Payment</a>
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
                    <input type="date" name="paid_date" class="form-control" value="{{ date('Y-m-d') }}" required>
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

function openSettleModal(loanId, schedId, suggestedAmount) {
    document.getElementById('settleForm').action = "/loans/" + loanId + "/schedule/" + schedId + "/settle";
    document.getElementById('settle_amount').nextElementSibling.value = suggestedAmount;
    if (typeof formatAmountBlur === 'function') formatAmountBlur(document.getElementById('settle_amount'));
    openModal('settleModal');
}

function openEditModal(loanId, schedId, currentAmount) {
    document.getElementById('editAmountForm').action = "/loans/" + loanId + "/schedule/" + schedId + "/edit";
    document.getElementById('edit_interest_amount').nextElementSibling.value = currentAmount;
    if (typeof formatAmountBlur === 'function') formatAmountBlur(document.getElementById('edit_interest_amount'));
    openModal('editAmountModal');
}
</script>
@endsection
