<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\AuditPayablesPdf;
use App\Services\AuditPayablesService;
use App\Services\PayrollService;
use App\Services\PayrollStatutoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditPayablesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 8));
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        DB::table('companies')->insert(['id' => 1, 'name' => 'Audit Review', 'base_currency' => 'LKR']);
        DB::table('departments')->insert(['id' => 1, 'company_id' => 1, 'name' => 'Finance']);
        DB::table('categories')->insert(['id' => 1, 'company_id' => 1, 'name' => 'Interest', 'type' => 'expense']);
        DB::table('bank_accounts')->insert(['id' => 1, 'company_id' => 1, 'bank_name' => 'Review Bank', 'account_no' => 'AUDIT-TEST', 'currency' => 'LKR']);
    }

    private function loan(array $extra = []): int
    {
        return DB::table('loans')->insertGetId($extra + ['lender_name' => 'Audit lender', 'principal_amount' => 100000, 'currency' => 'LKR',
            'claimed_date' => '2026-01-01', 'interest_method' => 'no_interest', 'loan_type' => 'interest_separate', 'status' => 'active']);
    }

    private function createRun(string $month = '2026-01', bool $approve = true, string $name = 'Review Employee', ?Employee $employee = null): int
    {
        $employee ??= Employee::create(['full_name' => $name, 'first_name' => $name, 'last_name' => 'Audit', 'employee_code' => 'AUD-'.Str::random(6), 'basic_salary' => 30000, 'currency' => 'LKR']);
        foreach (['EPF_EE' => ['deduction', 'percentage', 8], 'EPF_ER' => ['employer_contribution', 'percentage', 12], 'ETF_ER' => ['employer_contribution', 'percentage', 3], 'APIT' => ['deduction', 'fixed_amount', 1000]] as $code => [$type, $method, $value]) {
            $component = SalaryComponent::firstOrCreate(['code' => $code], ['name' => $code, 'type' => $type, 'calculation_type' => $method, 'calculation_base' => 'on_epf_base', 'is_active' => true]);
            $employee->salaryComponents()->firstOrCreate(['salary_component_id' => $component->id], ['value' => $value, 'is_enabled' => true]);
        }
        $id = PayrollService::create(['month' => $month, 'company_id' => 1, 'department_id' => 1, 'currency' => $employee->currency, 'employee_ids' => [$employee->id],
            'salary_due_date' => $month.'-28', 'statutory_due_date' => $month.'-28', 'tax_due_date' => $month.'-15']);
        if ($approve) {
            DB::table('payroll_items')->where('payroll_run_id', $id)->update(['reviewed' => true]);
            PayrollService::transition($id, 'submit');
            PayrollService::transition($id, 'approve');
        }

        return $id;
    }

    private function pay(int $run, string $kind, int $amount, string $date): int
    {
        return PayrollService::payment($run, ['kind' => $kind, 'amount' => $amount, 'paid_on' => $date, 'bank_account_id' => 1,
            'reference' => 'AUDIT / '.$kind, 'request_key' => (string) Str::uuid()]);
    }

    public function test_settled_loans_and_separate_interest_are_reconstructed_at_cutoff_without_double_counting(): void
    {
        $id = $this->loan(['status' => 'settled', 'interest_method' => 'fixed_amount', 'interest_amount' => 10000]);
        $period = DB::table('loan_interest_schedule')->insertGetId(['loan_id' => $id, 'due_date' => '2026-02-01', 'interest_amount' => 10000, 'paid_amount' => 10000, 'paid_date' => '2026-03-02', 'status' => 'paid']);
        DB::table('loan_interest_schedule')->insert(['loan_id' => $id, 'due_date' => '2026-04-01', 'interest_amount' => 10000, 'paid_amount' => 0, 'status' => 'pending']);
        DB::table('loan_principal_records')->insert(['loan_id' => $id, 'record_type' => 'repayment', 'record_date' => '2026-03-02', 'amount' => 100000, 'reference_no' => 'Cheque A']);
        DB::table('transactions')->insert(['type' => 'expense', 'category_id' => 1, 'department_id' => 1, 'amount' => 10000, 'currency' => 'LKR', 'transaction_date' => '2026-03-02',
            'reference_no' => "LOAN-INT-{$id}-{$period}", 'description' => 'Interest included in cheque A', 'payment_status' => 'completed']);
        $feb = AuditPayablesService::build('2026-02-28');
        $this->assertSame(10000000, $feb['summary']['LKR']['principal']);
        $this->assertSame(1000000, $feb['summary']['LKR']['interest_due']);
        $this->assertSame(1000000, $feb['summary']['LKR']['future_interest']);
        $this->assertSame(11000000, $feb['summary']['LKR']['total']);
        $this->assertCount(1, $feb['loanActivity']);
        $march = AuditPayablesService::build('2026-03-31');
        $this->assertCount(1, $march['loans']);
        $this->assertSame(0, $march['summary']['LKR']['total']);
        $this->assertSame(1000000, $march['loans'][0]['paidInterest']);
        $this->assertCount(3, $march['loanActivity']);
        $this->assertSame(0, $march['loans'][0]['principal']);
        $this->assertSame(1000000, $march['loans'][0]['futureInterest']);
        // A settled status is not enough to clear a future charge without evidence.
        $this->assertNotEmpty($march['exceptions']);
        DB::table('loan_interest_schedule')->where('due_date', '2026-04-01')->delete();
        $settled = AuditPayablesService::build('2026-03-31');
        $this->assertSame('Settled by recorded payments', $settled['loans'][0]['balance_status']);
        $this->assertCount(1, $settled['loans']);
    }

    public function test_currencies_dates_unactivated_loans_and_unknown_records_are_not_silently_combined(): void
    {
        $this->loan(['principal_amount' => 123.45, 'currency' => 'USD']);
        $id = $this->loan();
        $this->loan(['claimed_date' => '2027-01-01']);
        $this->loan(['claimed_date' => null]);
        $this->loan(['status' => 'pending']);
        DB::table('loan_principal_records')->insert(['loan_id' => $id, 'record_type' => 'repayment', 'amount' => 50000]);
        DB::table('loan_principal_records')->insert(['loan_id' => $id, 'record_type' => 'draw', 'amount' => 10000, 'record_date' => '2026-02-01']);
        $report = AuditPayablesService::build('2026-03-31');
        $this->assertSame(12345, $report['summary']['USD']['total']);
        $this->assertSame(11000000, $report['summary']['LKR']['total']);
        $this->assertCount(4, $report['loans']);
        $this->assertCount(3, $report['exceptions']);
        $this->assertSame(0, $report['summary']['USD']['interest_due']);
    }

    public function test_withheld_and_legacy_interest_have_a_dated_source_and_unscheduled_interest_is_separate(): void
    {
        $id = $this->loan(['is_upfront_interest' => true, 'interest_method' => 'fixed_amount', 'interest_amount' => 10000]);
        DB::table('loan_interest_schedule')->insert(['loan_id' => $id, 'due_date' => '2026-01-01', 'interest_amount' => 10000, 'paid_amount' => 10000, 'paid_date' => '2026-01-01', 'status' => 'paid']);
        $legacy = $this->loan();
        DB::table('loan_interest_schedule')->insert(['loan_id' => $legacy, 'due_date' => '2026-01-15', 'interest_amount' => 5000, 'paid_amount' => 2000, 'paid_date' => '2026-02-01', 'status' => 'partially_paid']);
        $this->loan(['interest_method' => 'total_interest', 'total_interest' => 12000, 'term_months' => 4]);
        $report = AuditPayablesService::build('2026-01-31');
        $this->assertSame(1000000, $report['loans'][0]['paidInterest']);
        $this->assertSame(0, $report['loans'][1]['paidInterest']);
        $this->assertSame(500000, $report['summary']['LKR']['interest_due']);
        $this->assertSame(1200000, $report['summary']['LKR']['unscheduled_interest']);
        $this->assertSame('Interest withheld at borrowing', $report['loanActivity'][1]['kind']);
        $this->assertCount(2, $report['exceptions']);
    }

    public function test_completed_payroll_keeps_partial_unpaid_salary_and_unpaid_statutory_balances(): void
    {
        $run = $this->createRun();
        PayrollService::transition($run, 'complete');
        $this->pay($run, 'salary', 20000, '2026-02-01');
        $this->pay($run, 'salary', 6600, '2026-03-01');
        $this->pay($run, 'etf', 900, '2026-02-02');
        PayrollStatutoryService::record($run, 'epf', 'prepared', ['event_on' => '2026-02-03', 'payment_due_on' => '2026-02-27', 'expected_event_id' => 0, 'request_key' => (string) Str::uuid()]);
        $feb = AuditPayablesService::build('2026-02-28');
        $this->assertSame(660000, $feb['summary']['LKR']['salary']);
        $this->assertSame(600000, $feb['summary']['LKR']['epf']);
        $this->assertSame(0, $feb['summary']['LKR']['etf']);
        $this->assertSame(100000, $feb['summary']['LKR']['apit']);
        $this->assertSame(1360000, $feb['summary']['LKR']['total']);
        $this->assertCount(1, $feb['salaries']);
        $this->assertCount(1, $feb['salaryPayments']);
        $epf = collect($feb['statutory'])->firstWhere('kind', 'epf');
        $this->assertSame('Prepared 2026-02-03', $epf['prepared']);
        $this->assertSame('Not recorded', $epf['submission']);
        $etf = collect($feb['statutory'])->firstWhere('kind', 'etf');
        $this->assertSame(0, $etf['balance']);
        $this->assertSame('Not recorded', $etf['submission']);
        $jan = AuditPayablesService::build('2026-01-31');
        $this->assertArrayHasKey('LKR', $jan['summary'], json_encode($jan));
        $this->assertSame(2660000, $jan['summary']['LKR']['salary']);
        $this->assertSame('Not recorded', $jan['statutory'][0]['prepared']);
        $this->assertCount(0, AuditPayablesService::build('2026-03-31')['salaries']);
    }

    public function test_reversals_and_voids_after_cutoff_do_not_rewrite_prior_balances(): void
    {
        $run = $this->createRun();
        $payment = $this->pay($run, 'salary', 20000, '2026-02-01');
        PayrollService::reversePayment($payment, ['date' => '2026-03-01', 'reason' => 'Bank rejected payment']);
        PayrollService::transition($run, 'void', ['date' => '2026-04-01', 'reason' => 'Replace erroneous payroll']);
        $feb = AuditPayablesService::build('2026-02-28');
        $this->assertSame(660000, $feb['summary']['LKR']['salary']);
        $this->assertTrue($feb['payments'][0]['effective']);
        $march = AuditPayablesService::build('2026-03-31');
        $this->assertSame(2660000, $march['summary']['LKR']['salary']);
        $this->assertFalse($march['payments'][0]['effective']);
        $april = AuditPayablesService::build('2026-04-30');
        $this->assertEmpty($april['payrollMonths']);
        $this->assertEmpty($april['summary']);
    }

    public function test_drafts_and_missing_months_are_explicitly_separate_and_report_is_read_only(): void
    {
        $this->createRun('2026-01', false, 'Confidential draft employee');
        $this->createRun('2026-03', false);
        $before = DB::table('payroll_runs')->pluck('status', 'id')->all();
        $report = AuditPayablesService::build('2026-04-15');
        $this->assertEmpty($report['summary']);
        $this->assertCount(2, $report['drafts']);
        $this->assertSame(2660000, $report['drafts'][0]['net']);
        $this->assertSame(5320000, $report['draftSummary']['LKR']['net']);
        $this->assertSame(600000, $report['drafts'][0]['epf']);
        $this->assertSame('2026-03-31', $report['periodEnd']);
        $this->assertCount(3, $report['coverage']);
        $this->assertStringContainsString('amount unknown', $report['coverage'][1]['status']);
        $this->assertSame($before, DB::table('payroll_runs')->pluck('status', 'id')->all());
        $this->assertDatabaseCount('payroll_payments', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->get('/reports/audit-payables?as_of=2026-03-31')->assertOk()
            ->assertSee('2 payrolls awaiting approval')->assertSee('1 gap in payroll records')
            ->assertDontSee('id="audit-review"', false)->assertDontSee('id="audit-exceptions"', false);
    }

    public function test_employee_salary_totals_combine_unpaid_months_and_keep_currencies_separate(): void
    {
        $january = $this->createRun();
        $employee = Employee::findOrFail(DB::table('payroll_items')->where('payroll_run_id', $january)->value('employee_id'));
        $this->createRun('2026-02', employee: $employee);
        $march = $this->createRun('2026-03', employee: $employee);
        $this->pay($january, 'salary', 20000, '2026-02-01');
        $this->pay($march, 'salary', 26600, '2026-04-01');
        $employee->update(['currency' => 'USD']);
        $this->createRun('2026-04', employee: $employee);

        $response = $this->get('/reports/audit-payables?as_of=2026-04-30')->assertOk()
            ->assertSee('Total pending salary')->assertSee('33,200.00')->assertSee('Unpaid salary by payroll month');
        $report = $response->viewData('report');
        $this->assertCount(3, $report['salaries']);
        $totals = collect($report['salaryTotals'])->keyBy('currency');
        $this->assertSame([2, 3320000], [$totals['LKR']['months'], $totals['LKR']['balance']]);
        $this->assertSame([1, 2660000], [$totals['USD']['months'], $totals['USD']['balance']]);
        $this->assertSame($report['summary']['LKR']['salary'], $totals['LKR']['balance']);
        $this->assertSame($report['summary']['USD']['salary'], $totals['USD']['balance']);
    }

    public function test_salary_privacy_date_validation_and_exports_share_the_same_cutoff(): void
    {
        $this->createRun();
        $loanId = $this->loan(['loan_code' => 'AUD-0001', 'lender_name' => 'Audit Export Lender']);
        foreach (['viewer', 'employee'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]));
            foreach (['/reports/audit-payables', '/reports/export/audit-payables/pdf', '/reports/export/audit-payables/xlsx'] as $path) {
                $this->get($path)->assertForbidden();
            }
        }
        $this->actingAs(User::factory()->create(['role' => 'accountant', 'is_active' => true]));
        $this->get('/reports/audit-payables?as_of=2027-01-01')->assertSessionHasErrors('as_of');
        $this->get('/reports/audit-payables?as_of=invalid')->assertSessionHasErrors('as_of');
        $this->get('/reports/audit-payables?as_of=2026-02-28')->assertOk()->assertSee('2026-02-28')->assertSee('Review Employee');
        $pdf = $this->get('/reports/export/audit-payables/pdf?as_of=2026-02-28')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringContainsString('2026-02-28', $pdf->headers->get('content-disposition'));
        $xlsx = $this->get('/reports/export/audit-payables/xlsx?as_of=2026-02-28')->assertOk();
        $this->assertStringContainsString('2026-02-28', $xlsx->headers->get('content-disposition'));
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($xlsx->baseResponse->getFile()->getPathname()));
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('2026-02-28', $xml);
        $this->assertStringContainsString('Review Employee', $xml);
        $this->assertStringContainsString('AUD-0001 (#'.$loanId.') Audit Export Lender', $xml);
        $this->assertStringContainsString('Unapproved payrolls', $xml);
        $this->assertStringContainsString('<c t="n" s="1"><v>26600.00</v></c>', $xml);
        $zip->close();
        $this->assertDatabaseCount('payroll_payments', 0);
    }

    public function test_interest_due_accordion_matches_cutoff_and_excludes_future_paid_and_skipped_periods(): void
    {
        $due = $this->loan(['lender_name' => 'Due lender']);
        $future = $this->loan(['lender_name' => 'Future lender']);
        $paid = $this->loan(['lender_name' => 'Paid lender']);
        $skipped = $this->loan(['lender_name' => 'Skipped lender']);
        foreach ([[$due, '2026-02-01', 1000, 250, '2026-02-10', 'partial'], [$due, '2026-02-28', 500, 0, null, 'pending'],
            [$due, '2026-04-01', 3000, 0, null, 'pending'], [$future, '2026-04-01', 800, 0, null, 'pending'],
            [$paid, '2026-02-01', 500, 500, '2026-02-10', 'paid'], [$skipped, '2026-02-01', 100, 0, null, 'skipped']] as [$loan, $date, $charge, $settled, $paidOn, $status]) {
            DB::table('loan_interest_schedule')->insert(['loan_id' => $loan, 'due_date' => $date, 'interest_amount' => $charge, 'paid_amount' => $settled, 'paid_date' => $paidOn, 'status' => $status]);
        }
        foreach (['remaining', 'all'] as $view) {
            $response = $this->get('/reports/audit-payables?as_of=2026-02-28&view='.$view)->assertOk();
            $dom = new \DOMDocument;
            @$dom->loadHTML($response->getContent());
            $xpath = new \DOMXPath($dom);
            $this->assertCount(1, $xpath->query('//a[@href="#audit-interest-due" and @data-audit-open]'));
            $rows = $xpath->query('//*[@id="audit-interest-due"]//tr[@data-interest-due-loan]');
            $this->assertCount(1, $rows);
            $this->assertSame((string) $due, $rows->item(0)->getAttribute('data-interest-due-loan'));
            $text = $rows->item(0)->textContent;
            foreach (['LKR 100,000.00', '2026-02-01', '2026-02-28', '1,500.00', '250.00', '1,250.00'] as $value) {
                $this->assertStringContainsString($value, $text);
            }
            $this->assertStringNotContainsString('2026-04-01', $text);
            $response->assertViewHas('report', fn ($report) => $report['summary']['LKR']['interest_due'] === 125000);
        }
        $this->get('/reports/audit-payables?as_of=2026-01-31')->assertOk()->assertSee('No unpaid loan interest is due on or before');
    }

    public function test_plain_language_overview_reconciles_per_currency_and_keeps_future_interest_separate(): void
    {
        $run = $this->createRun();
        $this->pay($run, 'salary', 20000, '2026-02-01');
        $loan = $this->loan(['interest_method' => 'fixed_amount', 'interest_amount' => 1000]);
        $this->loan(['principal_amount' => 123.45, 'currency' => 'USD']);
        DB::table('loan_interest_schedule')->insert([
            ['loan_id' => $loan, 'due_date' => '2026-02-01', 'interest_amount' => 1000, 'paid_amount' => 0, 'status' => 'pending'],
            ['loan_id' => $loan, 'due_date' => '2026-04-01', 'interest_amount' => 2000, 'paid_amount' => 0, 'status' => 'pending'],
        ]);
        $response = $this->get('/reports/audit-payables?as_of=2026-02-28')->assertOk()
            ->assertSee('What remains unpaid?')->assertSee('Borrowed money to repay')
            ->assertDontSee('Interest due later')->assertDontSee('How to read this report')
            ->assertSee('Different currencies are not added together.');
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $sheets = $xpath->query('//*[@data-currency]');
        $this->assertCount(2, $sheets);
        $expected = ['LKR' => 11550000, 'USD' => 12345];
        foreach ($sheets as $sheet) {
            $total = (int) $xpath->query('.//*[@data-audit-total]', $sheet)->item(0)->getAttribute('data-audit-total');
            $parts = 0;
            foreach ($xpath->query('.//*[@data-audit-part]', $sheet) as $part) {
                $parts += (int) $part->getAttribute('data-audit-part');
            }
            $this->assertSame($expected[$sheet->getAttribute('data-currency')], $total);
            $this->assertSame($total, $parts);
        }
        $this->assertCount(4, $xpath->query('//section[@class="audit-details"]/details[not(@open)]'));
        $ids = [];
        foreach ($xpath->query('//*[@id]') as $node) {
            $ids[] = $node->getAttribute('id');
        }
        $this->assertSame(count($ids), count(array_unique($ids)), 'Disclosure destinations must have unique IDs.');
        $this->assertCount(1, $xpath->query('//section/h2[contains(., "Loans and remaining balances")]'));
    }

    public function test_overview_explains_unconfirmed_data_without_presenting_a_zero_total(): void
    {
        $this->createRun('2026-01', false);
        $response = $this->get('/reports/audit-payables?as_of=2026-03-15')->assertOk()
            ->assertSee('No confirmed balances for this date')
            ->assertSee('This does not mean nothing is owed.')
            ->assertSee('1 payroll awaiting approval')
            ->assertSee('Missing amounts are unknown, not zero.')
            ->assertSee('Payroll through 28 Feb 2026')
            ->assertDontSee('data-audit-total=', false);
        $this->assertDatabaseCount('payroll_payments', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_fully_paid_records_show_a_real_zero_instead_of_an_empty_report(): void
    {
        $loan = $this->loan();
        DB::table('loan_principal_records')->insert(['loan_id' => $loan, 'record_type' => 'repayment', 'amount' => 100000, 'record_date' => '2026-02-01']);
        $this->get('/reports/audit-payables?as_of=2026-02-28')->assertOk()
            ->assertSee('data-audit-total="0"', false)
            ->assertDontSee('Settled by recorded payments')->assertSee('No remaining loan balances')
            ->assertDontSee('No confirmed balances for this date');
    }

    public function test_remaining_view_omits_paid_items_but_preserves_totals_and_historical_cutoff(): void
    {
        $settled = $this->loan(['loan_code' => 'PAID-LOAN', 'status' => 'settled']);
        $partial = $this->loan(['loan_code' => 'PARTIAL-LOAN']);
        foreach ([$settled => 100000, $partial => 20000] as $id => $amount) {
            DB::table('loan_principal_records')->insert(['loan_id' => $id, 'record_type' => 'repayment', 'amount' => $amount, 'record_date' => '2026-02-01']);
        }
        $run = $this->createRun();
        $this->pay($run, 'salary', 26600, '2026-02-01');
        $this->pay($run, 'epf', 2000, '2026-02-01');
        $this->pay($run, 'etf', 900, '2026-02-01');
        $all = AuditPayablesService::build('2026-02-28');
        $remaining = AuditPayablesService::build('2026-02-28', true);
        $this->assertSame($all['summary'], $remaining['summary']);
        $this->assertSame([$partial], array_column($remaining['loans'], 'id'));
        $this->assertSame(8000000, $remaining['loans'][0]['principal']);
        $this->assertSame([], $remaining['payrollMonths']);
        $this->assertSame([], $remaining['payments']);
        $this->assertSame([], $remaining['salaryPayments']);
        $this->assertSame([], $remaining['loanActivity']);
        $this->assertSame(['epf', 'apit'], array_column($remaining['statutory'], 'kind'));
        $this->assertSame(400000, $remaining['statutory'][0]['balance']);
        $jan = AuditPayablesService::build('2026-01-31', true);
        $this->assertCount(2, $jan['loans']);
        $this->assertCount(1, $jan['payrollMonths']);
        $this->assertCount(3, $jan['statutory']);
        $page = $this->get('/reports/audit-payables?as_of=2026-02-28')->assertOk();
        $page->assertViewHas('report', fn ($r) => $r['remainingOnly'] && count($r['loans']) === 1)
            ->assertSee('view=remaining')->assertDontSee('PAID-LOAN')->assertDontSee('id="audit-payments"', false);
        $this->get('/reports/audit-payables?as_of=2026-02-28&view=all')->assertOk()->assertSee('PAID-LOAN')->assertSee('view=all');
        $html = view('reports.partials.audit-payables-schedules', ['report' => $remaining, 'pdf' => true])->render();
        $this->assertStringNotContainsString('PAID-LOAN', $html);
        $this->assertStringNotContainsString('Employee salary payment allocations', $html);
        $xlsx = $this->get('/reports/export/audit-payables/xlsx?as_of=2026-02-28')->assertOk();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($xlsx->baseResponse->getFile()->getPathname()));
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('PARTIAL-LOAN', $xml);
        $this->assertStringNotContainsString('PAID-LOAN', $xml);
        $this->assertStringNotContainsString('Recorded payroll payments and reversals', $xml);
        $zip->close();
        $this->get('/reports/audit-payables?view=invalid')->assertSessionHasErrors('view');
    }

    public function test_remaining_view_keeps_future_interest_and_reopened_statutory_balances(): void
    {
        $loan = $this->loan(['status' => 'settled']);
        DB::table('loan_principal_records')->insert(['loan_id' => $loan, 'record_type' => 'repayment', 'amount' => 100000, 'record_date' => '2026-02-01']);
        DB::table('loan_interest_schedule')->insert(['loan_id' => $loan, 'due_date' => '2026-04-01', 'interest_amount' => 500, 'paid_amount' => 0, 'status' => 'pending']);
        $run = $this->createRun();
        $payment = $this->pay($run, 'etf', 900, '2026-02-01');
        PayrollService::reversePayment($payment, ['date' => '2026-03-01', 'reason' => 'Test remittance returned']);
        $feb = AuditPayablesService::build('2026-02-28', true);
        $this->assertCount(1, $feb['loans']);
        $this->assertSame(50000, $feb['loans'][0]['futureInterest']);
        $this->assertCount(1, $feb['interestSchedule']);
        $this->assertNotContains('etf', array_column($feb['statutory'], 'kind'));
        $march = AuditPayablesService::build('2026-03-31', true);
        $this->assertContains('etf', array_column($march['statutory'], 'kind'));
    }

    public function test_pdf_handles_a_thousand_employee_rows_and_unbroken_evidence_references(): void
    {
        $this->createRun();
        $report = AuditPayablesService::build('2026-02-28');
        $row = $report['salaries'][0];
        $report['salaries'] = [];
        for ($i = 1; $i <= 1000; $i++) {
            $report['salaries'][] = ['name' => 'Synthetic employee '.$i, 'code' => str_repeat('REFERENCE', 30)] + $row;
        }
        $pdf = app(AuditPayablesPdf::class)->render($report);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(50000, strlen($pdf));
        $this->assertDatabaseCount('payroll_items', 1);
    }

    public function test_duplicate_saved_loan_codes_have_distinct_references_in_every_schedule(): void
    {
        $first = $this->loan(['loan_code' => 'DUP-001']);
        $second = $this->loan(['loan_code' => 'DUP-001']);
        foreach ([$first, $second] as $id) {
            DB::table('loan_interest_schedule')->insert(['loan_id' => $id, 'due_date' => '2026-01-31', 'interest_amount' => 1000, 'paid_amount' => 0, 'status' => 'pending']);
        }
        $report = AuditPayablesService::build('2026-02-28');
        $expected = ['DUP-001 (#'.$first.')', 'DUP-001 (#'.$second.')'];
        $this->assertSame($expected, array_column($report['loans'], 'code'));
        $this->assertSame($expected, array_column($report['interestSchedule'], 'code'));
        $this->assertSame($expected, array_column($report['loanActivity'], 'code'));
        $this->assertSame(20200000, $report['summary']['LKR']['total']);
    }
}
