<section class="pr-panel pr-employee-register" aria-labelledby="employee-payroll-title" data-employee-directory>
    <div class="pr-panel-head">
        <h2 id="employee-payroll-title">Employee payroll</h2>
        <span class="pr-muted">{{ $items->where('reviewed', true)->count() }} of {{ $items->count() }} reviewed · {{ $run->currency }}</span>
    </div>

    @if($items->isNotEmpty())
        <div class="pr-employee-filters pr-body" data-employee-tools hidden>
            <div class="pr-field">
                <label for="employee-search">Search employees</label>
                <input id="employee-search" type="search" placeholder="Name or employee code" data-employee-search>
            </div>
            @if($posted)
                <div class="pr-field">
                    <label for="employee-payment-filter">Salary status</label>
                    <select id="employee-payment-filter" data-employee-filter>
                        <option value="all">All statuses</option>
                        <option value="unpaid">Unpaid</option>
                        <option value="partial">Part paid</option>
                        <option value="paid">Paid</option>
                    </select>
                </div>
            @endif
        </div>
        <div class="pr-table-wrap pr-batch-table" role="region" aria-labelledby="employee-payroll-title" tabindex="0">
            <table class="pr-table">
                <thead><tr>
                    <th scope="col">Employee</th>
                    <th scope="col" class="pr-number">Gross</th>
                    <th scope="col" class="pr-number">Net salary</th>
                    <th scope="col" class="pr-number">Salary paid</th>
                    <th scope="col" class="pr-number">Salary balance</th>
                    <th scope="col">{{ $posted ? 'Salary status' : 'Status' }}</th>
                    <th scope="col"><span class="pr-visually-hidden">Details</span></th>
                </tr></thead>
                <tbody>
                    @foreach($items as $item)
                        @php
                            $name = $item->snapshot['name'];
                            $code = $item->snapshot['code'] ?? '';
                            $paid = (int) ($item->paid['salary'] ?? 0);
                            $balance = $run->status === 'void' ? 0 : max(0, $item->net_cents - $paid);
                            [$state, $label] = match (true) {
                                $run->status === 'void' => ['void', 'Void'],
                                !$posted => ['review', $item->reviewed ? 'Reviewed' : 'Needs review'],
                                $balance === 0 => ['paid', 'Paid'],
                                $paid > 0 => ['partial', 'Part paid'],
                                default => ['unpaid', 'Unpaid'],
                            };
                        @endphp
                        <tr data-employee-row data-search="{{ mb_strtolower($name.' '.$code) }}" data-payment-state="{{ $state }}">
                            <td><a href="/payroll/{{ $run->id }}/employees/{{ $item->id }}">{{ $name }}</a><small>{{ $code }}</small></td>
                            <td class="pr-number">{{ number_format($item->gross_cents / 100, 2) }}</td>
                            <td class="pr-number">{{ number_format($item->net_cents / 100, 2) }}</td>
                            <td class="pr-number">{{ number_format($paid / 100, 2) }}</td>
                            <td class="pr-number">{{ number_format($balance / 100, 2) }}</td>
                            <td><span class="pr-state pr-state-{{ $state }}">{{ $label }}</span></td>
                            <td><a href="/payroll/{{ $run->id }}/employees/{{ $item->id }}" aria-label="View details for {{ $name }}">View details</a></td>
                        </tr>
                    @endforeach
                    <tr data-employee-empty hidden><td colspan="7">{{ $posted ? 'No employees match. Try another name, code, or salary status.' : 'No employees match. Try another name or code.' }}</td></tr>
                </tbody>
            </table>
        </div>
        <div class="pr-footer pr-employee-paging" data-employee-paging hidden>
            <span class="pr-muted" data-employee-range role="status"></span>
            <div class="pr-actions">
                <button class="pr-btn" type="button" data-employee-prev>Previous</button>
                <button class="pr-btn" type="button" data-employee-next>Next</button>
            </div>
        </div>
    @else
        <p class="pr-body pr-muted">No employees in this payroll.</p>
    @endif
</section>
