<section class="pr-panel" id="salary-batches" aria-labelledby="salary-batches-title">
    <div class="pr-panel-head">
        <div><h2 id="salary-batches-title">Salary batches</h2><p class="pr-hint">Recorded releases and the employees paid in each batch.</p></div>
        <span class="pr-state">{{ $salaryBatches->count() }} {{ \Illuminate\Support\Str::plural('batch', $salaryBatches->count()) }}</span>
    </div>
    <div class="pr-table-wrap" role="region" aria-label="Recorded salary batches" tabindex="0">
        <table class="pr-table">
            <thead><tr><th scope="col">Batch / reference</th><th scope="col">Paid date</th><th scope="col">Bank / cash</th><th scope="col" class="pr-number">Employees</th><th scope="col" class="pr-number">Amount</th><th scope="col">Status</th><th scope="col">Details</th></tr></thead>
            <tbody>
                @forelse($salaryBatches as $batch)
                    <tr>
                        <td><strong>PB-{{ str_pad($batch->id,5,'0',STR_PAD_LEFT) }}</strong><small>{{ $batch->reference }}</small></td>
                        <td>{{ $batch->paid_on }}</td>
                        <td>{{ $batch->bank_account_id ? ($banks->firstWhere('id', $batch->bank_account_id)->bank_name ?? 'Bank account unavailable') : 'Cash' }}</td>
                        <td class="pr-number">{{ $batch->employee_count }}</td>
                        <td class="pr-number">{{ $batch->currency }} {{ number_format($batch->amount_cents/100,2) }}</td>
                        <td><span class="pr-state pr-state-{{ $batch->status === 'posted' ? 'paid' : 'void' }}">{{ $batch->status === 'posted' ? 'Recorded' : ucfirst($batch->status) }}</span>@if($batch->status === 'reversed')<small>Reversed {{ $batch->reversed_on }}</small>@endif</td>
                        <td><a class="pr-btn" href="/payroll/{{ $run->id }}/salary-batches/{{ $batch->id }}" aria-label="View employees in batch PB-{{ str_pad($batch->id,5,'0',STR_PAD_LEFT) }}">View employees</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7">No salary batches recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
