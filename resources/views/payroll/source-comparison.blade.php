@php($sourceItems = $items->filter(fn ($item) => isset($item->snapshot['historical_source'])))
@if($sourceItems->isNotEmpty())
@php($comparisons = $sourceItems->mapWithKeys(fn ($item) => [$item->id => \App\Services\HistoricalPayrollImportService::comparison($item)]))
@php($comparisonLabels = $comparisons->flatMap(fn ($fields) => array_keys($fields))->unique()->values())
<section class="pr-panel"><div class="pr-panel-head"><div><h2>Salary sheet comparison</h2><p class="pr-hint">{{ $sourceItems->count() }} source rows · {{ $run->currency }} · system calculations compared with imported source amounts. Confirmed resolutions of source gaps are noted on the affected rows.</p></div><span class="pr-state">{{ $comparisons->filter(fn ($fields) => collect($fields)->contains(fn ($field) => $field['difference'] !== 0))->count() }} employees with differences</span></div>
<div class="pr-body"><p class="pr-hint">Package balance is the source package less basic salary; Allowance is a separate source amount where present. Historical EPF, ETF and tax amounts are retained from the sheet. Due dates are provisional. Salary advances and loan deductions need opening-balance reconciliation before approval.</p></div>
<div class="pr-table-wrap pr-comparison" role="region" aria-label="Salary sheet comparison, scroll for all components" tabindex="0"><table class="pr-table"><thead><tr><th>Employee / source</th>@foreach($comparisonLabels as $label)<th class="pr-number">{{ $label }}</th>@endforeach<th class="pr-number">Source / confirmed net</th><th class="pr-number">Net difference</th></tr></thead><tbody>
@foreach($sourceItems as $sourceItem)
@php($source = $sourceItem->snapshot['historical_source'])
<tr><td><a href="/payroll/{{ $run->id }}/employees/{{ $sourceItem->id }}">{{ $sourceItem->snapshot['name'] }}</a><small>{{ $source['sheet'] }} · row {{ $source['row'] }} · {{ $source['name'] }}</small>@if(isset($source['source_note']))<small>{{ $source['source_note'] }}</small>@endif</td>
@foreach($comparisonLabels as $label)
@php($field = $comparisons[$sourceItem->id][$label] ?? ['system' => 0, 'source' => 0, 'difference' => 0])
<td class="pr-number">{{ number_format($field['system']/100,2) }}@if($field['difference'] !== 0)<small class="text-danger">Sheet {{ number_format($field['source']/100,2) }} · difference {{ number_format($field['difference']/100,2) }}</small>@endif</td>@endforeach
<td class="pr-number">{{ number_format($source['net']/100,2) }}</td><td class="pr-number">{{ number_format(($sourceItem->net_cents - $source['net'])/100,2) }}</td></tr>
@endforeach
</tbody><tfoot><tr><th>Total</th>@foreach($comparisonLabels as $label)<th class="pr-number">{{ number_format($comparisons->sum(fn ($fields) => ($fields[$label]['system'] ?? 0))/100,2) }}</th>@endforeach<th class="pr-number">{{ number_format($sourceItems->sum(fn ($item) => $item->snapshot['historical_source']['net'])/100,2) }}</th><th class="pr-number">{{ number_format(($sourceItems->sum('net_cents') - $sourceItems->sum(fn ($item) => $item->snapshot['historical_source']['net']))/100,2) }}</th></tr></tfoot></table></div>
</section>
@endif
