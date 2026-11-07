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

    private function createRun(string $month = '2026-01', bool $approve = true, string $name = 'Review Employee'): int
    {
        $employee = Employee::create(['full_name' => $name, 'first_name' => $name, 'last_name' => 'Audit', 'employee_code' => 'AUD-'.Str::random(6), 'basic_salary' => 30000, 'currency' => 'LKR']);
        foreach (['EPF_EE' => ['deduction', 'percentage', 8], 'EPF_ER' => ['employer_contribution', 'percentage', 12], 'ETF_ER' => ['employer_contribution', 'percentage', 3], 'APIT' => ['deduction', 'fixed_amount', 1000]] as $code => [$type, $method, $value]) {
            $component = SalaryComponent::firstOrCreate(['code' => $code], ['name' => $code, 'type' => $type, 'calculation_type' => $method, 'calculation_base' => 'on_epf_base', 'is_active' => true]);
            $employee->salaryComponents()->create(['salary_component_id' => $component->id, 'value' => $value, 'is_enabled' => true]);
        }
        $id = PayrollService::create(['month' => $month, 'company_id' => 1, 'department_id' => 1, 'currency' => 'LKR', 'employee_ids' => [$employee->id],
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
        $this->get('/reports/audit-payables?as_of=2026-03-31')->assertOk()->assertSee('Unapproved payrolls')->assertSee('26,600.00')->assertSee('No valid payroll in scope');
    }

    public function test_salary_privacy_date_validation_and_exports_share_the_same_cutoff(): void
    {
        $this->createRun();
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
        $this->assertStringContainsString('Unapproved payrolls', $xml);
        $this->assertStringContainsString('<c t="n" s="1"><v>26600.00</v></c>', $xml);
        $zip->close();
        $this->assertDatabaseCount('payroll_payments', 0);
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
}
