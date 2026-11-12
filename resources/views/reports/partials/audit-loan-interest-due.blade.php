<section aria-labelledby="audit-interest-due-title">
    <h2 id="audit-interest-due-title">Loans with unpaid interest due by {{ $reportDate }}</h2>
    <p>Each loan below has interest still unpaid at the reporting date. Dates before {{ $reportDate }} are overdue; dates on {{ $reportDate }} are due that day. Loan amount is the original principal. Interest columns cover only these unpaid interest periods, excluding future interest.</p>
    <div class="audit-table-wrap" role="region" aria-label="Loans with unpaid interest due" tabindex="0">
        <table class="audit-table">
            <thead><tr><th scope="col">Loan / lender</th><th scope="col" class="num">Loan amount</th><th scope="col">Interest due dates</th><th scope="col" class="num">Interest charged</th><th scope="col" class="num">Paid by reporting date</th><th scope="col" class="num">Interest still due</th></tr></thead>
            <tbody>
            @forelse($interestDueLoans as $loan)
                @php($periods = $interestDuePeriods->get($loan['id'], collect())->sortBy('due_on'))
                <tr data-interest-due-loan="{{ $loan['id'] }}">
                    <td class="wrap"><a href="/loans/{{ $loan['id'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $loan['code'] }} (opens in a new tab)">{{ $loan['code'] }}</a><small>{{ $loan['lender'] }}</small></td>
                    <td class="num">{{ $loan['currency'] }} {{ $money($loan['original']) }}</td>
                    <td>@foreach($periods as $period)<small>{{ $period['due_on'] }} · {{ $period['due_on'] < $report['asOf'] ? 'Overdue' : 'Due on reporting date' }}</small>@endforeach</td>
                    <td class="num">{{ $money($periods->sum('charge')) }}</td>
                    <td class="num">{{ $money($periods->sum('paid')) }}</td>
                    <td class="num audit-total">{{ $money($loan['dueInterest']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No unpaid loan interest is due on or before {{ $reportDate }}.</td></tr>
            @endforelse
            </tbody>
            @if($interestDueLoans->isNotEmpty())
                <tfoot>@foreach($interestDueLoans->groupBy('currency') as $currency => $loans)<tr><th scope="row" colspan="5">Total interest due · {{ $currency }}</th><td class="num audit-total">{{ $money($loans->sum('dueInterest')) }}</td></tr>@endforeach</tfoot>
            @endif
        </table>
    </div>
</section>
