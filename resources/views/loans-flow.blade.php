<section id="loan-flow" class="loan-flow" aria-labelledby="loan-flow-title">
    <div class="loan-panel-heading">
        <div class="loan-flow-title-row">
            <h2 id="loan-flow-title">Loan flow</h2>
            <button type="button" class="loan-flow-help-toggle" id="loan-flow-help-toggle" aria-label="Help with loan flow" aria-expanded="false" aria-controls="loan-flow-help" title="Help with loan flow"><ion-icon name="help-circle-outline" aria-hidden="true"></ion-icon></button>
        </div>
    </div>
    <div id="loan-flow-help" class="loan-flow-help" role="note" aria-label="Loan flow guidance" hidden>
        <p>Loans selected in descriptions or settlement notes appear here automatically, ordered by borrowing date. Transactions, payments and projects selected in settlement notes appear as reference cards. Each loan stays outstanding until its own payments are recorded.</p>
        <p>To connect loans, edit the description, type <strong>/loan</strong>, select another loan, and save. In settlement notes, use <strong>/loan</strong>, <strong>/transaction</strong>, <strong>/payment</strong> or <strong>/project</strong> to explain how a payment was settled. Selecting this same loan does not create a connection.</p>
        <ul><li><strong>Dashed arrow:</strong> related in a description or settlement note. References do not post or allocate money.</li><li><strong>Solid arrow:</strong> recorded repayment funding.</li></ul>
    </div>
    <div class="loan-flow-viewport" tabindex="0" role="region" aria-label="Related loan and repayment diagram. Scroll horizontally to explore connected loans.">
        <div class="loan-flow-canvas" data-loan-flow>
            <svg class="loan-flow-lines" aria-hidden="true"></svg>
            @foreach($loanFlow['columns'] as $column)
                <div class="loan-flow-column">
                    @foreach($column as $flowLoan)
                        <a class="loan-flow-node {{ $flowLoan->id == $loan->id ? 'is-current' : '' }}" href="/loans/{{ $flowLoan->id }}#loan-flow" data-flow-node="{{ $flowLoan->id }}" @if($flowLoan->id == $loan->id) aria-current="page" @endif>
                            <div class="loan-flow-node-top"><ion-icon name="wallet-outline" aria-hidden="true"></ion-icon><span>{{ $flowLoan->id == $loan->id ? 'Current loan' : 'Loan facility' }}</span></div>
                            <strong class="loan-flow-code">{{ $flowLoan->loan_code ?: 'LN-'.$flowLoan->id }}</strong>
                            <span class="loan-flow-lender">{{ $flowLoan->lender_name }}</span>
                            <dl>
                                <div><dt>Borrowed</dt><dd>{{ $flowLoan->currency }} {{ number_format($flowLoan->flow_balance['total_borrowed'], 2) }}</dd></div>
                                <div><dt>Payable</dt><dd>{{ $flowLoan->currency }} {{ number_format($flowLoan->flow_balance['total_outstanding'], 2) }}</dd></div>
                            </dl>
                            <small class="loan-flow-interest">{{ $flowLoan->flow_balance['interest_included'] ? 'Includes unpaid interest' : 'Interest separate' }} · {{ $flowLoan->currency }} {{ number_format($flowLoan->flow_balance['pending_interest'], 2) }}</small>
                            <div class="loan-flow-node-footer"><span class="loan-flow-status status-{{ $flowLoan->status }}">{{ ucfirst($flowLoan->status) }}</span><span>{{ $flowLoan->claimed_date ?: 'Date not recorded' }}</span></div>
                        </a>
                    @endforeach
                </div>
            @endforeach
            @if($loanFlow['transactions']->isNotEmpty() || $loanFlow['references']->isNotEmpty())
                <div class="loan-flow-column">
                    @foreach($loanFlow['transactions'] as $transaction)
                        <a class="loan-flow-node" href="/transactions?transaction_id={{ $transaction->id }}" data-flow-node="transaction-{{ $transaction->id }}">
                            <div class="loan-flow-node-top"><ion-icon name="receipt-outline" aria-hidden="true"></ion-icon><span>Referenced transaction</span></div>
                            <strong class="loan-flow-code">TXN-{{ $transaction->id }}</strong>
                            <span class="loan-flow-lender">{{ \Illuminate\Support\Str::limit(strip_tags($transaction->description), 100) }}</span>
                            <dl><div><dt>{{ ucfirst($transaction->type) }}</dt><dd>{{ $transaction->currency }} {{ number_format($transaction->amount,2) }}</dd></div></dl>
                            <small class="loan-flow-interest">{{ $transaction->reference_no ?: 'No reference number' }}</small>
                            <div class="loan-flow-node-footer"><span>{{ ucfirst($transaction->payment_status ?? 'completed') }}</span><span>{{ $transaction->transaction_date }}</span></div>
                        </a>
                    @endforeach
                    @foreach($loanFlow['references'] as $reference)
                        <a class="loan-flow-node" href="{{ $reference->url }}" data-flow-node="{{ $reference->node }}">
                            <div class="loan-flow-node-top"><ion-icon name="link-outline" aria-hidden="true"></ion-icon><span>{{ $reference->label }}</span></div>
                            <strong class="loan-flow-code">{{ $reference->code }}</strong><span class="loan-flow-lender">{{ $reference->name }}</span>
                            <dl><div><dt>{{ $reference->amountLabel }}</dt><dd>{{ $reference->currency }} {{ number_format($reference->amount,2) }}</dd></div></dl>
                            <div class="loan-flow-node-footer">{{ $reference->detail }}</div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
    @if($loanFlow['edges']->isNotEmpty())
        <div class="loan-flow-links" aria-label="Loan connections">
            @foreach($loanFlow['edges'] as $edge)
                @php($from = $loanFlow['loans'][$edge->loan_id])
                @php($to = in_array($edge->kind, ['transaction', 'reference']) ? null : $loanFlow['loans'][$edge->source_loan_id])
                <div class="loan-flow-link" data-flow-from="{{ $edge->loan_id }}" data-flow-to="{{ $edge->source_loan_id }}" data-flow-kind="{{ $edge->kind }}">
                    <span class="loan-flow-number">{{ $loop->iteration }}</span>
                    <div class="loan-flow-link-description">
                        <a href="/loans/{{ $from->id }}#loan-flow">{{ $from->loan_code ?: 'LN-'.$from->id }}</a>
                        <span>{{ $edge->kind === 'description' ? 'related borrowing' : ($edge->kind === 'repayment' ? 'principal repaid using' : 'settlement reference') }}</span>
                        @if($edge->kind === 'transaction')<a href="/transactions?transaction_id={{ $edge->transaction_id }}">TXN-{{ $edge->transaction_id }}</a>@elseif($edge->kind === 'reference')<a href="{{ $loanFlow['references'][$edge->source_loan_id]->url }}">{{ $loanFlow['references'][$edge->source_loan_id]->code }}</a>@else<a href="/loans/{{ $to->id }}#loan-flow">{{ $to->loan_code ?: 'LN-'.$to->id }}</a>@endif
                        <small>{{ $edge->record_date ?: 'Date not recorded' }} ·
                            @if($edge->kind === 'description')
                                Linked in description of
                                @foreach(array_unique($edge->description_loan_ids) as $descriptionLoanId)
                                    <a href="/loans/{{ $descriptionLoanId }}#loan-description">{{ $loanFlow['loans'][$descriptionLoanId]->loan_code ?: 'LN-'.$descriptionLoanId }}</a>{{ !$loop->last ? ', ' : '' }}
                                @endforeach
                                · No payment implied
                            @elseif(in_array($edge->kind, ['settlement', 'transaction', 'reference']))
                                {{ $edge->note_label }} · <a href="/loans/{{ $edge->note_owner_id ?? $edge->loan_id }}#{{ str_starts_with($edge->note_label, 'Interest') ? 'interest-schedule' : 'principal-records' }}">View settlement notes</a> · Reference only
                            @else
                                Repayment #{{ $edge->principal_record_id }}@if($edge->notes) · {{ $edge->notes }}@endif
                            @endif
                        </small>
                    </div>
                    @if($edge->kind === 'repayment')<strong>{{ $from->currency }} {{ number_format($edge->amount, 2) }}</strong>@endif

                </div>
            @endforeach
        </div>
    @endif
</section>
