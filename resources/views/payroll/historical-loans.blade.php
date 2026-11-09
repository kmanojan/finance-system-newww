@if($loanRows->isNotEmpty())
    <section id="employee-loan-reconciliation" class="pr-panel" aria-labelledby="employee-loan-heading">
        <div class="pr-panel-head">
            <h2 id="employee-loan-heading">Employee loan reconciliation</h2>
            <span class="pr-state {{ $unreconciledLoans ? 'pr-state-review' : 'pr-state-paid' }}">
                {{ $loanRows->count() - $unreconciledLoans }} of {{ $loanRows->count() }} reconciled
            </span>
        </div>
        <div class="pr-body">
            <p class="pr-hint">The source sheet shows {{ $run->currency }} {{ number_format($loanRows->sum('amount') / 100, 2) }} in employee loan deductions. Match each deduction to a posted opening entry before approving payroll.</p>
            @if($canApprove && in_array($run->status, ['draft', 'review']) && $loanRows->contains(fn ($row) => $row['options']->isEmpty()))
                <p class="pr-hint">For a missing entry, verify the original loan record and have Finance record the opening balance under <strong>Employee loans receivable</strong>. Use a separate journal for each employee with a description starting with <strong>Employee loan opening [employee code]</strong>, using their actual code. <a href="/journal-entries">View journal entries</a></p>
            @endif
        </div>
        @foreach($loanRows as $row)
            @php
                ['item' => $loanItem, 'match' => $match, 'options' => $entries, 'entry' => $entry, 'amount' => $amount, 'valid' => $valid] = $row;
                $bag = $errors->getBag('historicalLoan'.$loanItem->id);
                $editable = $canApprove && in_array($run->status, ['draft', 'review']);
            @endphp
            <details id="loan-{{ $loanItem->id }}" class="pr-body" @if($bag->any()) open @endif>
                <summary>
                    <strong>{{ $loanItem->snapshot['name'] }}</strong> · {{ $loanItem->snapshot['code'] }}
                    · {{ $run->currency }} {{ number_format($amount / 100, 2) }}
                    <span class="pr-state {{ $valid ? 'pr-state-paid' : 'pr-state-review' }}">{{ $valid ? 'Reconciled' : ($entries->isEmpty() ? 'Opening entry missing' : 'Needs reconciliation') }}</span>
                </summary>
                @if($match)
                    <dl class="pr-detail-list">
                        <div><dt>Source reference</dt><dd>{{ $match->source_reference }}@if($entry) · {{ $entry->entry_no }}@endif</dd></div>
                        <div><dt>Balance before deduction</dt><dd>{{ $run->currency }} {{ number_format($match->balance_cents / 100, 2) }}</dd></div>
                        <div><dt>Balance after deduction</dt><dd>{{ $run->currency }} {{ number_format(($match->balance_cents - $match->deduction_cents) / 100, 2) }}</dd></div>
                    </dl>
                @endif
                @if($editable && $entries->isNotEmpty())
                    @if($bag->any())
                        <div class="pr-errors" role="alert">{{ $bag->first() }}</div>
                    @endif
                    <form class="pr-form-grid" method="post" action="/payroll/{{ $run->id }}/historical-loans/{{ $loanItem->id }}">
                        @csrf
                        <div class="pr-field">
                            <label for="loan-entry-{{ $loanItem->id }}">Opening journal entry</label>
                            <select name="journal_line_id" id="loan-entry-{{ $loanItem->id }}" required>
                                <option value="">Choose an entry</option>
                                @foreach($entries as $option)
                                    <option value="{{ $option->id }}" @selected((int) ($bag->any() ? old('journal_line_id', $match?->journal_line_id) : $match?->journal_line_id) === (int) $option->id)>
                                        {{ $option->entry_no }} · {{ $option->entry_date }} · {{ $run->currency }} {{ number_format($option->debit, 2) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="pr-field">
                            <label for="loan-reference-{{ $loanItem->id }}">Original loan reference</label>
                            <input name="source_reference" id="loan-reference-{{ $loanItem->id }}" value="{{ $bag->any() ? old('source_reference', $match?->source_reference) : $match?->source_reference }}" maxlength="255" required>
                        </div>
                        <div class="pr-field">
                            <label for="loan-balance-{{ $loanItem->id }}">Balance before deduction ({{ $run->currency }})</label>
                            @php($balance = $match ? number_format($match->balance_cents / 100, 2, '.', '') : '')
                            <input type="number" inputmode="decimal" name="balance" id="loan-balance-{{ $loanItem->id }}" value="{{ $bag->any() ? old('balance', $balance) : $balance }}" min="{{ number_format($amount / 100, 2, '.', '') }}" max="999999999.99" step="0.01" required>
                        </div>
                        <div class="pr-actions"><button class="pr-btn pr-btn-primary">Save reconciliation</button></div>
                    </form>
                @elseif($editable && $entries->isEmpty())
                    <p class="pr-hint">No posted opening journal matches this employee, company, currency and payroll date.</p>
                @endif
            </details>
        @endforeach
    </section>
@endif
