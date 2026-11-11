<section class="pr-salary-register" aria-labelledby="salary-payment-title" data-employee-directory>
    <div class="pr-panel-head">
        <h2 id="salary-payment-title">Employee salary payments</h2>
        <span class="pr-muted">Amounts in {{ $run->currency }}</span>
    </div>

    @if($items->isNotEmpty())
        <div class="pr-salary-filters pr-body pr-filter" data-employee-tools hidden>
            <div class="pr-field">
                <label for="salary-payment-search">Search employees</label>
                <input id="salary-payment-search" type="search" placeholder="Name or employee code" data-employee-search>
            </div>
            <div class="pr-field">
                <label for="salary-payment-status">Salary status</label>
                <select id="salary-payment-status" data-employee-filter>
                    <option value="all">All statuses</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="partial">Part paid</option>
                    <option value="paid">Paid</option>
                </select>
            </div>
        </div>
        <div class="pr-table-wrap pr-batch-table" role="region" aria-labelledby="salary-payment-title" tabindex="0">
            <table class="pr-table">
                <thead><tr>
                    <th scope="col">Employee</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="pr-number">Net salary</th>
                    <th scope="col" class="pr-number">Paid</th>
                    <th scope="col" class="pr-number">Outstanding</th>
                </tr></thead>
                <tbody>
                    @foreach($items as $item)
                        @php
                            $name = $item->snapshot['name'];
                            $code = $item->snapshot['code'] ?? '';
                            $paid = (int) ($item->paid['salary'] ?? 0);
                            $remaining = max(0, $item->net_cents - $paid);
                            $state = $remaining === 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
                        @endphp
                        <tr data-employee-row data-search="{{ mb_strtolower($name.' '.$code) }}" data-payment-state="{{ $state }}">
                            <td><a href="/payroll/{{ $run->id }}/employees/{{ $item->id }}">{{ $name }}</a><small>{{ $code }}</small></td>
                            <td><span class="pr-state pr-state-{{ $state }}">{{ ['paid' => 'Paid', 'partial' => 'Part paid', 'unpaid' => 'Unpaid'][$state] }}</span></td>
                            <td class="pr-number">{{ number_format($item->net_cents / 100, 2) }}</td>
                            <td class="pr-number">{{ number_format($paid / 100, 2) }}</td>
                            <td class="pr-number">{{ number_format($remaining / 100, 2) }}</td>
                        </tr>
                    @endforeach
                    <tr data-employee-empty hidden><td colspan="5">No employees match. Try another name, code, or salary status.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="pr-footer pr-salary-paging" data-employee-paging hidden>
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
