<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\PayrollPayablesService;
use App\Services\PayrollService;
use App\Services\PayrollStatutoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PayrollStatutoryTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 8));
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        DB::table('companies')->insert(['id' => 1, 'name' => 'Statutory test', 'base_currency' => 'LKR']);
        DB::table('departments')->insert(['id' => 1, 'company_id' => 1, 'name' => 'Finance']);
        DB::table('bank_accounts')->insert(['id' => 1, 'company_id' => 1, 'bank_name' => 'Review Bank', 'account_no' => 'QA-STAT', 'currency' => 'LKR']);
        $this->employee = Employee::create(['full_name' => 'Statutory Review Employee', 'first_name' => 'Statutory', 'last_name' => 'Review Employee', 'employee_code' => 'STAT-1', 'basic_salary' => 30000, 'currency' => 'LKR', 'epf_number' => 'EPF-1']);
        foreach (['EPF_EE' => ['deduction', 'percentage', 8], 'EPF_ER' => ['employer_contribution', 'percentage', 12], 'ETF_ER' => ['employer_contribution', 'percentage', 3], 'APIT' => ['deduction', 'fixed_amount', 1000]] as $code => [$type, $method, $value]) {
            $component = SalaryComponent::create(['code' => $code, 'name' => $code, 'type' => $type, 'calculation_type' => $method, 'calculation_base' => 'on_epf_base', 'is_active' => true]);
            $this->employee->salaryComponents()->create(['salary_component_id' => $component->id, 'value' => $value, 'is_enabled' => true]);
        }
    }

    private function createRun(string $month = '2026-01', bool $approve = true): int
    {
        $run = PayrollService::create(['month' => $month, 'company_id' => 1, 'department_id' => 1, 'currency' => 'LKR', 'employee_ids' => [$this->employee->id],
            'salary_due_date' => '2026-02-28', 'statutory_due_date' => '2026-02-28', 'tax_due_date' => '2026-02-15']);
        if ($approve) {
            DB::table('payroll_items')->where('payroll_run_id', $run)->update(['reviewed' => true]);
            PayrollService::transition($run, 'submit');
            PayrollService::transition($run, 'approve');
        }

        return $run;
    }

    private function event(int $run, string $kind, string $action, array $extra = [])
    {
        $latest = DB::table('payroll_statutory_events')->where('payroll_run_id', $run)->where('kind', $kind)->max('id') ?? 0;

        return $this->postJson('/payroll/'.$run.'/statutory/'.$kind.'/'.$action, $extra + ['event_on' => '2026-02-10', 'payment_due_on' => '2026-02-27',
            'reference' => 'Authority acknowledgement 001', 'notes' => 'Reviewed contribution details', 'request_key' => (string) Str::uuid(), 'expected_event_id' => $latest]);
    }

    private function payment(int $run, string $kind, int $amount)
    {
        return $this->postJson('/payroll/'.$run.'/statutory/'.$kind.'/payment', ['amount' => $amount, 'paid_on' => '2026-02-12', 'reference' => 'Bank remittance reference', 'request_key' => (string) Str::uuid(), 'bank_account_id' => 1, 'kind' => 'salary']);
    }

    private function state(int $run, string $kind = 'epf'): array
    {
        return PayrollStatutoryService::states(PayrollService::run($run), PayrollService::items($run))[$kind];
    }

    public function test_preparation_and_document_submission_never_clear_the_statutory_liability(): void
    {
        $run = $this->createRun();
        $this->assertSame(600000, $this->state($run)['due']);
        $this->assertSame('Not recorded', $this->state($run)['preparationLabel']);
        $this->event($run, 'epf', 'prepared')->assertRedirect();
        $this->assertSame('Prepared', $this->state($run)['preparationLabel']);
        $this->assertSame('Unpaid', $this->state($run)['paymentState']);
        $this->assertSame(600000, $this->state($run)['balance']);
        $this->assertSame('2026-02-27', $this->state($run)['dueOn']);
        $this->assertSame(1, PayrollStatutoryService::register(['status' => 'prepared_unpaid'])->count());
        $this->get('/payroll/'.$run)->assertOk()->assertSee('Preparation: Prepared')->assertSee('2026-02-27');
        $this->event($run, 'epf', 'submitted', ['event_on' => '2026-02-11'])->assertRedirect();
        PayrollService::transition($run, 'complete');
        $this->assertTrue($this->state($run)['submitted']);
        $this->assertSame(600000, $this->state($run)['balance']);
        $this->assertTrue($this->state($run)['overdue']);
        $this->assertDatabaseCount('payroll_payments', 0);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertSame(2660000, PayrollPayablesService::items()->sum('balance_cents'));
        $this->get('/payroll/statutory?status=unpaid')->assertOk()->assertSee('EPF')->assertSee('ETF')->assertSee('APIT')->assertSee('Submitted');
    }

    public function test_partial_remittances_and_reversals_are_independent_of_submission(): void
    {
        $run = $this->createRun();
        PayrollService::transition($run, 'complete');
        $this->event($run, 'epf', 'prepared')->assertRedirect();
        $this->payment($run, 'epf', 2000)->assertRedirect();
        $this->assertSame('Partially paid', $this->state($run)['paymentState']);
        $this->assertSame(400000, $this->state($run)['balance']);
        $this->assertFalse($this->state($run)['submitted']);
        $this->payment($run, 'epf', 4000)->assertRedirect();
        $this->assertSame('Paid', $this->state($run)['paymentState']);
        $this->assertSame(1, PayrollStatutoryService::register(['status' => 'paid_unsubmitted'])->count());
        $this->get('/payroll/statutory?status=paid_unsubmitted')->assertOk()->assertSee('Paid')->assertSee('Not recorded');
        $this->event($run, 'epf', 'submitted', ['event_on' => '2026-02-13'])->assertRedirect();
        $this->assertSame(2, PayrollStatutoryService::register()->count()); // Only ETF and APIT still need work.
        $this->assertSame(3, PayrollStatutoryService::register(['status' => 'all'])->count());
        $payment = DB::table('payroll_payments')->orderBy('id')->first();
        $this->postJson('/payroll/payments/'.$payment->id.'/reverse', ['date' => '2026-02-14', 'reason' => 'Authority returned the first remittance'])->assertRedirect();
        $this->assertSame(200000, $this->state($run)['balance']);
        $this->assertTrue($this->state($run)['submitted']);
        $this->assertSame(2660000, PayrollPayablesService::items()->sum('balance_cents'));
        $this->assertDatabaseHas('payroll_payments', ['kind' => 'epf', 'amount_cents' => 200000]);
    }

    public function test_stale_duplicate_invalid_and_unapproved_updates_are_rejected(): void
    {
        $draft = $this->createRun('2026-01', false);
        $this->event($draft, 'epf', 'prepared')->assertUnprocessable();
        $this->assertSame(0, PayrollStatutoryService::register(['status' => 'all'])->count());
        $run = $this->createRun('2026-02');
        $this->event($run, 'epf', 'submitted')->assertUnprocessable();
        $this->event($run, 'epf', 'prepared', ['event_on' => '2026-01-31'])->assertUnprocessable();
        $this->event($run, 'epf', 'prepared', ['payment_due_on' => '2026-01-31'])->assertUnprocessable();
        $this->event($run, 'epf', 'prepared', ['event_on' => now()->addDays(2)->format('Y-m-d')])->assertUnprocessable();
        $key = (string) Str::uuid();
        $this->event($run, 'epf', 'prepared', ['request_key' => $key])->assertRedirect();
        $this->event($run, 'epf', 'prepared', ['request_key' => $key])->assertUnprocessable();
        $this->event($run, 'epf', 'submitted', ['expected_event_id' => 0])->assertUnprocessable();
        $this->event($run, 'epf', 'submitted', ['event_on' => '2026-02-09'])->assertUnprocessable();
        $this->event($run, 'epf', 'submitted', ['reference' => ''])->assertUnprocessable();
        $this->assertDatabaseCount('payroll_statutory_events', 1);
        $this->payment($run, 'epf', 6001)->assertUnprocessable();
        $this->assertDatabaseCount('payroll_payments', 0);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_evidence_is_private_and_reopening_keeps_original_history_and_payments(): void
    {
        Storage::fake('local');
        $run = $this->createRun();
        $this->event($run, 'epf', 'prepared', ['evidence' => UploadedFile::fake()->create('review.pdf', 1, 'application/pdf')])->assertRedirect();
        $prepared = DB::table('payroll_statutory_events')->first();
        Storage::disk('local')->assertExists($prepared->evidence_path);
        $this->get('/payroll/statutory/events/'.$prepared->id.'/evidence')->assertOk()->assertDownload('review.pdf');
        $this->event($run, 'epf', 'submitted', ['event_on' => '2026-02-11', 'evidence' => UploadedFile::fake()->create('acknowledgement.pdf', 1, 'application/pdf')])->assertRedirect();
        $this->payment($run, 'epf', 6000)->assertRedirect();
        $journals = DB::table('journal_entries')->count();
        $this->actingAs(User::factory()->create(['role' => 'accountant', 'is_active' => true]));
        $this->event($run, 'epf', 'reopened', ['event_on' => '2026-02-13'])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'manager', 'is_active' => true]));
        $this->event($run, 'epf', 'reopened', ['event_on' => '2026-02-13', 'notes' => 'Correct member details on submission'])->assertRedirect();
        $this->assertSame(0, $this->state($run)['balance']);
        $this->assertFalse($this->state($run)['submitted']);
        $this->assertSame('Reopened', $this->state($run)['submissionLabel']);
        $this->assertDatabaseCount('payroll_statutory_events', 3);
        $this->assertDatabaseCount('journal_entries', $journals);
        $this->get('/payroll/'.$run.'/statutory/epf')->assertOk()->assertSee('Correct member details')->assertSee('acknowledgement.pdf');
        $this->actingAs(User::factory()->create(['role' => 'viewer', 'is_active' => true]));
        $this->get('/payroll/statutory')->assertForbidden();
        $this->get('/payroll/statutory/events/'.$prepared->id.'/evidence')->assertForbidden();
        $this->get('/payroll/'.$run.'/statutory/epf/schedule')->assertForbidden();
    }

    public function test_month_company_kind_filters_schedules_and_voided_history(): void
    {
        $jan = $this->createRun();
        $feb = $this->createRun('2026-02');
        $this->assertSame(6, PayrollStatutoryService::register(['status' => 'all'])->count());
        $this->assertSame(1, PayrollStatutoryService::register(['month' => '2026-01', 'kind' => 'epf'])->count());
        $this->assertSame(3, PayrollStatutoryService::register(['month' => '2026-02'])->count());
        $this->get('/payroll/statutory?company_id=1&month=2026-01&kind=epf')->assertOk()->assertSee('6,000.00')->assertDontSee('February 2026');
        $this->get('/payroll/'.$jan.'/statutory/epf/schedule')->assertOk()->assertDownload('payroll-'.$jan.'-epf-review.csv');
        $this->get('/payroll/'.$jan.'/statutory/other')->assertNotFound();
        $this->event($feb, 'etf', 'prepared')->assertRedirect();
        PayrollService::transition($feb, 'void', ['date' => '2026-03-01', 'reason' => 'Payroll replaced for correction']);
        $this->assertSame(3, PayrollStatutoryService::register(['status' => 'all'])->count());
        $this->get('/payroll/'.$feb.'/statutory/etf')->assertOk()->assertSee('This payroll is void')->assertSee('Prepared');
        $this->assertDatabaseHas('payroll_statutory_events', ['payroll_run_id' => $feb, 'kind' => 'etf']);
        $this->get('/payroll/'.$jan.'/statutory/apit')->assertOk()->assertSee('Annual APIT employer statements');
    }

    public function test_paid_legacy_obligation_remains_unknown_until_document_activity_is_recorded(): void
    {
        config(['filesystems.documents_disk' => 'database-documents']);
        $run = $this->createRun();
        $this->payment($run, 'etf', 900)->assertRedirect();
        $state = $this->state($run, 'etf');
        $this->assertSame('Paid', $state['paymentState']);
        $this->assertSame('Not recorded', $state['preparationLabel']);
        $this->assertSame('Not recorded', $state['submissionLabel']);
        $this->assertDatabaseCount('payroll_statutory_events', 0);
        $journals = DB::table('journal_entries')->count();
        $this->event($run, 'etf', 'prepared', ['evidence' => UploadedFile::fake()->create('historical-schedule.pdf', 1, 'application/pdf')])->assertRedirect();
        $record = DB::table('payroll_statutory_events')->first();
        $this->assertDatabaseCount('private_document_files', 1);
        $this->get('/payroll/statutory/events/'.$record->id.'/evidence')->assertOk()->assertDownload('historical-schedule.pdf');
        $this->event($run, 'etf', 'submitted', ['event_on' => '2026-02-13'])->assertRedirect();
        $this->assertSame('Paid', $this->state($run, 'etf')['paymentState']);
        $this->assertTrue($this->state($run, 'etf')['submitted']);
        $this->assertDatabaseCount('journal_entries', $journals);
    }
}
