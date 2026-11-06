<details class="salary-history-change-list">
    <summary>{{ count($entry['changes']) }} {{ Str::plural('change', count($entry['changes'])) }}: {{ collect($entry['changes'])->pluck('name')->implode(', ') }}</summary>
    <dl>
        @foreach($entry['changes'] as $change)
        <dt>{{ $change['name'] }}</dt><dd>{{ $change['before'] }} → {{ $change['after'] }}</dd>
        @endforeach
        @foreach(['gross_salary' => 'Gross salary', 'total_deductions' => 'Deductions', 'ctc' => 'Cost to company'] as $key => $label)
        <dt>{{ $label }}</dt><dd>{{ $entry['before']['currency'] }} {{ number_format($entry['before']['totals'][$key], 2) }} → {{ $entry['after']['currency'] }} {{ number_format($entry['after']['totals'][$key], 2) }}</dd>
        @endforeach
    </dl>
</details>
