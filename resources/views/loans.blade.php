@extends('layouts.app')
@section('title', 'Loans Management')
@section('meta_description', 'Track, manage, and settle company loan facilities, repayment schedules, interest calculations, and lender obligations.')

@section('secondary-sidebar')
@include('navigation.loans')

@endsection

@section('content')
<link rel="stylesheet" href="{{ asset('loan-index.css') }}?v={{ filemtime(public_path('loan-index.css')) }}">
@php
    $currStatus = request('status', 'all');
    $queryParams = request()->except(['status', 'page']);
    $hasFilters = request()->filled('party_id') || request()->filled('search') || request()->filled('start_date') || request()->filled('end_date') || request()->filled('from') || request()->filled('to') || $currStatus !== 'all';
@endphp
<div class="li-workspace">
    <header class="li-header">
        <div><h1>Third-party loans</h1><p>Borrowings, repayments and the balance still to settle.</p></div>
        <button type="button" class="li-button li-primary" onclick="openModal('createLoanModal')"><ion-icon name="add-outline" aria-hidden="true"></ion-icon> Record loan</button>
    </header>

    <section class="li-overview" aria-label="Loan balances for current filters">
        <div class="li-balance">
            <span>Total to settle</span>
            <strong><small>LKR</small> {{ number_format($totalToSettle, 2) }}</strong>
            <dl><div><dt>Unpaid principal:</dt><dd>{{ number_format($totalOutstandingPrincipal, 2) }}</dd></div><div><dt>Unpaid interest:</dt><dd>{{ number_format($totalOutstandingInterest, 2) }}</dd></div></dl>
            <details class="li-balance-help"><summary>Payable balance by loan type <ion-icon name="help-circle-outline" aria-hidden="true"></ion-icon></summary><p>LKR {{ number_format($totalWantToPaid, 2) }}. Type 1 excludes scheduled interest from payable; type 2 includes it. Total to settle includes both unpaid principal and scheduled interest.</p></details>
        </div>
        <dl class="li-metrics">
            <div><dt>Total borrowed</dt><dd><small>LKR</small> {{ number_format($totalBorrowed, 2) }}</dd><p>{{ $totalLoansCount }} {{ Str::plural('loan', $totalLoansCount) }} · {{ $activeLoansCount }} active</p></div>
            <div><dt>Total paid</dt><dd><small>LKR</small> {{ number_format($totalPaidAll, 2) }}</dd><p>Principal {{ number_format($totalPrincipalRepaid, 2) }}<br>Interest {{ number_format($totalInterestPaid, 2) }}</p></div>
            <div><dt>Interest due · {{ now()->format('M Y') }}</dt><dd><small>LKR</small> {{ number_format($thisMonthPayable, 2) }}</dd><p>Unpaid scheduled interest this month</p></div>
        </dl>
    </section>

    <section class="li-register" aria-labelledby="loan-register-title">
        <header class="li-register-header"><div><h2 id="loan-register-title">Loan register</h2><p>{{ $loans->total() }} {{ Str::plural('loan', $loans->total()) }}{{ $hasFilters ? ' matching your filters' : ' in total' }} · Balances reflect {{ $hasFilters ? 'these filters' : 'all loans' }}</p></div>
            @if($loans->count())<button type="button" class="li-button li-quiet" onclick="toggleAllAccordions()" id="btnToggleAll" aria-expanded="false" aria-controls="loansAccordionContainer"><ion-icon name="list-outline" aria-hidden="true"></ion-icon><span id="toggleAllText">Expand details</span></button>@endif
        </header>
        <nav class="li-tabs" aria-label="Filter loans by status">
            @foreach(['all'=>'All loans', 'active'=>'Active', 'pending'=>'Pending', 'settled'=>'Settled', 'closed'=>'Closed'] as $status => $label)
                <a href="/loans?{{ http_build_query(array_merge($queryParams, ['status'=>$status])) }}" @if($currStatus === $status) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        <form method="GET" action="/loans" class="li-filters" role="search" aria-label="Search and filter loans">
            <input type="hidden" name="status" value="{{ $currStatus }}">
            <div class="li-field li-search"><label for="loan-search">Search loans</label><div><ion-icon name="search-outline" aria-hidden="true"></ion-icon><input id="loan-search" name="search" type="search" placeholder="Loan code, lender or purpose" value="{{ request('search') }}"></div></div>
            <details class="li-more-filters" @if(request('party_id') || request('start_date') || request('end_date') || request('from') || request('to')) open @endif>
                <summary><ion-icon name="options-outline" aria-hidden="true"></ion-icon> Filters</summary>
                <div class="li-filter-fields">
                    <div class="li-field li-party" role="group" aria-labelledby="loan-party-label"><span id="loan-party-label" class="li-field-label">Party / lender</span><x-party-selector id="loan_filter_party" name="party_id" :parties="$parties" :selected="request('party_id')" placeholder="All parties / lenders" /></div>
                    <div class="li-field"><label for="loan-from">Borrowed from</label><input id="loan-from" type="date" name="start_date" value="{{ request('start_date', request('from')) }}"></div>
                    <div class="li-field"><label for="loan-to">Borrowed to</label><input id="loan-to" type="date" name="end_date" value="{{ request('end_date', request('to')) }}"></div>
                </div>
            </details>
            <button type="submit" class="li-button li-apply"><ion-icon name="search-outline" aria-hidden="true"></ion-icon><span>Apply</span></button>
            @if($hasFilters)<a href="/loans" class="li-clear">Clear filters</a>@endif
        </form>
        <div class="li-column-head" aria-hidden="true"><span>Loan / lender</span><span>Initial principal</span><span>Total to settle</span><span>Next due / settled</span><span>Actions</span></div>
        <div id="loansAccordionContainer">
            @forelse($loans as $loan)
                @php
                    $code = $loan->loan_code ?: ('LN-' . str_pad($loan->id, 4, '0', STR_PAD_LEFT));
                    $isOverdue = $loan->next_due_date !== 'N/A' && \Carbon\Carbon::parse($loan->next_due_date)->startOfDay()->lt(today()) && $loan->status === 'active';
                    $principalRepaidPct = $loan->total_borrowed > 0 ? min(100, round(($loan->principal_repaid / $loan->total_borrowed) * 100)) : 0;
                @endphp
                <article class="li-loan loan-accordion-item" id="loan-item-{{ $loan->id }}" aria-labelledby="loan-title-{{ $loan->id }}">
                    <div class="li-row">
                        <div class="li-identity"><div class="li-identity-top"><a class="li-code" href="/loans/{{ $loan->id }}">{{ $code }}</a><span class="li-status li-status-{{ $loan->status }}">{{ ucfirst($loan->status) }}</span></div><a class="li-lender" id="loan-title-{{ $loan->id }}" href="/loans/{{ $loan->id }}">{{ $loan->lender_name }}</a><span class="li-subline">Borrowed {{ $loan->claimed_date ? date('d M Y', strtotime($loan->claimed_date)) : '—' }}</span></div>
                        <div class="li-money"><span class="li-mobile-label">Initial principal</span><strong><small>{{ $loan->currency }}</small> {{ number_format($loan->principal_amount, 2) }}</strong></div>
                        <!-- Full settlement includes unpaid principal and interest for both loan types. -->
                        <div class="li-money li-settlement"><span class="li-mobile-label">Total to settle</span><strong><small>{{ $loan->currency }}</small> {{ number_format($loan->full_settlement_amount, 2) }}</strong><span class="li-subline">Principal {{ number_format($loan->outstanding_principal, 2) }}</span><span class="li-subline">Interest {{ number_format($loan->pending_interest, 2) }}</span></div>
                        <div class="li-date">
                            @if($loan->status === 'settled')<span class="li-mobile-label">Settled</span><span>{{ $loan->settled_date ? date('d M Y', strtotime($loan->settled_date)) : '—' }}</span>
                            @else<span class="li-mobile-label">Next due</span><span @class(['li-overdue' => $isOverdue])>{{ $loan->next_due_date !== 'N/A' ? date('d M Y', strtotime($loan->next_due_date)) : '—' }}</span>@if($isOverdue)<span class="li-overdue li-subline">Overdue</span>@endif @endif
                        </div>
                        <div class="li-actions"><a href="/loans/{{ $loan->id }}" class="li-button li-view" aria-label="View {{ $code }}">View</a><button type="button" class="li-button li-icon" aria-label="Edit {{ $code }}" title="Edit loan" onclick='openEditLoanModal(@json($loan))'><ion-icon name="create-outline" aria-hidden="true"></ion-icon></button><button type="button" class="li-button li-icon li-toggle" aria-expanded="false" aria-controls="loan-drawer-{{ $loan->id }}" aria-label="Details for {{ $code }}" onclick="toggleLoanAccordion({{ $loan->id }})"><ion-icon name="chevron-down-outline" aria-hidden="true"></ion-icon></button></div>
                    </div>
                    <div class="li-drawer" id="loan-drawer-{{ $loan->id }}" hidden>
                        <div class="li-detail-grid">
                            <section><h3>Principal & repayments</h3><div class="li-progress-label"><span>Principal repaid</span><strong>{{ $principalRepaidPct }}%</strong></div><progress value="{{ $principalRepaidPct }}" max="100" aria-label="Principal repaid for {{ $code }}">{{ $principalRepaidPct }}%</progress><dl class="li-facts"><div><dt>Total borrowed</dt><dd>{{ $loan->currency }} {{ number_format($loan->total_borrowed, 2) }}</dd></div><div><dt>Repaid</dt><dd>{{ $loan->currency }} {{ number_format($loan->principal_repaid, 2) }}</dd></div><div><dt>Remaining principal</dt><dd>{{ $loan->currency }} {{ number_format($loan->outstanding_principal, 2) }}</dd></div>@if(!empty($loan->is_upfront_interest))<div><dt>Net disbursed</dt><dd>{{ $loan->currency }} {{ number_format($loan->net_disbursed, 2) }}</dd></div>@endif</dl></section>
                            <section><h3>Interest & obligations</h3><dl class="li-facts"><div><dt>Method</dt><dd>@if($loan->interest_method === 'fixed_amount')Fixed · {{ $loan->currency }} {{ number_format($loan->interest_amount, 2) }}@elseif($loan->interest_method === 'percentage_rate'){{ $loan->interest_rate }}% · {{ ucfirst($loan->rate_basis ?? 'flat') }}@else{{ Str::headline($loan->interest_method) }}@endif</dd></div><div><dt>Frequency</dt><dd>{{ ucfirst($loan->frequency ?? 'monthly') }}</dd></div><div><dt>Interest paid</dt><dd>{{ $loan->currency }} {{ number_format($loan->interest_paid, 2) }}</dd></div><div><dt>Unpaid interest</dt><dd>{{ $loan->currency }} {{ number_format($loan->pending_interest, 2) }}</dd></div><div><dt>Payable by loan type</dt><dd>{{ $loan->currency }} {{ number_format($loan->total_outstanding, 2) }}</dd></div></dl></section>
                            <section><h3>Facility details</h3><dl class="li-facts"><div><dt>Term</dt><dd>{{ $loan->term_months }} months</dd></div><div><dt>Principal due</dt><dd>{{ $loan->maturity_date ? date('d M Y', strtotime($loan->maturity_date)) : 'Not set' }}</dd></div><div><dt>Due day</dt><dd>{{ $loan->due_day ? 'Day '.$loan->due_day : 'Not set' }}</dd></div><div><dt>Bank account</dt><dd>{{ $loan->bank_name ?: 'Not linked' }}@if($loan->account_no)<small>{{ $loan->account_no }}</small>@endif</dd></div><div><dt>Guarantor</dt><dd>{{ $loan->guarantor ?: 'None specified' }}</dd></div><div><dt>Collateral</dt><dd>{{ $loan->collateral ?: 'None' }}</dd></div></dl></section>
                        </div>
                        @if($loan->purpose)<section class="li-purpose"><h3>Purpose & terms</h3><div class="prose">{!! $loan->purpose !!}</div></section>@endif
                        <footer class="li-drawer-actions"><a class="li-button" href="/loans/{{ $loan->id }}">Open loan & schedules <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon></a><div><button type="button" class="li-button" onclick="openChangeStatusModal({{ $loan->id }}, '{{ $loan->status }}')">Change status</button><form id="delete_loan_{{ $loan->id }}" action="/loans/{{ $loan->id }}" method="POST">@csrf @method('DELETE')<button type="button" class="li-button li-delete" onclick="return confirmAction({title:'Delete Loan?', message:'Delete this loan and all associated schedules?', confirmText:'Delete Loan', formId:'delete_loan_{{ $loan->id }}'})">Delete</button></form></div></footer>
                    </div>
                </article>
            @empty
                <div class="li-empty"><h3>{{ $hasFilters ? 'No loans match these filters' : 'No loans recorded yet' }}</h3><p>{{ $hasFilters ? 'Try another lender, date range or search term.' : 'Record your first borrowing to track principal, interest and settlements.' }}</p>@if($hasFilters)<a class="li-button" href="/loans">Clear filters</a>@else<button type="button" class="li-button" onclick="openModal('createLoanModal')">Record loan</button>@endif</div>
            @endforelse
        </div>
        @if($loans->count())<footer class="li-pagination"><span>Showing {{ $loans->firstItem() }}–{{ $loans->lastItem() }} of {{ $loans->total() }} loans</span>{{ $loans->links() }}</footer>@endif
    </section>
</div>
<script src="{{ asset('loan-index.js') }}?v={{ filemtime(public_path('loan-index.js')) }}" defer></script>
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
                <x-loan-correction-note />
                <x-loan-type-selector id="edit_loan_type" />
                <div class="form-row" style="margin-bottom:1.25rem;">
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;">Loan Reference Code</label>
                        <input type="text" name="loan_code" id="edit_loan_code" class="form-control" placeholder="E.g. LN-0016">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;">Select Party / Lender</label>
                        <x-party-selector name="party_id" id="edit_party_id" :parties="$parties" placeholder="Search & select party..." />
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
                    <x-rich-editor name="purpose" id="edit_purpose" placeholder="E.g. Capital investment / terms (type /loan or /employee)..." />
                </div>

                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;"><ion-icon name="card-outline" style="vertical-align:middle;"></ion-icon> Disbursement / Settlement Bank Account</label>
                        <x-bank-account-selector name="bank_account_id" id="edit_bank_account_id" :bankAccounts="$bankAccounts" placeholder="Select Bank Account..." />
                    </div>
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
                <x-loan-type-selector id="create_loan_type" />
                <div class="form-row" style="margin-bottom:1.25rem;">
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;">Loan Reference Code</label>
                        <input type="text" name="loan_code" id="create_loan_code" class="form-control" placeholder="Auto-generated (e.g. LN-0017) or custom">
                    </div>
                </div>
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
                    <x-rich-editor name="purpose" id="create_purpose" placeholder="E.g. Capital investment / terms (type /loan or /employee)..." />
                </div>

                <div class="form-row" style="margin-top:1.25rem;">
                    <div class="form-col">
                        <label class="form-label" style="font-weight:700;"><ion-icon name="card-outline" style="vertical-align:middle;"></ion-icon> Disbursement / Settlement Bank Account</label>
                        <x-bank-account-selector name="bank_account_id" id="create_bank_account_id" :bankAccounts="$bankAccounts" placeholder="Select Bank Account (Disbursement / Settlement)..." />
                    </div>
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
                    <x-loan-attachments label="Attachments (Optional)" />
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
    if (!claimedDateInput || !termInput || !maturityInput || termInput.disabled) return;

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
    if (termInput && !termInput.disabled) {
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
});

function openEditLoanModal(loan) {
    const editForm = document.getElementById('editLoanForm');
    editForm.action = '/loans/' + loan.id;
    const editable = new Set(['_token', '_method', 'loan_type', 'party_id', 'lender_name', 'principal_amount', 'maturity_date', 'reminder_days', 'correction_reason', 'purpose', 'guarantor', 'collateral', 'attachments[]']);
    editForm.querySelectorAll('input, select, textarea').forEach(field => {
        const name = field.name || field.closest('.amount-input-wrapper')?.querySelector('.amount-hidden')?.name;
        field.disabled = loan.status !== 'pending' && !editable.has(name) && !field.closest('.rich-editor-wrapper, .party-selector-wrapper');
    });
    editForm.querySelector('[name="attachments[]"]').value = '';
    editForm.querySelector('[data-loan-correction]').hidden = loan.status === 'pending';
    editForm.querySelector('[name="correction_reason"]').value = '';
    
    setPartySelectorValue('edit_party_id', loan.party_id || '');
    
    const codeEl = document.getElementById('edit_loan_code');
    if (codeEl) codeEl.value = loan.loan_code || ('LN-' + String(loan.id).padStart(4, '0'));
    
    document.getElementById('edit_lender_name').value = loan.lender_name || '';
    setAmountInputValue('edit_principal_amount', loan.principal_amount);
    
    const currEl = document.getElementById('edit_currency');
    if (currEl) currEl.value = loan.currency || 'LKR';
    
    document.getElementById('edit_claimed_date').value = loan.claimed_date || loan.start_date || '';
    document.getElementById('edit_term_months').value = loan.term_months || 1;
    
    setRichEditorData('edit_purpose', loan.purpose || '');

    setBankAccountSelectorValue('edit_bank_account_id', loan.bank_account_id || '');
    
    document.getElementById('edit_loan_type').value = loan.loan_type || 'interest_separate';
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
