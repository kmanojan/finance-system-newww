@extends('layouts.app')
@section('title', 'Loan Facility: ' . $loan->lender_name . ' (' . ($loan->loan_code ?: 'LN-' . $loan->id) . ')')
@section('meta_description', 'Detailed overview, repayment schedules, interest breakdowns, and settlement history for loan ' . $loan->lender_name)

@section('secondary-sidebar')
@include('navigation.loans')
@endsection

@section('content')
<link rel="stylesheet" href="{{ asset('loan-detail.css') }}?v={{ filemtime(public_path('loan-detail.css')) }}">
<script defer src="{{ asset('loan-detail.js') }}?v={{ filemtime(public_path('loan-detail.js')) }}"></script>
<link rel="stylesheet" href="{{ asset('loan-flow.css') }}?v={{ filemtime(public_path('loan-flow.css')) }}">
<script defer src="{{ asset('loan-flow.js') }}?v={{ filemtime(public_path('loan-flow.js')) }}"></script>

@php
    $totalDraws = $principalRecords->where('record_type', 'draw')->sum('amount');
    $totalRepaid = $balance['principal_repaid'];
    $totalBorrowed = $balance['total_borrowed'];
    $totalInterestPaid = $balance['interest_paid'];
    $totalInterestDue = $balance['pending_interest'];
    $totalContractedInterest = $balance['contracted_interest'];
    $totalLoanObligation = $balance['total_obligation'];
    $totalPaidAll = $balance['total_paid'];
    $totalRemainingPayable = $balance['total_outstanding'];
    $overallRepaymentPct = $balance['repayment_percent'];
    $canManageLoan = auth()->user()->isAdmin() || in_array(auth()->user()->role, ['manager', 'accountant']);
    $loanCode = $loan->loan_code ?: 'LN-'.str_pad($loan->id, 4, '0', STR_PAD_LEFT);
    $statusLabel = match ($loan->status) { 'pending' => 'Pending activation', 'defaulted' => 'Defaulted', 'closed' => 'Closed', 'settled' => 'Settled', default => 'Active' };
    $interestMethodLabel = match ($loan->interest_method) { 'fixed_amount' => 'Fixed amount', 'percentage_rate' => 'Percentage · '.ucfirst($loan->rate_basis ?? 'flat'), 'equal_installments', 'total_interest' => 'Equal installments', 'custom_schedule' => 'Custom schedule', default => 'No interest' };
@endphp

<div class="loan-detail-page">
    @include('loans-summary')
    <div class="loan-workspace">
        <div class="loan-tabs" role="tablist" aria-label="Loan information">
            <button type="button" id="tab-schedule" role="tab" aria-controls="content-schedule" aria-selected="true" tabindex="0" data-loan-tab="schedule"><ion-icon name="calendar-outline" aria-hidden="true"></ion-icon> Interest Schedule <span>{{ $schedules->count() }}</span></button>
            <button type="button" id="tab-principal" role="tab" aria-controls="content-principal" aria-selected="false" tabindex="-1" data-loan-tab="principal"><ion-icon name="swap-vertical-outline" aria-hidden="true"></ion-icon> Principal Records <span>{{ $principalRecords->count() }}</span></button>
            <button type="button" id="tab-flow" role="tab" aria-controls="content-flow" aria-selected="false" tabindex="-1" data-loan-tab="flow"><ion-icon name="git-network-outline" aria-hidden="true"></ion-icon> Loan Flow <span>{{ max(0, $loanFlow['loans']->count() - 1) }}</span></button>
            <button type="button" id="tab-details" role="tab" aria-controls="content-details" aria-selected="false" tabindex="-1" data-loan-tab="details"><ion-icon name="document-text-outline" aria-hidden="true"></ion-icon> Loan Details</button>
        </div>
        <!-- TAB 1: Interest Schedule -->
        <section id="content-schedule" class="loan-tab-panel" role="tabpanel" aria-labelledby="tab-schedule" tabindex="0">
            <div class="loan-panel-heading"><div><h2>Interest schedule</h2><p>Track each interest period, payment, and remaining amount.</p></div>
 @if($canManageLoan && !in_array($loan->status, ['settled', 'closed']))<button type="button" class="btn btn-outline" onclick="openModal('addScheduleModal')"><ion-icon name="add-outline" aria-hidden="true"></ion-icon> Add interest row</button>@endif</div>
<div class="loan-records-table">
                <table class="data-table loan-schedule-table">
                    <caption class="sr-only">Interest schedule</caption>
                    <thead>
                        <tr>
                            <th>Due Date</th>
                            <th>Amount Due</th>
                            <th>Paid Amount</th>
                            <th>Remaining</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($schedules as $sched)
                        @php
                            $paidAmt = $sched->paid_amount ?? 0;
                            $remAmt = max(0, $sched->interest_amount - $paidAmt);
                        @endphp
                        <tr>
                            <td>
                                <span class="font-medium {{ \Carbon\Carbon::parse($sched->due_date)->isPast() && in_array($sched->status, ['pending', 'partially_paid']) ? 'text-danger' : '' }}">
                                    {{ $sched->due_date }}
                                </span>
                            </td>
                            <td>
                                {{ $loan->currency }} {{ number_format($sched->interest_amount, 2) }}
                            </td>
                            <td>
                                {{ $loan->currency }} {{ number_format($paidAmt, 2) }}
                            </td>
                            <td>
                                {{ $loan->currency }} {{ number_format($remAmt, 2) }}
                            </td>
                            <td>
                                @if($sched->status === 'paid')
                                    <span class="loan-state is-settled">Paid</span>
                                @elseif($sched->status === 'skipped')
                                    <span class="loan-state">Skipped</span>
                                @elseif($sched->status === 'partially_paid')
                                    <span class="loan-state is-pending">Partially paid</span>
                                @elseif(\Carbon\Carbon::parse($sched->due_date)->isPast())
                                    <span class="loan-state is-defaulted">Overdue</span>
                                @else
                                    <span class="loan-state">Pending</span>
                                @endif
                            </td>
                            <td>
                                @if($canManageLoan && in_array($sched->status, ['pending', 'partially_paid', 'overdue']))
                                <div class="dropdown">
                                    <button type="button" class="action-btn" aria-label="Interest schedule actions" onclick="toggleDropdown('sched-actions-{{ $sched->id }}', event)">
                                        <ion-icon name="ellipsis-horizontal-outline"></ion-icon>
                                    </button>
                                    <div class="dropdown-menu" id="sched-actions-{{ $sched->id }}">
                                        <a href="javascript:void(0)" onclick="openSettleModal({{ $sched->id }}, {{ $remAmt }}, '{{ $sched->due_date }}')">
                                            <ion-icon name="cash-outline"></ion-icon> Settle Payment
                                        </a>
                                        <a href="javascript:void(0)" onclick="openEditInterestModal({{ $sched->id }}, {{ $sched->interest_amount }}, '{{ $sched->due_date }}')">
                                            <ion-icon name="create-outline"></ion-icon> Edit Schedule (Date & Amount)
                                        </a>
                                        <form action="/loans/{{ $loan->id }}/schedule/{{ $sched->id }}/skip" method="POST">
                                            @csrf
                                            <button type="submit">
                                                <ion-icon name="close-circle-outline"></ion-icon> Mark Not Needed
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                @else
                                    <span class="text-muted">{{ in_array($sched->status, ['pending', 'partially_paid', 'overdue']) ? 'View only' : 'Completed' }}</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach

                        @if($schedules->isEmpty())
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <ion-icon name="calendar-outline"></ion-icon><br>
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
        </section>

        <!-- TAB 2: Principal Records -->
        <section id="content-principal" class="loan-tab-panel" role="tabpanel" aria-labelledby="tab-principal" tabindex="0" hidden>
            <div class="loan-panel-heading"><div><h2>Principal records</h2><p>Repayments reduce the principal. Additional draws increase it.</p></div>
 @if($canManageLoan && $loan->status === 'active')<div class="loan-panel-actions"><button type="button" class="btn btn-outline" onclick="openModal('addDrawModal')"><ion-icon name="add-outline" aria-hidden="true"></ion-icon> Add draw</button><button type="button" class="btn btn-primary-gradient" onclick="openModal('recordRepaymentModal')"><ion-icon name="arrow-down-outline" aria-hidden="true"></ion-icon> Repay principal</button></div>@endif</div>
<div class="loan-records-table">
                <table class="data-table loan-principal-table">
                    <caption class="sr-only">Principal repayments and additional draws</caption>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Payment Mode</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($principalRecords as $record)
                        <tr>
                            <td>{{ $record->record_date }}</td>
                            <td>
                                @if($record->record_type === 'repayment')
                                    <span>
                                        <ion-icon name="arrow-down-circle-outline"></ion-icon> Principal Repayment
                                    </span>
                                @else
                                    <span>
                                        <ion-icon name="arrow-up-circle-outline"></ion-icon> Additional Draw
                                    </span>
                                @endif
                            </td>
                            <td>
                                {{ $loan->currency }} {{ number_format($record->amount, 2) }}
                            </td>
                            <td>
                                <span class="badge">
                                    {{ $record->payment_mode ?? 'Default' }}
                                </span>
                            </td>
                            <td class="text-muted">{{ $record->notes ?? '-' }}</td>
                        </tr>
                        @endforeach

                        @if($principalRecords->isEmpty())
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <ion-icon name="swap-vertical-outline"></ion-icon><br>
                                No principal repayments or draw records logged yet.
                            </td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </section>

        <section id="content-flow" class="loan-tab-panel" role="tabpanel" aria-labelledby="tab-flow" tabindex="0" hidden>
            @include('loans-flow')
        </section>
        <section id="content-details" class="loan-tab-panel" role="tabpanel" aria-labelledby="tab-details" tabindex="0" hidden>
            @include('loans-details-panel')
        </section>
    </div>
</div>

@endsection

@section('modals')
<!-- Settle loan fully Modal -->
<div class="modal-backdrop" id="settlePrincipalFullyModal">
    <div class="modal-card" style="max-width:540px;">
        <div class="modal-header">
            <h3 class="modal-title" style="display:flex; align-items:center; gap:0.5rem;">
                <ion-icon name="checkmark-done-circle-outline" style="color:var(--success); font-size:1.4rem;"></ion-icon> Settle loan fully
            </h3>
            <button type="button" class="btn-close" onclick="closeModal('settlePrincipalFullyModal')">&times;</button>
        </div>
        <form action="/loans/{{ $loan->id }}/settle-fully" method="POST">
            @csrf
            <div class="modal-body">
                <x-loan-funding-selector :fundingLoans="$fundingLoans" id="settle-funding-loan" />

                <!-- Outstanding Balance Overview Banner -->
                <div style="background:var(--primary-light); border:1px solid var(--border-light); border-radius:10px; padding:1.1rem 1.25rem; margin-bottom:1.25rem; text-align:center;">
                    <span style="font-size:0.75rem; font-weight:700; text-transform:uppercase; color:var(--text-muted); letter-spacing:0.5px; display:block;">Full settlement amount</span>
                    <div style="font-size:1.65rem; font-weight:800; color:var(--text-heading); margin-top:0.25rem;">
                        {{ $loan->currency }} {{ number_format($balance['full_settlement_amount'], 2) }}
                    </div>
                    <span style="font-size:0.75rem; color:var(--text-muted); margin-top:0.2rem; display:block;">
                        Pays remaining principal {{ number_format($loan->outstanding_principal, 2) }} plus unpaid interest {{ number_format($totalInterestDue, 2) }}. Interest is included in full closure for both loan types.
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
                    <label class="form-label" style="font-weight:700;"><ion-icon name="card-outline" style="vertical-align:middle;"></ion-icon> Deduct from Bank Account</label>
                    <x-bank-account-selector name="bank_account_id" id="settle_bank_account_id" :bankAccounts="$bankAccounts" :selected="$loan->bank_account_id" placeholder="Select Bank Account..." />
                </div>

                <div class="form-group" style="margin-top:1.25rem;">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control" value="Full Principal Settlement" placeholder="E.g. Final principal clearance">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('settlePrincipalFullyModal')">Cancel</button>
                <button type="submit" class="btn btn-primary-gradient">Confirm full settlement</button>
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
        <form action="/loans/{{ $loan->id }}" method="POST" enctype="multipart/form-data" data-loan-edit data-terms-locked="{{ $loan->status !== 'pending' ? 'true' : 'false' }}">
            @csrf
            @method('PUT')
            <div class="modal-body">
                @if($loan->status !== 'pending')
                    <x-loan-correction-note />
                @endif

                <x-loan-type-selector id="edit_loan_type" :value="$balance['loan_type']" />
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
                        <label class="form-label" style="font-weight:700;"><ion-icon name="card-outline" style="vertical-align:middle;"></ion-icon> Disbursement / Settlement Bank Account</label>
                        <x-bank-account-selector name="bank_account_id" id="show_edit_bank_account_id" :bankAccounts="$bankAccounts" :selected="$loan->bank_account_id" placeholder="Select Bank Account..." />
                    </div>
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
                    <x-loan-attachments label="Add More Attachments" />
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
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label"><ion-icon name="card-outline" style="vertical-align:middle;"></ion-icon> Deduct from Bank Account</label>
                    <x-bank-account-selector name="bank_account_id" id="settle_interest_bank_account_id" :bankAccounts="$bankAccounts" :selected="$loan->bank_account_id" placeholder="Select Bank Account..." />
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
                <x-loan-funding-selector :fundingLoans="$fundingLoans" id="repay-funding-loan" />

                <div class="form-group">
                    <label class="form-label">Repayment Amount ({{ $loan->currency }})</label>
                    <x-amount-input name="amount" required="true" />
                </div>
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Date</label>
                    <input type="date" name="record_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label"><ion-icon name="card-outline" style="vertical-align:middle;"></ion-icon> Deduct from Bank Account</label>
                    <x-bank-account-selector name="bank_account_id" id="repay_bank_account_id" :bankAccounts="$bankAccounts" :selected="$loan->bank_account_id" placeholder="Select Bank Account..." />
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
                    <label class="form-label"><ion-icon name="card-outline" style="vertical-align:middle;"></ion-icon> Deposit into Bank Account</label>
                    <x-bank-account-selector name="bank_account_id" id="draw_bank_account_id" :bankAccounts="$bankAccounts" :selected="$loan->bank_account_id" placeholder="Select Bank Account..." />
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
        const anchor = event?.currentTarget?.getBoundingClientRect();
        if (anchor) {
            el.style.position = 'fixed';
            el.style.right = 'auto';
            el.style.left = Math.max(12, Math.min(anchor.right - el.offsetWidth, innerWidth - el.offsetWidth - 12)) + 'px';
            el.style.top = (anchor.bottom + el.offsetHeight + 12 < innerHeight ? anchor.bottom + 6 : Math.max(12, anchor.top - el.offsetHeight - 6)) + 'px';
        }
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
