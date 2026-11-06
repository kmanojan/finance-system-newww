@props(['id', 'value' => 'interest_separate'])
<div class="form-group">
    <label class="form-label" for="{{ $id }}">Loan type</label>
    <select class="form-control" id="{{ $id }}" name="loan_type" aria-describedby="{{ $id }}_help" required>
        <option value="interest_separate" @selected(old('loan_type', $value) === 'interest_separate')>Type 1 · Interest paid separately</option>
        <option value="principal_and_interest" @selected(old('loan_type', $value) === 'principal_and_interest')>Type 2 · Principal + interest</option>
    </select>
    <p id="{{ $id }}_help" class="text-muted" style="font-size:13px;margin-top:8px;">Type 1 shows remaining principal as payable, with interest separate. Type 2 includes unpaid scheduled interest in the payable balance.</p>
</div>
