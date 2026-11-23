@php
    $currencyCodes = $selectedTotals->keys()->merge($party ? [] : $statutoryTotals->keys())->unique()->sort()->values();
    $summary = [
        'receivables' => ['Outstanding receivables', ['receivable' => 'Project invoices', 'staff_receivable' => 'Staff loans and advances']],
        'payables' => ['Outstanding payables', ['loan' => 'Borrowed loans', 'salary' => 'Net salaries', 'other' => 'Vendor bills and commissions']],
        'credits' => ['Unapplied client receipts', ['unapplied' => 'Receipts awaiting invoice allocation']],
    ];
    if (!$canViewPayroll) unset($summary['receivables'][1]['staff_receivable'], $summary['payables'][1]['salary']);
    $remittances = [];
    if ($canViewPayroll && !$party) {
        foreach ($statutoryKinds as $kind => $label) $remittances[$kind] = $kind === 'apit' ? 'Tax (APIT)' : $label;
    }
    $pending = $selectedTotals->filter(fn ($row) => $row['pending_in'] || $row['pending_out']);
@endphp
<section class="party-ledger-position" aria-label="Current financial position">
    @foreach($summary as $side => [$title, $categories])
        @php
            $amounts = $currencyCodes->mapWithKeys(function ($currency) use ($categories, $selectedTotals, $statutoryTotals, $party, $side) {
                $amount = collect($categories)->keys()->sum(fn ($key) => $selectedTotals->get($currency, [])[$key] ?? 0);
                if ($side === 'payables' && !$party) $amount += $statutoryTotals->get($currency, 0);

                return [$currency => $amount];
            });
            $positive = $amounts->filter();
        @endphp
        <section class="ds-panel party-ledger-position-card party-ledger-position-{{ $side }}" aria-labelledby="party-position-{{ $side }}">
            <h2 id="party-position-{{ $side }}">{{ $title }}</h2>
            <div class="party-ledger-position-total">
                @forelse($positive as $currency => $cents)
                    <x-amount-display :amount="$cents / 100" :currency="$currency" class="party-ledger-number" />
                @empty
                    <span class="party-ledger-number">{{ $currencyCodes->count() === 1 ? $money(0, $currencyCodes->first()) : '—' }}</span>
                @endforelse
            </div>
            <dl class="party-ledger-position-lines">
                @foreach($categories as $key => $label)
                    <div class="work-row">
                        <dt>{{ $label }}</dt>
                        <dd>
                            @forelse($currencyCodes->filter(fn ($currency) => $selectedTotals->get($currency, [])[$key] ?? 0) as $currency)
                                <span>{{ $money($selectedTotals[$currency][$key], $currency) }}</span>
                            @empty
                                <span>—</span>
                            @endforelse
                        </dd>
                    </div>
                @endforeach
                @if($side === 'payables')
                    @foreach($remittances as $kind => $label)
                        <div class="work-row">
                            <dt><a href="#statutory-payables">{{ $label }}</a></dt>
                            <dd>
                                @forelse($remittanceBreakdown->get($kind, collect())->filter() as $currency => $amount)
                                    <span>{{ $money($amount, $currency) }}</span>
                                @empty
                                    <span>—</span>
                                @endforelse
                            </dd>
                        </div>
                    @endforeach
                @endif
            </dl>
        </section>
    @endforeach
</section>
@if($pending->isNotEmpty())
    <div class="ds-notice party-ledger-pending">
        <strong>Pending ledger entries</strong>
        @foreach($pending as $currency => $row)
            <span>{{ $money($row['pending_in'], $currency) }} expected in · {{ $money($row['pending_out'], $currency) }} expected out</span>
        @endforeach
        <span>Pending ledger entries are shown separately from recorded balances.</span>
    </div>
@endif
