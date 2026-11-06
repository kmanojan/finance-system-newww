<header class="loan-heading">
    <div class="loan-heading-main">
        <a class="loan-back" href="/loans"><ion-icon name="arrow-back-outline" aria-hidden="true"></ion-icon> Loans</a>
        <div class="loan-title-line"><h1>{{ $loan->lender_name }}</h1><span class="loan-state is-{{ $loan->status }}">{{ $statusLabel }}</span></div>
        <p class="loan-identity"><strong>{{ $loanCode }}</strong><span>{{ $balance['loan_type_label'] }}</span></p>
    </div>
    @if($canManageLoan)
        <div class="loan-heading-actions">
            @if($loan->status === 'pending')
                <form action="/loans/{{ $loan->id }}/activate" method="POST" onsubmit="return confirm('Activate this loan and generate its interest schedule?');">@csrf<button type="submit" class="btn btn-primary-gradient"><ion-icon name="play-outline" aria-hidden="true"></ion-icon> Activate loan</button></form>
            @elseif(in_array($loan->status, ['active', 'defaulted']))
                <button type="button" class="btn btn-primary-gradient" onclick="openModal('settlePrincipalFullyModal')"><ion-icon name="checkmark-circle-outline" aria-hidden="true"></ion-icon> Settle loan fully</button>
            @endif
            <button type="button" class="btn btn-outline" onclick="openModal('editLoanModal')"><ion-icon name="create-outline" aria-hidden="true"></ion-icon> Edit loan</button>
            <details class="loan-more">
                <summary aria-label="More loan actions"><ion-icon name="ellipsis-horizontal" aria-hidden="true"></ion-icon></summary>
                <div class="loan-more-menu">
                    @if($loan->status === 'pending')
                        <form action="/loans/{{ $loan->id }}" method="POST" onsubmit="return confirm('Delete this pending loan and its draft records?');">@csrf @method('DELETE')<button type="submit" class="loan-delete"><ion-icon name="trash-outline" aria-hidden="true"></ion-icon> Delete pending loan</button></form>
                    @else
                        <button type="button" onclick="this.closest('details').open=false;openModal('changeStatusModal')"><ion-icon name="swap-horizontal-outline" aria-hidden="true"></ion-icon> Change status</button>
                    @endif
                </div>
            </details>
        </div>
    @endif
</header>

<section class="loan-summary" aria-label="Loan balances">
    <div class="loan-payable">
        <span class="loan-summary-label">Remaining payable</span>
        <div class="loan-payable-value"><span>{{ $loan->currency }}</span><strong>{{ number_format($totalRemainingPayable, 2) }}</strong></div>
        <p>{{ $balance['interest_included'] ? 'Principal + unpaid scheduled interest' : 'Principal only · Interest paid separately' }}</p>
        <div class="loan-progress-label"><span>{{ $balance['interest_included'] ? 'Principal + interest repaid' : 'Principal repaid' }}</span><strong>{{ $overallRepaymentPct }}%</strong></div>
        <progress max="100" value="{{ $overallRepaymentPct }}" aria-label="{{ $balance['interest_included'] ? 'Principal and interest repaid' : 'Principal repaid' }}">{{ $overallRepaymentPct }}%</progress>
    </div>
    <div class="loan-stat"><span class="loan-summary-label">Principal borrowed</span><strong class="loan-stat-value"><small>{{ $loan->currency }}</small>{{ number_format($totalBorrowed, 2) }}</strong><p>Initial {{ number_format($loan->principal_amount, 2) }}@if($totalDraws > 0) + draws {{ number_format($totalDraws, 2) }}@endif</p></div>
    <div class="loan-stat"><span class="loan-summary-label">Unpaid interest</span><strong class="loan-stat-value"><small>{{ $loan->currency }}</small>{{ number_format($totalInterestDue, 2) }}</strong><p>{{ $balance['interest_included'] ? 'Included in payable' : 'Separate from payable' }}</p></div>
    <div class="loan-stat"><span class="loan-summary-label">Total paid</span><strong class="loan-stat-value"><small>{{ $loan->currency }}</small>{{ number_format($totalPaidAll, 2) }}</strong><p>Principal {{ number_format($totalRepaid, 2) }}<br>Interest {{ number_format($totalInterestPaid, 2) }}</p></div>
</section>
<dl class="loan-key-dates">
    <div><dt>Borrowed on</dt><dd>{{ $loan->claimed_date ?: 'Not recorded' }}</dd></div>
    <div><dt>Principal due</dt><dd>{{ $loan->maturity_date ?: 'Open term' }}</dd></div>
    <div><dt>Interest method</dt><dd>{{ $interestMethodLabel }}</dd></div>
    @if($loan->status === 'settled' && $loan->settled_date)<div><dt>Settled on</dt><dd>{{ $loan->settled_date }}</dd></div>@endif
</dl>
