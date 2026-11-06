<section id="salary-history" class="salary-history" role="tabpanel" aria-labelledby="employee-history-tab" hidden>
    <header class="salary-history-heading">
        <div><h2>Salary change history</h2><p class="text-muted">{{ $salaryHistory->total() }} recorded {{ Str::plural('change', $salaryHistory->total()) }} · Changes apply when saved.</p></div>
        <div class="employee-view-tabs" role="tablist" aria-label="Salary history views">
            @foreach(['table' => 'Table', 'flow' => 'Flow', 'chart' => 'Chart'] as $key => $label)
            <button type="button" id="salary-{{ $key }}-tab" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" aria-controls="salary-{{ $key }}-panel" data-salary-view="{{ $key }}" tabindex="{{ $loop->first ? '0' : '-1' }}">{{ $label }}</button>
            @endforeach
        </div>
    </header>
    <p class="salary-history-help">History tracks saved basic salary, allowances, deductions, and component definition changes. Earlier changes that were not recorded are unavailable. Monthly payroll adjustments remain in payroll records.</p>
    @if($salaryHistory->isEmpty())
    <div class="salary-history-empty">
        <h3>No salary changes recorded yet</h3>
        <p>Save a change to this salary profile to record its before-and-after values.</p>
        <p>Current basic salary: <strong>{{ $salaryCurrent['currency'] }} {{ number_format($salaryCurrent['totals']['basic_salary'], 2) }}</strong></p>
    </div>
    @endif
    <div id="salary-table-panel" role="tabpanel" aria-labelledby="salary-table-tab">
        @if($salaryHistory->isNotEmpty())
        <div class="salary-history-table-wrap" tabindex="0" role="region" aria-label="Salary changes table; scroll horizontally for all columns">
            <table class="salary-history-table">
                <thead><tr><th scope="col">Recorded ({{ config('app.timezone') }})</th><th scope="col">What changed</th><th scope="col">Basic salary</th><th scope="col">Net salary</th><th scope="col">Changed by / reason</th></tr></thead>
                <tbody>
                @foreach($salaryHistory as $entry)
                <tr>
                    <td><time datetime="{{ $entry['recorded_at'] }}">{{ \Carbon\Carbon::parse($entry['recorded_at'])->format('d M Y') }}<small>{{ \Carbon\Carbon::parse($entry['recorded_at'])->format('H:i') }}</small></time><small>{{ $entry['after']['source'] }}</small></td>
                    <td>@include('employees.salary-history-changes', ['entry' => $entry])</td>
                    @foreach(['basic_salary', 'net_salary'] as $metric)
                    <td class="salary-history-amount"><span class="text-muted">{{ $entry['before']['currency'] }} {{ number_format($entry['before']['totals'][$metric], 2) }}</span><strong><span aria-label="changed to">→</span> {{ $entry['after']['currency'] }} {{ number_format($entry['after']['totals'][$metric], 2) }}</strong></td>
                    @endforeach
                    <td><strong>{{ $entry['after']['changed_by_name'] }}</strong><p>{{ $entry['after']['reason'] ?: 'No reason provided' }}</p></td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
    <div id="salary-flow-panel" role="tabpanel" aria-labelledby="salary-flow-tab" hidden>
        @if($salaryHistory->isNotEmpty())
        <p class="text-muted">Oldest to newest on this page.</p>
        <ol class="salary-history-flow">
        @foreach(collect($salaryHistory->items())->reverse() as $entry)
            <li><article>
                <header><strong>{{ \Carbon\Carbon::parse($entry['recorded_at'])->format('d M Y · H:i') }} {{ config('app.timezone') }}</strong><span class="text-muted">{{ $entry['after']['source'] }}</span></header>
                <div class="salary-flow-values"><div><span class="text-muted">Previous basic</span><strong>{{ $entry['before']['currency'] }} {{ number_format($entry['before']['totals']['basic_salary'], 2) }}</strong></div><span aria-label="changed to">→</span><div><span class="text-muted">New basic</span><strong>{{ $entry['after']['currency'] }} {{ number_format($entry['after']['totals']['basic_salary'], 2) }}</strong></div></div>
                @include('employees.salary-history-changes', ['entry' => $entry])
                <footer>{{ $entry['after']['changed_by_name'] }}@if($entry['after']['reason']) · {{ $entry['after']['reason'] }}@endif</footer>
            </article></li>
        @endforeach
        </ol>
        @endif
    </div>
    <div id="salary-chart-panel" role="tabpanel" aria-labelledby="salary-chart-tab" hidden>
        @if($salaryChart->isNotEmpty())
        <div class="salary-chart-controls">
            <label for="salary-chart-metric">Salary measure<select id="salary-chart-metric" class="form-control"><option value="basic_salary">Basic salary</option><option value="gross_salary">Gross salary</option><option value="net_salary">Net salary</option><option value="ctc">Cost to company</option></select></label>
            <label for="salary-chart-currency">Currency<select id="salary-chart-currency" class="form-control"></select></label>
        </div>
        <p class="text-muted">Latest {{ $salaryChart->count() }} recorded {{ Str::plural('change', $salaryChart->count()) }}, oldest to newest. Each point is a saved profile; the first previous profile has no known start date.</p>
        <div class="salary-chart-scroll"><svg id="salary-history-chart" viewBox="0 0 800 280" role="group" aria-label="Salary history chart"></svg></div>
        <output id="salary-chart-value" class="salary-chart-value" aria-live="polite">Select a point to see its date and amount.</output>
        <p class="text-muted">Recorded profile sequence →</p>
        @endif
    </div>
    @if($salaryHistory->hasPages())
    <nav class="salary-history-pagination" data-salary-history-pagination aria-label="Salary history pages">
        @if($salaryHistory->previousPageUrl())<a class="btn btn-outline" href="{{ $salaryHistory->previousPageUrl() }}">Newer changes</a>@endif
        <span>Page {{ $salaryHistory->currentPage() }} of {{ $salaryHistory->lastPage() }}</span>
        @if($salaryHistory->nextPageUrl())<a class="btn btn-outline" href="{{ $salaryHistory->nextPageUrl() }}">Older changes</a>@endif
    </nav>
    @endif
    <script type="application/json" id="salary-history-data">{!! json_encode($salaryChart->map(fn ($entry) => ['id' => $entry['id'], 'date' => \Carbon\Carbon::parse($entry['recorded_at'])->format('d M Y H:i').' '.config('app.timezone'), 'before' => ['currency' => $entry['before']['currency'], 'totals' => $entry['before']['totals']], 'after' => ['currency' => $entry['after']['currency'], 'totals' => $entry['after']['totals']]]), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</section>
