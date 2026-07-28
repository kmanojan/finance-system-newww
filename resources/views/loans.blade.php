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
    <button class="btn btn-primary-gradient btn-pill mobile-hide" onclick="openModal('createLoanModal')">
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
                    {{ $loan->currency }} {{ number_format($loan->principal_amount, 2) }}
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
                    <span class="font-medium {{ $loan->next_due_date !== 'N/A' && \Carbon\Carbon::parse($loan->next_due_date)->isPast() ? 'text-danger' : '' }}" style="font-size:0.85rem;">
                        {{ $loan->next_due_date }}
                    </span>
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
                        <button type="button" class="action-btn" title="Change Status" onclick="openChangeStatusModal({{ $loan->id }}, '{{ $loan->status }}')" style="background:var(--bg-page); border:1px solid var(--border); border-radius:6px; padding:0.25rem 0.5rem; cursor:pointer; color:var(--text-heading);">
                            <ion-icon name="create-outline" style="font-size:0.95rem;"></ion-icon>
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
                        <x-amount-input name="principal_amount" required="true" />
                    </div>
                </div>


                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col">
                        <label class="form-label">Currency</label>
                        <x-currency-selector name="currency" selected="LKR" required />
                    </div>
                    <div class="form-col">
                        <label class="form-label">Claimed / Start Date</label>
                        <input type="date" name="claimed_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                </div>

                <div class="form-group" style="margin-top:1.25rem;">
                    <label class="form-label">Purpose / Description</label>
                    <input type="text" name="purpose" class="form-control" placeholder="E.g. Capital investment / Equipment purchase">
                </div>

                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col">
                        <label class="form-label">Interest Calculation Method</label>
                        <select name="interest_method" id="create_interest_method" class="form-control" onchange="toggleInterestFields(this.value)" required>
                            <option value="fixed_amount">Fixed Amount per Period</option>
                            <option value="percentage_rate">Percentage Rate (%)</option>
                            <option value="equal_installments">Equal Installments</option>
                            <option value="custom_schedule">Custom Schedule</option>
                            <option value="no_interest">No Interest</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Term (Months)</label>
                        <input type="number" name="term_months" class="form-control" value="12" min="1" required>
                    </div>
                </div>

                <!-- Method Dynamic Fields -->
                <div id="field_fixed_amount" class="form-group" style="margin-top:1.25rem;">
                    <label class="form-label">Fixed Interest Amount per Period</label>
                    <x-amount-input name="interest_amount" />
                </div>

                <div id="field_percentage_rate" class="form-row" style="margin-top:1.25rem; display:none;">
                    <div class="form-col">
                        <label class="form-label">Interest Rate (%)</label>
                        <input type="number" step="0.01" name="interest_rate" class="form-control" placeholder="E.g. 10.5">
                    </div>
                    <div class="form-col">
                        <label class="form-label">Rate Basis</label>
                        <select name="rate_basis" class="form-control">
                            <option value="flat">Flat Interest Rate</option>
                            <option value="reducing">Reducing Balance</option>
                        </select>
                    </div>
                </div>

                <div id="field_equal_installments" class="form-group" style="margin-top:1.25rem; display:none;">
                    <label class="form-label">Total Agreed Interest Amount</label>
                    <x-amount-input name="total_interest" />
                </div>

                <div id="field_frequency" class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col">
                        <label class="form-label">Interest Payment Frequency</label>
                        <select name="frequency" class="form-control">
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Due Day of Month</label>
                        <input type="number" name="due_day" class="form-control" value="5" min="1" max="31">
                    </div>
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
</script>
@endsection
