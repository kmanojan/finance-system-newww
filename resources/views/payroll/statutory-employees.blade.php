<section class="pr-panel sd-employees">
    <header class="pr-panel-head"><div><h2>Employee contributions</h2><p class="pr-hint">{{ \Carbon\Carbon::parse($run->month)->format('F Y') }} · {{ $company->name }} · {{ $items->count() }} {{ Str::plural('employee', $items->count()) }}</p></div><span class="pr-hint">All amounts in {{ $run->currency }}</span></header>
    <div class="pr-body sd-employee-note"><p class="pr-hint">{{ $state['posted'] ? 'Saved contributions for this payroll month.' : ($run->status === 'void' ? 'Historical contributions from this voided payroll; these amounts are no longer payable.' : 'Estimated contributions for this draft payroll.') }} These are contribution amounts, not payment balances. Select an employee to review their monthly salary sheet.</p></div>
    <div class="pr-table-wrap" role="region" aria-label="Monthly employee EPF ETF and tax contributions" tabindex="0">
        <table class="pr-table sd-employee-table">
            <thead><tr><th scope="col">Employee</th><th scope="col" class="pr-number">Employee EPF</th><th scope="col" class="pr-number">Employer EPF</th><th scope="col" class="pr-number">Total EPF</th><th scope="col" class="pr-number">ETF</th><th scope="col" class="pr-number">Tax (APIT)</th></tr></thead>
            <tbody>
            @forelse($items as $employeeItem)
                <tr data-statutory-employee="{{ $employeeItem->employee_id }}">
                    <th scope="row"><a href="/payroll/{{ $run->id }}/employees/{{ $employeeItem->id }}">{{ $employeeItem->snapshot['name'] }}</a><small>{{ $employeeItem->snapshot['code'] ?: '—' }}</small></th>
                    <td class="pr-number">{{ number_format(($employeeItem->calculation['ee'] ?? 0)/100,2) }}</td>
                    <td class="pr-number">{{ number_format(($employeeItem->calculation['er'] ?? 0)/100,2) }}</td>
                    <td class="pr-number sd-epf-total">{{ number_format($employeeItem->epf_cents/100,2) }}</td>
                    <td class="pr-number">{{ number_format($employeeItem->etf_cents/100,2) }}</td>
                    <td class="pr-number">{{ number_format($employeeItem->apit_cents/100,2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No employees are included in this payroll.</td></tr>
            @endforelse
            </tbody>
            @if($items->isNotEmpty())
                <tfoot><tr><th scope="row">Total · {{ $run->currency }}</th><td class="pr-number">{{ number_format($items->sum(fn ($item) => $item->calculation['ee'] ?? 0)/100,2) }}</td><td class="pr-number">{{ number_format($items->sum(fn ($item) => $item->calculation['er'] ?? 0)/100,2) }}</td><td class="pr-number">{{ number_format($items->sum('epf_cents')/100,2) }}</td><td class="pr-number">{{ number_format($items->sum('etf_cents')/100,2) }}</td><td class="pr-number">{{ number_format($items->sum('apit_cents')/100,2) }}</td></tr></tfoot>
            @endif
        </table>
    </div>
</section>
