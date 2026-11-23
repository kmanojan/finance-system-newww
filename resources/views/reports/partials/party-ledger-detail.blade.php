@if($party->entries->isEmpty() && $party->contracts->isEmpty())
    <section class="ds-panel party-ledger-panel party-ledger-empty">No financial records are linked to {{ $party->name }}.</section>
@endif

@if($party->contracts->isNotEmpty())
    <section class="ds-panel party-ledger-panel" aria-labelledby="party-contracts-title">
        <div class="ds-panel party-ledger-panel-header">
            <div><h2 id="party-contracts-title">Project contracts</h2><p>Budget and approved changes for each project.</p></div>
        </div>
        <div class="party-ledger-scroll" role="region" aria-label="Project contracts" tabindex="0">
            <table class="party-ledger-table">
                <thead><tr><th scope="col">Project</th><th class="party-ledger-numeric" scope="col">Base budget</th><th class="party-ledger-numeric" scope="col">Approved changes</th><th class="party-ledger-numeric" scope="col">Receivable</th></tr></thead>
                <tbody>
                @foreach($party->contracts as $contract)
                    <tr>
                        <th scope="row"><a href="/projects/{{ $contract->id }}">{{ $contract->name }}</a></th>
                        <td class="party-ledger-numeric">{{ $contract->budget === null ? '—' : $money((int) round($contract->budget * 100), $contract->currency) }}</td>
                        <td class="party-ledger-numeric">
                            @forelse($contract->changes->groupBy(fn ($change) => $change->currency ?: $contract->currency) as $currency => $changes)
                                <span class="party-ledger-number">{{ $money((int) round($changes->sum('amount') * 100), $currency) }}</span>
                            @empty
                                —
                            @endforelse
                        </td>
                        <td class="party-ledger-numeric">
                            @forelse($party->entries->get('receivable', collect())->where('project.id', $contract->id)->groupBy('currency') as $currency => $invoices)
                                <span class="party-ledger-number">{{ $money($invoices->sum('balance'), $currency) }}</span>
                            @empty
                                —
                            @endforelse
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

@foreach($sections as $key => [$title, $icon, $amountLabel, $paidLabel, $balanceLabel])
    @php
        $results = $party->entries->get($key, collect());
    @endphp
    @continue($results->isEmpty())
    <section class="ds-panel party-ledger-panel" id="{{ $key === 'salary' ? 'salary-payables' : 'party-'.$key }}" aria-labelledby="party-{{ $key }}-title">
        <div class="ds-panel party-ledger-panel-header">
            <div>
                <h2 id="party-{{ $key }}-title"><ion-icon name="{{ $icon }}" aria-hidden="true"></ion-icon> {{ $title }}</h2>
                @if($key === 'salary')
                    <p>Monthly employee payables from approved or completed payroll. EPF, ETF and tax are shown as payroll contributions.</p>
                @elseif($key === 'loan')
                    <p>Interest paid separately appears alongside the principal balance.</p>
                @elseif($key === 'unapplied')
                    <p>These receipts are available for allocation to invoices.</p>
                @elseif($key === 'staff_loan')
                    <p>Posted opening balances and recoveries linked to approved payroll.</p>
                @elseif($key === 'advance')
                    <p>Posted advances and amounts recovered through payroll.</p>
                @endif
            </div>
        </div>
        <div class="party-ledger-scroll" role="region" aria-label="{{ $title }}" tabindex="0">
            <table class="party-ledger-table">
                <thead>
                    <tr>
                        <th scope="col">Source</th>
                        <th scope="col">{{ $key === 'salary' ? 'Pay date due' : 'Date' }}</th>
                        <th class="party-ledger-numeric" scope="col">{{ $amountLabel }}</th>
                        <th class="party-ledger-numeric" scope="col">{{ $paidLabel }}</th>
                        <th class="party-ledger-numeric" scope="col">{{ $balanceLabel }}</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($results as $row)
                    <tr>
                        <th scope="row">
                            <a href="{{ $row->url }}">{{ $row->label }}</a>
                            @isset($row->project)
                                <small><a href="/projects/{{ $row->project->id }}">{{ $row->project->name }}</a></small>
                            @endisset
                            @isset($row->context)<small>{{ $row->context }}</small>@endisset
                            @if($key === 'salary' && $row->balance > 0)
                                <small><a href="{{ $row->payUrl }}">Record salary payment</a></small>
                            @endif
                        </th>
                        <td>{{ $date($key === 'salary' ? $row->due : $row->date) }}
                            @if($key !== 'salary' && !empty($row->due))<small>{{ $key === 'advance' ? 'Deduction month' : 'Due' }} {{ $key === 'advance' ? \Carbon\CarbonImmutable::parse($row->due)->format('F Y') : $date($row->due) }}</small>@endif
                        </td>
                        <td class="party-ledger-numeric">{{ !empty($row->unpriced) ? '—' : $money($row->amount, $row->currency) }}</td>
                        <td class="party-ledger-numeric">{{ $money($row->paid, $row->currency) }}</td>
                        <td class="party-ledger-numeric"><strong>{{ !empty($row->unpriced) ? '—' : $money($row->balance, $row->currency) }}</strong></td>
                        <td><span @class(['party-ledger-status', 'is-open' => $row->balance > 0])>{{ $key === 'unapplied' ? 'Unapplied' : $row->status }}</span></td>
                    </tr>
                    @if($key === 'salary' || $key === 'loan' || ($row->activity ?? collect())->isNotEmpty())
                        <tr class="party-ledger-inline-detail">
                            <td colspan="6">
                                <details class="party-ledger-record">
                                    <summary>
                                        @if($key === 'salary')
                                            Contributions &amp; salary payments
                                        @elseif($key === 'loan')
                                            Principal &amp; interest records
                                        @else
                                            Allocated receipts
                                        @endif
                                        <span class="sr-only"> · {{ $row->label }}</span>
                                    </summary>
                                    @if($key === 'salary' || $key === 'loan')
                                        <dl class="party-ledger-components">
                                            @if($key === 'salary')
                                                <div><dt>EPF contribution</dt><dd>{{ $money($row->epf, $row->currency) }}</dd></div>
                                                <div><dt>ETF contribution</dt><dd>{{ $money($row->etf, $row->currency) }}</dd></div>
                                                <div><dt>Tax (APIT) deducted</dt><dd>{{ $money($row->apit, $row->currency) }}</dd></div>
                                            @else
                                                <div><dt>Principal balance</dt><dd>{{ $money($row->principal, $row->currency) }}</dd></div>
                                                <div><dt>Remaining interest</dt><dd>{{ $money($row->interest, $row->currency) }}</dd></div>
                                                <div><dt>Interest paid</dt><dd>{{ $money($row->interestPaid, $row->currency) }}</dd></div>
                                            @endif
                                        </dl>
                                    @endif
                                    @if($key === 'loan')
                                        @if($row->records->isNotEmpty())
                                            <h3>Principal history</h3>
                                            <ul class="party-ledger-activity">
                                                @foreach($row->records as $record)
                                                    <li><span>{{ $date($record->record_date) }}</span><span>{{ $record->record_type === 'draw' ? 'Additional draw' : 'Repayment' }}{{ $record->reference_no ? ' · '.$record->reference_no : '' }}</span><strong>{{ $row->currency }} {{ number_format($record->amount, 2) }}</strong></li>
                                                @endforeach
                                            </ul>
                                        @endif
                                        @if($row->periods->isNotEmpty())
                                            <h3>Interest schedule</h3>
                                            <ul class="party-ledger-activity">
                                                @foreach($row->periods as $period)
                                                    <li><span>{{ $date($period->due_date) }}</span><span>{{ Str::headline($period->status) }} · Paid {{ $row->currency }} {{ number_format($period->paid_amount, 2) }}</span><strong>{{ $row->currency }} {{ number_format($period->interest_amount, 2) }} scheduled</strong></li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    @else
                                        <ul class="party-ledger-activity">
                                            @forelse($row->activity as $activity)
                                                <li><span>{{ $date($activity->date) }}</span><a href="{{ $activity->url }}">{{ $activity->label }}</a><strong>{{ $money($activity->amount, $row->currency) }}</strong></li>
                                            @empty
                                                <li>No recorded salary payments for this month.</li>
                                            @endforelse
                                        </ul>
                                    @endif
                                </details>
                            </td>
                        </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endforeach
