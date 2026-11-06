@forelse($employees as $e)
@php($payroll = $employeePayroll[$e->id])
@php($s = $payroll['snapshot'])
@php($c = $payroll['calculation'])
@php($assigned = collect($s['components'])->keyBy('id'))
@php($lineAmounts = collect(array_merge($c['earnings'] ?? [], $c['deductions'] ?? [], $c['employer'] ?? []))->filter(fn($line) => isset($line['id']))->keyBy('id'))
<tr data-employee-row data-employee-id="{{ $e->id }}">
<th scope="row" class="pr-grid-employee"><div class="pr-grid-person"><input type="checkbox" name="employee_ids[]" value="{{ $e->id }}" aria-label="Select {{ $e->full_name }}" data-active="{{ $e->status === 'active' ? '1' : '0' }}" @disabled($e->basic_salary <= 0 || $e->currency !== 'LKR')><div><a href="/employees/{{ $e->id }}" target="_blank" rel="noopener" title="Open salary profile in a new tab">{{ $e->full_name }}</a><small>{{ $e->employee_code }} · {{ ucfirst($e->status) }}</small>@if($e->basic_salary <= 0 || $e->currency !== 'LKR')<small>Needs a positive LKR salary</small>@elseif($payroll['error'])<small>{{ $payroll['error'] }}</small>@endif</div></div></th>
<td>{{ number_format($s['basic']/100,2) }}</td>
@foreach(['earning' => 'gross', 'deduction' => 'deductions', 'employer_contribution' => 'employer'] as $type => $total)
@foreach($components[$type] ?? [] as $column)
<td>
@if(isset($assigned[$column->id]))
@php($component = $assigned[$column->id])
@if(($component['payroll_input_mode'] ?? 'display') === 'monthly_amount')
<input aria-label="{{ $component['name'] }} for {{ $e->full_name }} in LKR" name="component_amounts[{{ $e->id }}][{{ $component['id'] }}]" type="number" min="0" max="999999999.99" step="0.01" inputmode="decimal" value="0" data-monthly-component data-component-id="{{ $component['id'] }}" disabled>
@else
<span data-component-label="{{ $component['id'] }}">{{ isset($lineAmounts[$component['id']]) ? number_format($lineAmounts[$component['id']]['amount']/100,2) : '—' }}</span>
@endif
@else<span class="pr-muted" aria-label="Not assigned">—</span>@endif
</td>
@endforeach
@if($type === 'deduction')<td data-employee-total="advance">{{ $c ? number_format($c['advance']/100,2) : '—' }}</td>@endif
<td class="pr-grid-total" data-employee-total="{{ $total }}">{{ $c ? number_format(($total === 'deductions' ? $c['gross']-$c['net'] : ($total === 'employer' ? $c['cost']-$c['gross'] : $c[$total]))/100,2) : '—' }}</td>
@endforeach
<td class="pr-grid-total" data-employee-total="net">{{ $c ? number_format($c['net']/100,2) : '—' }}</td><td class="pr-grid-total" data-employee-total="cost">{{ $c ? number_format($c['cost']/100,2) : '—' }}</td>
</tr>
@empty<tr><td colspan="{{ $components->flatten(1)->count() + 8 }}" class="pr-grid-empty">No employees match. Change your search or <a href="/employees">maintain employee profiles</a>.</td></tr>@endforelse
