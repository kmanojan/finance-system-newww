<div data-loan-correction style="margin-bottom:20px;">
    <p class="text-muted">You can correct the party, lender name, and original principal. A principal correction updates the linked disbursement. Recorded repayments and the existing interest schedule stay unchanged.</p>
    <label class="form-label" for="loan_correction_reason">Principal correction note</label>
    <input class="form-control" id="loan_correction_reason" name="correction_reason" maxlength="1000" value="{{ old('correction_reason') }}" placeholder="Required only when changing an activated loan’s principal">
    <small class="text-muted">For newly borrowed money, record an additional draw instead. Reconciled disbursements and closed accounting periods must be resolved before correcting the amount.</small>
</div>
