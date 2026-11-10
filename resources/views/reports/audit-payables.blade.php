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
    $sections = [
        'loans' => ['Loans', 'Borrowed amounts, repayments and interest for each lender.', $countLabel(count($report['loans']), 'loan')],
        'salaries' => ['Employee salaries', 'Salary after deductions, payments made and what each employee is still owed.', $countLabel(count($report['payrollMonths']), 'payroll')],
        'statutory' => ['EPF, ETF & tax', 'Contributions and deductions to pay separately from employee salaries.', $countLabel(count($report['statutory']), 'obligation')],
        'payments' => ['Payments already recorded', 'Payment dates, references and reversals. These are already reflected in the balances.', $countLabel(count($report['payments']), 'payment')],
        'review' => ['Unapproved payrolls & missing months', 'Amounts that need confirmation before they can be included.', $draftCount.' unapproved'],
        'exceptions' => ['Checks before sharing', 'Source records to verify and the final review checklist.', $countLabel($exceptionCount, 'check')],
    ];
    if ($remainingOnly) unset($sections['payments']);
@endphp
<div class="audit-report">
    <header class="audit-header">
        <div>
            <h1>Auditor payables</h1>
            <p>Money still to pay for loans and payroll.</p>
        </div>
        <div class="audit-actions" aria-label="Download selected report view">
            <a class="btn btn-outline" href="{{ url('/reports/export/audit-payables/pdf').'?'.http_build_query(['as_of' => $report['asOf'], 'view' => $remainingOnly ? 'remaining' : 'all']) }}">Download PDF</a>
            <a class="btn btn-outline" href="{{ url('/reports/export/audit-payables/xlsx').'?'.http_build_query(['as_of' => $report['asOf'], 'view' => $remainingOnly ? 'remaining' : 'all']) }}">Export Excel</a>
        </div>
    </header>

    <form method="get" class="audit-filter">
        <label for="audit-as-of">Balances as at</label>
        <input type="date" name="as_of" id="audit-as-of" required max="{{ \App\Services\PayrollStatutoryService::today() }}" value="{{ $report['asOf'] }}" aria-describedby="audit-period">
        <label for="audit-view">Show</label>
        <select id="audit-view" name="view"><option value="remaining" @selected($remainingOnly)>Remaining only</option><option value="all" @selected(!$remainingOnly)>All records & payment history</option></select>
        <button class="btn btn-primary" type="submit">Update report</button>
        <span id="audit-period">All companies · Payroll through {{ $payrollDate }}</span>
    </form>
    @if($errors->any())
        <div class="audit-notice" role="alert">{{ $errors->first() }}</div>
    @endif

    <section class="audit-overview" aria-labelledby="audit-overview-title">
        <div class="audit-section-heading">
            <h2 id="audit-overview-title">What remains unpaid?</h2>
            <span>As at {{ $reportDate }}</span>
        </div>
        @forelse($report['summary'] as $currency => $row)
            {{-- One currency per sheet; all breakdown values use the report's integer cents. --}}
            <div class="audit-balance-sheet" data-currency="{{ $currency }}">
                <div class="audit-balance-lead">
                    <span class="audit-label">Recorded amount still unpaid</span>
                    <div class="audit-balance"><span>{{ $currency }}</span><strong data-audit-total="{{ $row['total'] }}">{{ $money($row['total']) }}</strong></div>
                    <p>Based on recorded loans and approved payroll, after recorded payments.</p>
                    <span class="audit-scope-note">Covers loans and payroll only.</span>
                </div>
                <div class="audit-breakdown">
                    <h3>This amount is made up of</h3>
                    <dl>
                        <div>
                            <dt><a href="#audit-loans" data-audit-open>Borrowed money to repay</a><small>Remaining loan principal, whether due now or later.</small></dt>
                            <dd data-audit-part="{{ $row['principal'] }}">{{ $money($row['principal']) }}</dd>
                        </div>
                        <div>
                            <dt><a href="#audit-loans" data-audit-open>Loan interest due</a><small>Interest due by {{ $reportDate }} that remains unpaid.</small></dt>
                            <dd data-audit-part="{{ $row['interest_due'] }}">{{ $money($row['interest_due']) }}</dd>
                        </div>
                        <div>
                            <dt><a href="#audit-salaries" data-audit-open>Employee salaries</a><small>Approved take-home pay still owed to employees.</small></dt>
                            <dd data-audit-part="{{ $row['salary'] }}">{{ $money($row['salary']) }}</dd>
                        </div>
                        <div>
                            <dt><a href="#audit-statutory" data-audit-open>Employee funds & tax</a><small>EPF, ETF and payroll tax (APIT), paid separately from salaries.</small></dt>
                            <dd data-audit-part="{{ $row['epf'] + $row['etf'] + $row['apit'] }}">{{ $money($row['epf'] + $row['etf'] + $row['apit']) }}</dd>
                        </div>
                        @if($row['other'] > 0)
                            <div>
                                <dt><a href="#audit-statutory" data-audit-open>Other payroll amounts</a><small>Other deductions and employer contributions still to pay.</small></dt>
                                <dd data-audit-part="{{ $row['other'] }}">{{ $money($row['other']) }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
                @if($row['unscheduled_interest'] > 0)
                    <div class="audit-future">
                        @if($row['unscheduled_interest'] > 0)
                            <p><strong>Interest without a schedule</strong><span>{{ $currency }} {{ $money($row['unscheduled_interest']) }}</span><small>An estimate to check. Not included because its due dates are unknown.</small></p>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="audit-empty-overview">
                <h3>No confirmed balances for this date</h3>
                <p>This does not mean nothing is owed. Loans need a recorded borrowing date and activation; payroll needs approval and a completed month.</p>
                <a href="#audit-review" data-audit-open>Check payroll records</a>
                <a href="#audit-loans" data-audit-open>Check loan records</a>
            </div>
        @endforelse
        @if(count($report['summary']) > 1)
            <p class="audit-currency-note">Each currency has its own total. Different currencies are not added together.</p>
        @endif
    </section>

    @if($needsReview)
        <aside class="audit-review-notice" aria-labelledby="audit-review-title">
            <div>
                <h2 id="audit-review-title">Some records still need checking</h2>
                <p>The total may not include everything owed.</p>
            </div>
            <ul>
                @if($draftCount)
                    <li><a href="#audit-review" data-audit-open>{{ $draftCount }} {{ \Illuminate\Support\Str::plural('payroll', $draftCount) }} awaiting approval</a><span>Estimates are excluded from the total.</span></li>
                @endif
                @if($gapCount)
                    <li><a href="#audit-review" data-audit-open>{{ $gapCount }} {{ \Illuminate\Support\Str::plural('gap', $gapCount) }} in payroll records</a><span>Missing amounts are unknown, not zero.</span></li>
                @endif
                @if($exceptionCount)
                    <li><a href="#audit-exceptions" data-audit-open>{{ $exceptionCount }} source {{ \Illuminate\Support\Str::plural('check', $exceptionCount) }}</a><span>Review missing dates or conflicting records.</span></li>
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
            <details class="audit-disclosure" id="audit-{{ $key }}">
                <summary><span class="audit-disclosure-title">{{ $label }}<small>{{ $description }}</small></span><span class="audit-count">{{ $count }}</span></summary>
                <div class="audit-detail-body">
                    @include('reports.partials.audit-payables-schedules', ['pdf' => false, 'onlySection' => $key])
                </div>
            </details>
        @endforeach
    </section>

    <footer class="audit-footer">Generated {{ $report['generatedAt'] }} · Confidential payroll information</footer>
</div>
<script src="{{ asset('audit-payables.js') }}?v={{ hash_file('sha256', public_path('audit-payables.js')) }}" defer></script>
@endsection
