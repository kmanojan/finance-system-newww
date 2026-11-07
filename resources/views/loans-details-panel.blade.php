<div class="loan-panel-heading"><div><h2>Loan details</h2><p>Terms, account information, and the context behind this borrowing.</p></div></div>
<div class="loan-information-grid">
    <section class="loan-info-block" aria-labelledby="loan-terms-title">
        <h3 id="loan-terms-title">Terms & balances</h3>
        <dl class="loan-facts">
            <div><dt>Payable balance</dt><dd>{{ $loan->currency }} {{ number_format($totalRemainingPayable, 2) }}<small>{{ $balance['interest_included'] ? 'Principal + unpaid interest' : 'Principal only; interest paid separately' }}</small></dd></div>
            <div><dt>Outstanding principal</dt><dd>{{ $loan->currency }} {{ number_format($balance['outstanding_principal'], 2) }}</dd></div>
            <div><dt>{{ $balance['interest_included'] ? 'Principal + scheduled interest' : 'Principal obligation' }}</dt><dd>{{ $loan->currency }} {{ number_format($totalLoanObligation, 2) }}</dd></div>
            <div><dt>Scheduled interest</dt><dd>{{ $loan->currency }} {{ number_format($totalContractedInterest, 2) }}</dd></div>
            <div><dt>Interest method</dt><dd>{{ $interestMethodLabel }}</dd></div>
            @if($loan->interest_method === 'fixed_amount')<div><dt>Interest per period</dt><dd>{{ $loan->currency }} {{ number_format($loan->interest_amount, 2) }}</dd></div>@endif
            @if($loan->interest_method === 'percentage_rate')<div><dt>Interest rate</dt><dd>{{ $loan->interest_rate }}%</dd></div>@endif
            @if(!in_array($loan->interest_method, ['no_interest', 'custom_schedule']))<div><dt>Payment frequency</dt><dd>{{ ucfirst($loan->frequency ?? 'monthly') }} · Day {{ $loan->due_day ?? 1 }}</dd></div>@endif
            <div><dt>Term</dt><dd>{{ $loan->term_months ? $loan->term_months.' months' : 'Not specified' }}</dd></div>
            @if(!empty($loan->is_upfront_interest))<div><dt>Net cash received</dt><dd>{{ $loan->currency }} {{ number_format($loan->net_disbursed, 2) }}</dd></div><div><dt>Upfront interest</dt><dd>{{ $loan->currency }} {{ number_format($loan->upfront_interest_amount ?: $loan->interest_amount, 2) }}</dd></div>@endif
            <div><dt>Maturity reminder</dt><dd>{{ $loan->maturity_date ? ($loan->reminder_days ?? 3).' days before due date' : 'No maturity date set' }}</dd></div>
        </dl>
    </section>
    <div class="loan-information-stack">
        <section id="loan-description" class="loan-info-block" aria-labelledby="loan-description-title">
            <div class="loan-info-heading"><h3 id="loan-description-title">Description & related loans</h3>@if($canManageLoan)<button type="button" class="btn btn-outline" onclick="openModal('editLoanModal')">Edit description</button>@endif</div>
            @if($loan->purpose)<div class="prose loan-description-text">{!! $loan->purpose !!}</div>@else<p class="text-muted">No description added.</p>@endif
            <p class="loan-help">Select another loan using <strong>/loan</strong> in the description to connect it in <button type="button" class="loan-inline-link" data-open-loan-tab="flow">Loan Flow</button>. Each loan keeps its own balance.</p>
        </section>
        <section class="loan-info-block" aria-labelledby="loan-bank-title">
            <h3 id="loan-bank-title">Bank account & security</h3>
            <dl class="loan-facts">
                <div><dt>Bank account</dt><dd>@if($linkedBankAccount){{ $linkedBankAccount->bank_name }}<small>{{ $linkedBankAccount->account_no }}</small>@else Unassigned / Cash in hand @endif</dd></div>
                @if($linkedBankAccount)<div><dt>Current bank balance</dt><dd>{{ $linkedBankAccount->currency }} {{ number_format($linkedBankAccount->current_balance, 2) }}</dd></div>@endif
                <div><dt>Guarantor</dt><dd>{{ $loan->guarantor ?: 'Not specified' }}</dd></div>
                <div><dt>Collateral</dt><dd>{{ $loan->collateral ?: 'Not specified' }}</dd></div>
            </dl>
        </section>
        <section class="loan-info-block" aria-labelledby="loan-documents-title">
            <h3 id="loan-documents-title">Documents <span class="loan-count">{{ $attachments->count() }}</span></h3>
            @forelse($attachments as $attachment)
                <a class="loan-document" href="/attachments/{{ $attachment->id }}/download" target="_blank" rel="noopener"><ion-icon name="document-attach-outline" aria-hidden="true"></ion-icon><span>{{ $attachment->file_name }}</span><ion-icon name="download-outline" aria-hidden="true"></ion-icon></a>
            @empty<p class="text-muted">No documents attached.</p>@endforelse
        </section>
    </div>
</div>
