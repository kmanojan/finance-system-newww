@props(['fundingLoans', 'id'])
<div class="form-group" style="margin-top:1rem;">
    <label class="form-label" for="{{ $id }}">Principal funded by another loan <span class="text-muted">(optional)</span></label>
    <select id="{{ $id }}" name="source_loan_id" class="form-control" aria-describedby="{{ $id }}-help">
        <option value="">Own funds / no linked loan</option>
        @foreach($fundingLoans as $fundingLoan)
            <option value="{{ $fundingLoan->id }}">{{ $fundingLoan->loan_code ?: 'LN-'.$fundingLoan->id }} · {{ $fundingLoan->lender_name }} · {{ $fundingLoan->currency }} {{ number_format($fundingLoan->principal_amount, 2) }}</option>
        @endforeach
    </select>
    <small id="{{ $id }}-help" class="text-muted">Links this principal repayment to the loan that funded it. Interest is paid separately. Create and activate the funding loan first.</small>
</div>
