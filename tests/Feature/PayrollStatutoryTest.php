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

    private function statutoryBatch(array $entries, array $extra = [])
    {
        return $this->postJson('/payroll/statutory/batches', $extra + [
            'company_id' => 1, 'kind' => 'epf', 'entries' => $entries,
            'paid_on' => '2026-03-15', 'reference' => 'Authority bulk transfer',
            'request_key' => (string) Str::uuid(), 'bank_account_id' => 1,
        ]);
    }

    public function test_actual_remittances_keep_dates_within_the_payroll_month(): void
    {
        $run = $this->createRun('2025-01');
        foreach (['epf' => 6000, 'etf' => 900, 'apit' => 1000] as $kind => $amount) {
            $url = '/payroll/'.$run.'/statutory/'.$kind.'/payment';
            $data = ['amount' => $amount, 'paid_on' => '2025-01-30', 'reference' => 'January bank remittance', 'bank_account_id' => 1, 'request_key' => (string) Str::uuid()];
            $this->postJson($url, array_replace($data, ['paid_on' => '2024-12-31']))->assertUnprocessable();
            $this->postJson($url, array_replace($data, ['paid_on' => '2026-11-10']))->assertUnprocessable();
            $this->postJson($url, $data)->assertRedirect();
            $this->assertSame(0, $this->state($run, $kind)['balance']);
            $payment = DB::table('payroll_payments')->where('kind', $kind)->first();
            $this->assertSame('2025-01-30', $payment->paid_on);
            $this->assertDatabaseHas('transactions', ['id' => $payment->transaction_id, 'transaction_date' => '2025-01-30']);
            $this->postJson($url, $data)->assertUnprocessable();
        }
        $this->assertDatabaseCount('payroll_payments', 3);
        $this->assertDatabaseCount('transactions', 3);
        $this->get('/payroll/'.$run.'/statutory/epf')->assertOk()->assertSee('2025-01-30');
    }

    public function test_batch_accepts_latest_selected_month_start_and_rejects_earlier_dates_atomically(): void
    {
        $jan = $this->createRun();
        $feb = $this->createRun('2026-02');
        $entries = [['run_id' => $jan, 'amount' => 100], ['run_id' => $feb, 'amount' => 100]];
        $this->statutoryBatch($entries, ['paid_on' => '2026-01-30'])->assertUnprocessable();
        $this->assertDatabaseCount('payroll_payments', 0);
        $this->assertDatabaseCount('transactions', 0);
        $this->statutoryBatch($entries, ['paid_on' => '2026-02-01'])->assertOk();
        $this->assertDatabaseCount('payroll_payments', 2);
        $this->assertSame(2, DB::table('transactions')->where('transaction_date', '2026-02-01')->count());
        $this->get('/payroll/statutory/batches/create?company_id=1&kind=epf')->assertOk()->assertSee('data-earliest="2026-02-01"', false);
        $this->get('/payroll/'.$feb.'/statutory/epf')->assertOk()->assertSee('min="2026-02-01"', false)->assertSee('Use the actual remittance date');
    }

    public function test_multiple_month_batch_partial_balances_history_and_reversal(): void
    {
        $jan = $this->createRun();
        $feb = $this->createRun('2026-02');
        $beforeSalary = PayrollPayablesService::items()->sum('balance_cents');
        $this->get('/payroll/statutory/batches/create?company_id=1&kind=epf&year=2026')->assertOk()->assertSee('January 2026')->assertSee('February 2026');
        $response = $this->statutoryBatch([['run_id' => $jan, 'amount' => 6000], ['run_id' => $feb, 'amount' => 2000]])->assertOk();
        $first = DB::table('payroll_payments')->orderBy('id')->first();
        $response->assertJsonPath('redirect', '/payroll/statutory/batches/'.$first->id);
        $this->assertSame(0, $this->state($jan)['balance']);
        $this->assertSame(400000, $this->state($feb)['balance']);
        $this->assertSame(90000, $this->state($jan, 'etf')['balance']);
        $this->assertSame($beforeSalary, PayrollPayablesService::items()->sum('balance_cents'));
        $this->assertFalse($this->state($jan)['submitted']);
        $this->assertDatabaseCount('transactions', 2);
        $this->assertDatabaseCount('payroll_payments', 2);
        $this->assertDatabaseCount('journal_entries', 4);
        $this->assertEquals(8000, DB::table('transactions')->sum('amount'));
        $this->assertSame(1, PayrollStatutoryService::batches()->get()->count());
        $this->get('/payroll/statutory/batches/'.$first->id)->assertOk()->assertSee('8,000.00')->assertSee('Month allocations');
        $form = $this->get('/payroll/statutory/batches/create?company_id=1&kind=epf')->assertOk();
        $this->assertSame([$feb], $form->viewData('rows')->pluck('run_id')->all());
        $this->get('/payroll/statutory?year=2026&kind=epf&status=paid')->assertOk()->assertSee('January 2026')->assertDontSee('February 2026');
        $this->get('/payroll/statutory?year=2026&kind=epf&status=partial')->assertOk()->assertSee('February 2026')->assertDontSee('January 2026');
        $this->postJson('/payroll/payments/'.$first->id.'/reverse', ['date' => '2026-03-16', 'reason' => 'Authority returned January allocation'])->assertRedirect();
        $this->assertSame(600000, $this->state($jan)['balance']);
        $batch = PayrollStatutoryService::batches()->first();
        $this->assertEquals(800000, $batch->total_cents);
        $this->assertEquals(200000, $batch->posted_cents);
        $this->get('/payroll/statutory')->assertOk()->assertSee('Partially reversed');
    }

    public function test_batch_rejects_overpayment_duplicates_wrong_scope_and_rolls_back_every_month(): void
    {
        $jan = $this->createRun();
        $feb = $this->createRun('2026-02');
        $entries = [['run_id' => $jan, 'amount' => 6000], ['run_id' => $feb, 'amount' => 6001]];
        $this->statutoryBatch($entries)->assertUnprocessable();
        $this->assertDatabaseCount('payroll_payments', 0);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('journal_entries', 2);
        $this->statutoryBatch([['run_id' => $jan, 'amount' => 1], ['run_id' => $jan, 'amount' => 1]])->assertUnprocessable();
        $this->statutoryBatch([['run_id' => $jan, 'amount' => '1.001']])->assertUnprocessable();
        $this->statutoryBatch([['run_id' => $jan, 'amount' => 1]], ['kind' => 'salary'])->assertUnprocessable();
        $this->statutoryBatch([['run_id' => $jan, 'amount' => 1]], ['paid_on' => '2025-12-31'])->assertUnprocessable();
        $this->statutoryBatch([['run_id' => $jan, 'amount' => 1]], ['paid_on' => '2026-11-09'])->assertUnprocessable();
        DB::table('companies')->insert(['id' => 2, 'name' => 'Other company', 'base_currency' => 'LKR']);
        DB::table('bank_accounts')->insert(['id' => 2, 'company_id' => 2, 'bank_name' => 'Other bank', 'account_no' => 'WRONG', 'currency' => 'LKR']);
        $this->statutoryBatch([['run_id' => $jan, 'amount' => 1]], ['company_id' => 2])->assertUnprocessable();
        $this->statutoryBatch([['run_id' => $jan, 'amount' => 1]], ['bank_account_id' => 2])->assertUnprocessable();
        $draft = $this->createRun('2026-03', false);
        $this->statutoryBatch([['run_id' => $jan, 'amount' => 1], ['run_id' => $draft, 'amount' => 1]], ['paid_on' => '2026-04-01'])->assertUnprocessable();
        $this->assertDatabaseCount('payroll_payments', 0);
        $key = (string) Str::uuid();
        $this->statutoryBatch([['run_id' => $jan, 'amount' => 1]], ['request_key' => $key])->assertOk();
        $this->statutoryBatch([['run_id' => $feb, 'amount' => 1]], ['request_key' => $key])->assertUnprocessable();
        $this->assertDatabaseCount('payroll_payments', 1);
    }

    public function test_etf_and_tax_batches_have_private_receipts_and_role_protection(): void
    {
        Storage::fake('local');
        $run = $this->createRun();
        foreach (['etf' => 900, 'apit' => 1000] as $kind => $amount) {
            $this->statutoryBatch([], ['kind' => $kind, 'entries_json' => json_encode([['run_id' => $run, 'amount' => $amount]]), 'receipt' => UploadedFile::fake()->create('bank.pdf', 1, 'application/pdf')])->assertOk();
            $this->assertSame(0, $this->state($run, $kind)['balance']);
        }
        $payment = DB::table('payroll_payments')->first();
        Storage::disk('local')->assertExists($payment->receipt_path);
        $this->get('/payroll/payments/'.$payment->id.'/receipt')->assertOk();
        $this->assertSame(600000, $this->state($run)['balance']);
        $this->get('/payroll/statutory?year=2025')->assertOk()->assertViewHas('rows', fn ($rows) => $rows->isEmpty());
        $this->actingAs(User::factory()->create(['role' => 'viewer', 'is_active' => true]));
        $this->get('/payroll/statutory/batches/create')->assertForbidden();
        $this->get('/payroll/statutory/batches/'.$payment->id)->assertForbidden();
        $this->statutoryBatch([['run_id' => $run, 'amount' => 1]])->assertForbidden();
    }
}
