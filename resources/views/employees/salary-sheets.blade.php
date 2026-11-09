<section id="employee-sheets-panel" role="tabpanel" aria-labelledby="employee-sheets-tab" hidden class="ep-panel">
    <header><h2>Salary sheets</h2><p>Monthly payroll records for {{ $employee->full_name }}. Open a sheet to review its components and adjustments.</p></header>
    @if($salarySheets->isEmpty())
        <div class="pi-empty"><h3>No salary sheets yet</h3><p>Sheets appear when this employee is included in a monthly payroll.</p><a href="/payroll" class="pi-button">Open monthly payroll</a></div>
    @else
        <div class="ep-sheet-scroll" role="region" aria-label="Employee salary sheets" tabindex="0"><table class="ep-sheets"><thead><tr><th scope="col">Payroll month</th><th scope="col">Processing</th><th scope="col">Payment status</th><th scope="col">Net salary</th><th scope="col">Paid</th><th scope="col">Remaining</th><th scope="col">Actions</th></tr></thead><tbody>
        @foreach($salarySheets as $sheet)
            @php
                $posted = in_array($sheet->status, \App\Services\PayrollService::POSTED_STATUSES);
                $balance = max(0, $sheet->net_cents - $sheet->paid_cents);
                $state = $sheet->status === 'void' ? 'Void' : (!$posted ? 'Not approved' : ($balance === 0 ? 'Paid' : ($sheet->paid_cents > 0 ? 'Partially paid' : 'Unpaid')));
                $url = '/payroll/'.$sheet->run_id.'/employees/'.$sheet->id;
                $month = \Carbon\Carbon::parse($sheet->month)->format('F Y');
            @endphp
            <tr><th scope="row"><a href="{{ $url }}">{{ $month }}</a><small>{{ $sheet->company_name }} · PR-{{ str_pad($sheet->run_id,5,'0',STR_PAD_LEFT) }}</small></th><td>{{ ucfirst($sheet->status) }}</td><td>{{ $state }}</td><td>{{ $sheet->currency }} {{ number_format($sheet->net_cents/100,2) }}@if(!$posted && $sheet->status !== 'void')<small>Estimated</small>@endif</td><td>{{ $sheet->currency }} {{ number_format($sheet->paid_cents/100,2) }}</td><td>@if($posted){{ $sheet->currency }} {{ number_format($balance/100,2) }}@else<span aria-label="Not payable">—</span>@endif</td><td><div class="pi-actions"><a class="pi-button" href="{{ $url }}" aria-label="Open {{ $month }} salary sheet">Open sheet</a>@if($posted || $sheet->status === 'void')<a class="pi-button pi-quiet" href="{{ $url }}/payslip" aria-label="Download {{ $month }} payslip">Payslip</a>@endif</div></td></tr>
        @endforeach
        </tbody></table></div>
        <div class="pi-pagination"><span>{{ $salarySheets->total() }} salary sheets</span>{{ $salarySheets->links() }}</div>
    @endif
</section>
