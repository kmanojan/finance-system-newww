<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Services\HistoricalPayrollImportService;
use App\Services\HistoricalSalaryAdvanceService as History;
use App\Services\PayrollService;
use App\Services\SalaryAdvanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistoricalSalaryAdvanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 8));
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        DB::table('companies')->insert(['id' => 1, 'name' => 'Historical advances', 'base_currency' => 'LKR']);
        DB::table('departments')->insert(['id' => 1, 'company_id' => 1, 'name' => 'Finance']);
        DB::table('bank_accounts')->insert(['id' => 1, 'company_id' => 1, 'bank_name' => 'HNB test', 'account_no' => 'TEST-6990', 'currency' => 'LKR']);
        Employee::create(['id' => 1, 'full_name' => 'Historical advance employee', 'first_name' => 'Historical', 'last_name' => 'Employee', 'employee_code' => 'HIST-1', 'currency' => 'LKR', 'basic_salary' => 100000, 'status' => 'inactive']);
    }

    private function source(int $employee = 1, string $month = '2025-01', int $loan = 0): array
    {
        return ['employee_id' => $employee, 'name' => 'Historical advance employee', 'month' => $month, 'sheet' => $month, 'row' => $employee + 3,
            'basic' => 10000000, 'package' => 10000000, 'commission' => 0, 'no_pay' => 0, 'tax' => 0, 'epf_ee' => 0, 'epf_er' => 0, 'etf' => 0,
            'advance' => 2000000, 'loan' => $loan, 'net' => 8000000 - $loan];
    }

    private function createRuns(array $rows): array
    {
        return HistoricalPayrollImportService::import(['version' => 1, 'source_url' => 'https://docs.google.com/spreadsheets/d/test/edit', 'source_sha256' => str_repeat('a', 64), 'rows' => $rows], 1, 1);
    }

    private function import(int $run)
    {
        return $this->postJson('/payroll/'.$run.'/historical-advances', ['bank_account_id' => 1, 'version' => History::version(PayrollService::run($run), History::candidates($run))]);
    }

    private function approve(int $run): void
    {
        DB::table('payroll_items')->where('payroll_run_id', $run)->update(['reviewed' => true]);
        PayrollService::transition($run, 'submit');
        PayrollService::transition($run, 'approve');
    }

    public function test_import_records_past_bank_transfers_once_and_preserves_payroll_values_reviews_and_source(): void
    {
        $run = $this->createRuns([$this->source()])[0];
        DB::table('payroll_items')->where('payroll_run_id', $run)->update(['reviewed' => true]);
        PayrollService::transition($run, 'submit');
        $before = DB::table('payroll_items')->get()->toJson();
        $this->import($run)->assertOk()->assertJson(['created' => 1, 'existing' => 0, 'total' => 2000000]);
        $this->assertSame($before, DB::table('payroll_items')->get()->toJson());
        $this->assertDatabaseHas('payroll_runs', ['id' => $run, 'status' => 'review']);
        $this->assertDatabaseHas('payroll_payments', ['kind' => 'advance', 'amount_cents' => 2000000, 'paid_on' => '2025-01-10', 'recovery_month' => '2025-01-01', 'bank_account_id' => 1, 'employee_id' => 1]);
        $this->assertDatabaseHas('transactions', ['amount' => 20000, 'transaction_date' => '2025-01-10', 'payment_method' => 'bank_transfer', 'bank_account_id' => 1]);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseHas('accounts', ['code' => '1250', 'type' => 'asset']);
        $this->assertDatabaseMissing('accounts', ['code' => '5210']);
        $this->assertDatabaseCount('payroll_advance_recoveries', 0);
        $this->import($run)->assertOk()->assertJson(['created' => 0, 'existing' => 1]);
        $this->assertDatabaseCount('payroll_payments', 1);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->get('/payroll/advances/import-history?company_id=1')->assertOk()->assertSee('1 of 1')->assertSee('2025-01-10')->assertSee('20,000.00')
            ->assertSee('name="_token"', false)->assertSee('value="'.csrf_token().'"', false);
    }

    public function test_recovery_uses_its_exact_source_month_even_when_approved_out_of_order_and_is_not_proposed_again(): void
    {
        [$jan, $feb] = $this->createRuns([$this->source(), $this->source(1, '2025-02')]);
        $this->import($jan)->assertOk();
        $this->import($feb)->assertOk();
        $febItem = PayrollService::items($feb)->first();
        $febPayment = DB::table('payroll_historical_advance_links')->where('payroll_item_id', $febItem->id)->value('payroll_payment_id');
        $janItem = PayrollService::items($jan)->first();
        $janPayment = DB::table('payroll_historical_advance_links')->where('payroll_item_id', $janItem->id)->value('payroll_payment_id');
        $this->assertEmpty(SalaryAdvanceService::scheduledTotals([1], 1, '2025-03'));
        $this->approve($feb);
        $this->assertDatabaseHas('payroll_advance_recoveries', ['payroll_payment_id' => $febPayment, 'payroll_item_id' => $febItem->id, 'amount_cents' => 2000000]);
        $this->assertDatabaseMissing('payroll_advance_recoveries', ['payroll_payment_id' => $janPayment]);
        $this->assertSame(2000000, PayrollService::advanceBalance(1, 1, 'LKR'));
        $this->approve($jan);
        $this->assertSame(0, PayrollService::advanceBalance(1, 1, 'LKR'));
        $this->assertDatabaseCount('payroll_payments', 2);
        PayrollService::transition($feb, 'void', ['date' => '2026-11-08', 'reason' => 'Correct historical payroll']);
        $this->assertSame(2000000, PayrollService::advanceBalance(1, 1, 'LKR'));
        $this->assertEmpty(SalaryAdvanceService::scheduledTotals([1], 1, '2026-12'));
        $this->postJson('/payroll/advances/'.$febPayment.'/schedule', ['recovery_month' => '2026-12'])->assertUnprocessable();
    }

    public function test_stale_sources_wrong_banks_duplicates_and_invalid_roles_do_not_post_any_payments(): void
    {
        $run = $this->createRuns([$this->source()])[0];
        $version = History::version(PayrollService::run($run), History::candidates($run));
        DB::table('payroll_items')->where('payroll_run_id', $run)->update(['advance_cents' => 1999999]);
        $this->postJson('/payroll/'.$run.'/historical-advances', ['bank_account_id' => 1, 'version' => $version])->assertUnprocessable();
        $this->import($run)->assertUnprocessable();
        DB::table('payroll_items')->where('payroll_run_id', $run)->update(['advance_cents' => 2000000]);
        DB::table('bank_accounts')->insert(['id' => 2, 'company_id' => 1, 'bank_name' => 'USD test', 'account_no' => 'USD', 'currency' => 'USD']);
        $this->postJson('/payroll/'.$run.'/historical-advances', ['bank_account_id' => 2, 'version' => $version])->assertUnprocessable();
        $this->actingAs(User::factory()->create(['role' => 'accountant', 'is_active' => true]));
        $this->import($run)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'viewer', 'is_active' => true]));
        $this->get('/payroll/advances/import-history')->assertForbidden();
        $this->assertDatabaseCount('payroll_payments', 0);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_month_rolls_back_if_a_possible_existing_manual_payment_is_found(): void
    {
        Employee::create(['id' => 2, 'full_name' => 'Second employee', 'first_name' => 'Second', 'last_name' => 'Employee', 'employee_code' => 'HIST-2', 'currency' => 'LKR', 'basic_salary' => 100000]);
        $run = $this->createRuns([$this->source(), $this->source(2)])[0];
        PayrollService::advance(['employee_id' => 2, 'company_id' => 1, 'department_id' => 1, 'currency' => 'LKR', 'amount' => 20000,
            'paid_on' => '2025-01-10', 'recovery_month' => '2025-01', 'reference' => 'Existing bank payment', 'request_key' => 'manual-existing', 'bank_account_id' => 1]);
        $this->import($run)->assertUnprocessable()->assertJsonValidationErrors('historical_advances');
        $this->assertDatabaseCount('payroll_payments', 1);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('payroll_historical_advance_links', 0);
    }

    public function test_missing_or_reversed_advance_blocks_approval_and_employee_loans_stay_separate(): void
    {
        $run = $this->createRuns([$this->source()])[0];
        DB::table('payroll_items')->where('payroll_run_id', $run)->update(['reviewed' => true]);
        PayrollService::transition($run, 'submit');
        $this->postJson('/payroll/'.$run.'/approve')->assertUnprocessable();
        $this->import($run)->assertOk();
        $p = DB::table('payroll_payments')->first();
        PayrollService::reversePayment($p->id, ['date' => '2025-01-11', 'reason' => 'Historical transfer correction']);
        $this->postJson('/payroll/'.$run.'/approve')->assertUnprocessable();
        $this->import($run)->assertUnprocessable();
        $loanRun = $this->createRuns([$this->source(1, '2025-02', 100000)])[0];
        $this->import($loanRun)->assertOk();
        DB::table('payroll_items')->where('payroll_run_id', $loanRun)->update(['reviewed' => true]);
        PayrollService::transition($loanRun, 'submit');
        $this->postJson('/payroll/'.$loanRun.'/approve')->assertUnprocessable()->assertJsonValidationErrors('payroll');
        $this->assertDatabaseHas('payroll_runs', ['id' => $loanRun, 'status' => 'review']);
        $this->assertDatabaseCount('loans', 0);
    }
}
