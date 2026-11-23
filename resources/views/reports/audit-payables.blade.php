@extends('reports.layout')
@section('title', 'Auditor payables')
@section('report-content')
<link rel="stylesheet" href="{{ asset('audit-payables.css') }}?v={{ hash_file('sha256', public_path('audit-payables.css')) }}">
@php
    $remainingOnly = $report['remainingOnly'] ?? false;
    $money = fn ($cents) => number_format($cents / 100, 2);
    $reportDate = \Carbon\CarbonImmutable::parse($report['asOf'])->format('j M Y');
    $payrollDate = \Carbon\CarbonImmutable::parse($report['periodEnd'])->format('j M Y');
    $draftCount = count($report['drafts']);
    $gapCount = collect($report['coverage'])->filter(fn ($row) => str_contains($row['status'], 'unknown'))->count();
    $exceptionCount = count($report['exceptions']);
    $needsReview = $draftCount || $gapCount || $exceptionCount;
    $countLabel = fn ($count, $noun) => $count.' '.\Illuminate\Support\Str::plural($noun, $count);
    $interestDueLoans = collect($report['loans'])->filter(fn ($loan) => $loan['included'] && $loan['dueInterest'] > 0);
    $interestDuePeriods = collect($report['interestSchedule'])->filter(fn ($period) => $period['classification'] === 'Due by cutoff' && $period['balance'] > 0)->groupBy('loan_id');
    $partLabels = [
        'principal' => ['Borrowed money to repay', 'loans'],
        'interest_due' => ['Loan interest due', 'interest-due'],
        'salary' => ['Employee salaries', 'salaries'],
        'remittances' => ['Employee funds & tax', 'statutory'],
        'other' => ['Other payroll amounts', 'statutory'],
    ];
    $sections = [
        'loans' => ['Loans', 'Borrowed amounts, repayments and interest for each lender.', $countLabel(count($report['loans']), 'loan')],
        'interest-due' => ['Loans with interest due', 'Unpaid interest due on or before '.$reportDate.'. Future and fully paid interest are excluded.', $countLabel($interestDueLoans->count(), 'loan')],
        'salaries' => ['Employee salaries', 'Salary after deductions, payments made and what each employee is still owed.', $countLabel(count($report['payrollMonths']), 'payroll')],
        'statutory' => ['EPF, ETF & tax', 'Contributions and deductions to pay separately from employee salaries.', $countLabel(count($report['statutory']), 'obligation')],
        'payments' => ['Payments already recorded', 'Payment dates, references and reversals. These are already reflected in the balances.', $countLabel(count($report['payments']), 'payment')],
    ];
    if ($remainingOnly) unset($sections['payments']);
@endphp
<div class="audit-report">
    <header class="page-header audit-header">
        <div>
            <h1>Auditor payables</h1>
            <p>Balances through {{ $reportDate }}</p>
        </div>
        <div class="audit-actions" aria-label="Download selected report view">
            <a class="btn btn-outline" href="{{ url('/reports/export/audit-payables/pdf').'?'.http_build_query(['as_of' => $report['asOf'], 'view' => $remainingOnly ? 'remaining' : 'all']) }}"><ion-icon name="document-outline" aria-hidden="true"></ion-icon> PDF</a>
            <a class="btn btn-outline" href="{{ url('/reports/export/audit-payables/xlsx').'?'.http_build_query(['as_of' => $report['asOf'], 'view' => $remainingOnly ? 'remaining' : 'all']) }}"><ion-icon name="download-outline" aria-hidden="true"></ion-icon> Excel</a>
        </div>
    </header>

    <form method="get" class="ds-panel audit-filter">
        <label for="audit-as-of">Balances as at</label>
        <input type="date" class="form-control" name="as_of" id="audit-as-of" required max="{{ \App\Services\PayrollStatutoryService::today() }}" value="{{ $report['asOf'] }}" aria-describedby="audit-period">
        <label for="audit-view">Show</label>
        <select class="form-control" id="audit-view" name="view"><option value="remaining" @selected($remainingOnly)>Remaining only</option><option value="all" @selected(!$remainingOnly)>All records & payment history</option></select>
        <button class="btn btn-primary" type="submit">Update report</button>
        <span id="audit-period">All companies · Payroll through {{ $payrollDate }}</span>
    </form>
    @if($errors->any())
        <div class="ds-notice error audit-notice" role="alert">{{ $errors->first() }}</div>
    @endif

    <section class="audit-overview" aria-labelledby="audit-overview-title">
        <div class="audit-section-heading">
            <h2 id="audit-overview-title">What remains unpaid?</h2>
            <span>Recorded loans and approved payroll</span>
        </div>
        @forelse($report['summary'] as $currency => $row)
            @php
                $remittances = $row['epf'] + $row['etf'] + $row['apit'];
                $parts = $row + ['remittances' => $remittances];
            @endphp
            <div class="audit-balance-sheet" data-currency="{{ $currency }}">
                <section class="ds-panel audit-position-card audit-balance-lead" aria-label="{{ $currency }} outstanding payables">
                    <h3>Outstanding payables</h3>
                    <strong class="audit-balance" data-audit-total="{{ $row['total'] }}">{{ $currency }} {{ $money($row['total']) }}</strong>
                    <dl class="audit-position-lines">
                        @foreach($partLabels as $key => [$label, $section])
                            @continue($key === 'other' && !$parts[$key])
                            <div>
                                <dt><a href="#audit-{{ $section }}" data-audit-open>{{ $label }}</a></dt>
                                <dd data-audit-part="{{ $parts[$key] }}">{{ $currency }} {{ $money($parts[$key]) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
                <section class="ds-panel audit-position-card" aria-label="{{ $currency }} payroll remittances">
                    <h3>EPF, ETF & tax payable</h3>
                    <strong class="audit-balance">{{ $currency }} {{ $money($remittances) }}</strong>
                    <dl class="audit-position-lines">
                        @foreach(['epf' => 'EPF', 'etf' => 'ETF', 'apit' => 'Payroll tax (APIT)'] as $kind => $label)
                            <div>
                                <dt><a href="#audit-statutory" data-audit-open>{{ $label }}</a></dt>
                                <dd>{{ $currency }} {{ $money($row[$kind]) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <p class="audit-scope-note">Included in outstanding payables.</p>
                </section>
                @if($row['unscheduled_interest'] > 0)
                    <div class="ds-panel audit-future">
                        <p><strong>Interest without a schedule</strong><span>{{ $currency }} {{ $money($row['unscheduled_interest']) }}</span><small>Estimated interest. Due dates are unconfirmed, so it stays outside outstanding payables.</small></p>
                    </div>
                @endif
            </div>
        @empty
            <div class="audit-empty-overview">
                <h3>No confirmed balances for this date</h3>
                <p>This does not mean nothing is owed. Loans need a recorded borrowing date and activation; payroll needs approval and a completed month.</p>
                <a href="#audit-loans" data-audit-open>Check loan records</a>
            </div>
        @endforelse
        @if(count($report['summary']) > 1)
            <p class="audit-currency-note">Each currency has its own total. Different currencies are not added together.</p>
        @endif
    </section>

    @if($needsReview)
        <aside class="ds-panel audit-review-notice" aria-labelledby="audit-review-title">
            <div>
                <h2 id="audit-review-title">Some records still need checking</h2>
                <p>The total may not include everything owed.</p>
            </div>
            <ul>
                @if($draftCount)
                    <li><strong>{{ $draftCount }} {{ \Illuminate\Support\Str::plural('payroll', $draftCount) }} awaiting approval</strong><span>Estimates are excluded from the total.</span></li>
                @endif
                @if($gapCount)
                    <li><strong>{{ $gapCount }} {{ \Illuminate\Support\Str::plural('gap', $gapCount) }} in payroll records</strong><span>Missing amounts are unknown, not zero.</span></li>
                @endif
                @if($exceptionCount)
                    <li><strong>{{ $exceptionCount }} source {{ \Illuminate\Support\Str::plural('check', $exceptionCount) }}</strong><span>See the report exports for details.</span></li>
                @endif
            </ul>
        </aside>
    @endif

    <section class="audit-details" aria-labelledby="audit-details-title">
        <div class="audit-section-heading">
            <h2 id="audit-details-title">Look into the amounts</h2>
            <span>{{ $remainingOnly ? 'Outstanding items only · fully paid items hidden' : 'All records, including settled items' }}</span>
        </div>
        @foreach($sections as $key => [$label, $description, $count])
            <details class="ds-panel audit-disclosure" id="audit-{{ $key }}">
                <summary><span class="audit-disclosure-title">{{ $label }}<small>{{ $description }}</small></span><span class="audit-count">{{ $count }}</span></summary>
                <div class="audit-detail-body">
                    @if($key === 'interest-due')
                        @include('reports.partials.audit-loan-interest-due')
                    @else
                        @if($key === 'statutory')
                            <div class="audit-statutory-totals">
                                <h3>Recorded totals still to pay</h3>
                                <dl class="audit-statutory-cards">
                                    @foreach(['epf' => 'EPF total', 'etf' => 'ETF total', 'apit' => 'APIT total'] as $kind => $title)
                                        <div>
                                            <dt>{{ $title }}</dt>
                                            @forelse($report['summary'] as $currency => $totals)
                                                <dd><span>{{ $currency }}</span> {{ $money($totals[$kind]) }}</dd>
                                            @empty
                                                <dd>—</dd>
                                            @endforelse
                                        </div>
                                    @endforeach
                                </dl>
                            </div>
                        @endif
                        @include('reports.partials.audit-payables-schedules', ['pdf' => false, 'onlySection' => $key])
                    @endif
                </div>
            </details>
        @endforeach
    </section>

    <footer class="audit-footer">Generated {{ $report['generatedAt'] }} · Confidential payroll information</footer>
</div>
<script src="{{ asset('audit-payables.js') }}?v={{ hash_file('sha256', public_path('audit-payables.js')) }}" defer></script>
@endsection
