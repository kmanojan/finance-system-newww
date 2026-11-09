<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\FinancialReportingService;
use App\Services\PayrollService;
use Database\Seeders\SalaryComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 6));
        DB::table('companies')->insert(['id' => 1, 'name' => 'Payroll Test', 'base_currency' => 'LKR']);
        DB::table('departments')->insert(['id' => 1, 'company_id' => 1, 'name' => 'Finance']);
        DB::table('bank_accounts')->insert(['id' => 1, 'company_id' => 1, 'bank_name' => 'Test Bank', 'account_no' => '12345', 'currency' => 'LKR']);
        $this->seed(SalaryComponentSeeder::class);
        $this->employee = $this->makeEmployee(99001);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    }

    private function makeEmployee(int $external): Employee
    {
        $e = Employee::create(['external_id' => $external, 'employee_code' => 'EMP-'.$external, 'first_name' => 'Payroll', 'last_name' => 'Tester', 'full_name' => 'Payroll Tester '.$external, 'status' => 'active', 'basic_salary' => 100000, 'currency' => 'LKR', 'epf_number' => '123']);
        foreach (['BASIC' => 100000, 'FIXED_ALLOW' => 20000, 'TRAVEL_ALLOW' => 10000, 'EPF_EE' => 8, 'EPF_ER' => 12, 'ETF_ER' => 3, 'APIT' => 2000] as $code => $value) {
            $e->salaryComponents()->create(['salary_component_id' => SalaryComponent::where('code', $code)->value('id'), 'value' => $value, 'is_enabled' => true]);
        }

        return $e;
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['month' => '2026-10', 'company_id' => 1, 'department_id' => 1, 'currency' => 'LKR', 'employee_ids' => [$this->employee->id], 'salary_due_date' => '2026-10-31', 'statutory_due_date' => '2026-11-30', 'tax_due_date' => '2026-11-15', 'salary_confirmation' => 1], $overrides);
    }

    private function createRun(array $overrides = []): int
    {
        $this->post('/payroll', $this->payload($overrides))->assertRedirect();

        return DB::table('payroll_runs')->max('id');
    }

    private function adjustments(array $overrides = []): array
    {
        return array_replace(['overtime' => 5000, 'bonus' => 10000, 'allowance' => 0, 'unpaid_leave' => 3000, 'advance' => 0, 'other_deduction' => 500, 'epf_base' => 117000, 'etf_base' => 117000, 'apit' => 2500, 'reviewed' => 1, 'notes' => 'Reviewed rates, earnings, 3 unpaid days, overtime and APIT calculation.'], $overrides);
    }

    private function approve(int $run, array $adjustments = []): void
    {
        foreach (PayrollService::items($run) as $i) {
            $this->post("/payroll/$run/employees/$i->id", $this->adjustments($adjustments))->assertRedirect();
        }
        $this->post("/payroll/$run/submit")->assertRedirect();
        $this->post("/payroll/$run/approve")->assertRedirect();
        $this->assertDatabaseHas('payroll_runs', ['id' => $run, 'status' => 'approved']);
    }

    private function pay(int $run, string $kind, float $amount, array $extra = [])
    {
        return $this->postJson("/payroll/$run/payments", array_replace(['kind' => $kind, 'amount' => $amount, 'paid_on' => '2026-11-01', 'reference' => 'BANK-'.Str::random(6), 'request_key' => (string) Str::uuid(), 'bank_account_id' => 1], $extra));
    }

    private function ledger(string $code): float
    {
        return (float) DB::table('journal_entry_lines as l')->join('accounts as a', 'a.id', '=', 'l.account_id')->where('a.code', $code)->selectRaw('COALESCE(SUM(l.debit-l.credit),0) as balance')->value('balance');
    }

    public function test_monthly_snapshot_adjustments_approval_and_rendering(): void
    {
        $this->get('/payroll')->assertOk();
        $this->get('/payroll/create')->assertOk()->assertSee('Create draft');
        $run = $this->createRun();
        $item = PayrollService::items($run)->first();
        $this->assertEquals(13000000, $item->gross_cents); // BASIC component never doubles the base.
        $this->assertDatabaseCount('journal_entries', 0);
        $this->get("/payroll/$run")->assertOk();
        $this->get("/payroll/$run/employees/$item->id")->assertOk();
        $this->postJson("/payroll/$run/submit")->assertUnprocessable();
        $this->employee->update(['basic_salary' => 200000]);
        $this->approve($run);
        $item = PayrollService::items($run)->first();
        $c = $item->calculation;
        $this->assertEquals(14200000, $c['gross']);
        $this->assertEquals(936000, $c['ee']);
        $this->assertEquals(12964000, $c['net']);
        $this->assertEquals(15955000, $c['cost']);
        $this->assertEquals(159550, $this->ledger('5210'));
        $this->assertEquals(-23400, $this->ledger('2320'));
        $this->assertEquals(0, DB::table('journal_entry_lines')->sum('debit') - DB::table('journal_entry_lines')->sum('credit'));
        $this->postJson("/payroll/$run/employees/$item->id", $this->adjustments())->assertUnprocessable();
        $this->postJson("/payroll/$run/approve")->assertUnprocessable();
        $this->assertDatabaseCount('journal_entries', 1);
        $this->get("/payroll/$run")->assertOk()->assertSee('Record payment');
        $this->get("/payroll/$run/employees/$item->id/payslip")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get("/payroll/$run/export")->assertOk()->assertDownload();
    }

    public function test_partial_and_statutory_payments_clear_liabilities_without_duplicate_expense(): void
    {
        $run = $this->createRun();
        $this->approve($run);
        $item = PayrollService::items($run)->first();
        $this->pay($run, 'salary', 20000)->assertRedirect();
        $this->assertEquals(2000000, PayrollService::paid($item->id, 'salary'));
        $this->assertEquals(-109640, $this->ledger('2310'));
        $this->pay($run, 'salary', 109640)->assertRedirect();
        foreach (['epf' => 23400, 'etf' => 3510, 'apit' => 2500, 'other' => 500] as $kind => $amount) {
            $this->pay($run, $kind, $amount)->assertRedirect();
        }
        $this->assertEquals(0, collect(PayrollService::balances(PayrollService::run($run), PayrollService::items($run)))->sum('balance'));
        $this->assertEquals(159550, $this->ledger('5210'));
        $this->assertEquals(-159550, $this->ledger('1010'));
        $this->assertEquals(159550, DB::table('transactions')->where('type', 'expense')->sum('amount'));
        $this->pay($run, 'salary', 0.01)->assertUnprocessable();
        $payment = DB::table('payroll_payments')->first();
        $this->deleteJson('/transactions/'.$payment->transaction_id)->assertUnprocessable();
        $this->postJson("/payroll/$run/void", ['date' => '2026-11-06', 'reason' => 'Cannot void paid payroll'])->assertUnprocessable();
        $this->assertEquals(0, FinancialReportingService::unpostedCount());
    }

    public function test_bulk_payment_allocates_to_employee_balances_and_checks_idempotency(): void
    {
        $second = $this->makeEmployee(99002);
        $run = $this->createRun(['employee_ids' => [$this->employee->id, $second->id]]);
        $this->approve($run);
        $key = (string) Str::uuid();
        $this->pay($run, 'salary', 150000, ['request_key' => $key])->assertRedirect();
        $items = PayrollService::items($run);
        $this->assertEquals(12964000, PayrollService::paid($items[0]->id, 'salary'));
        $this->assertEquals(2036000, PayrollService::paid($items[1]->id, 'salary'));
        $this->pay($run, 'salary', 100, ['request_key' => $key])->assertUnprocessable();
        $this->assertDatabaseCount('payroll_payments', 1);
        $this->pay($run, 'epf', 100, ['item_id' => 999999])->assertUnprocessable();
    }

    public function test_advance_payment_recovery_and_reversals_preserve_history(): void
    {
        $this->postJson('/payroll/advances', ['employee_id' => $this->employee->id, 'company_id' => 1, 'department_id' => 1, 'currency' => 'LKR', 'amount' => 10000, 'paid_on' => '2026-10-15', 'recovery_month' => '2026-10', 'reference' => 'Advance 1', 'request_key' => (string) Str::uuid(), 'bank_account_id' => 1])->assertRedirect();
        $advance = DB::table('payroll_payments')->first();
        $this->assertEquals(10000, $this->ledger('1250'));
        $run = $this->createRun();
        $this->approve($run, ['advance' => 10000]);
        $this->assertEquals(0, PayrollService::advanceBalance($this->employee->id, 1, 'LKR'));
        $this->assertEquals(0, $this->ledger('1250'));
        $this->postJson("/payroll/payments/$advance->id/reverse", ['date' => '2026-11-06', 'reason' => 'Cannot reverse recovered advance'])->assertUnprocessable();
        $this->pay($run, 'salary', 10000)->assertRedirect();
        $p = DB::table('payroll_payments')->orderByDesc('id')->first();
        $this->postJson("/payroll/payments/$p->id/reverse", ['date' => '2026-10-31', 'reason' => 'Invalid reversal date'])->assertUnprocessable();
        $this->postJson("/payroll/payments/$p->id/reverse", ['date' => '2026-11-06', 'reason' => 'Bank payment returned'])->assertRedirect();
        $this->assertEquals(0, PayrollService::paid(PayrollService::items($run)->first()->id, 'salary'));
        $this->postJson("/payroll/$run/void", ['date' => '2026-11-06', 'reason' => 'Payroll correction needed'])->assertRedirect();
        $this->assertEquals(0, $this->ledger('5210'));
        $this->assertEquals(10000, $this->ledger('1250'));
        $this->postJson("/payroll/payments/$advance->id/reverse", ['date' => '2026-11-06', 'reason' => 'Advance was returned'])->assertRedirect();
        $this->assertEquals(0, $this->ledger('1250'));
        $this->assertEquals(0, $this->ledger('1010'));
        $this->assertEquals(2, DB::table('payroll_payments')->where('status', 'reversed')->count());
        $this->createRun(); // Reissue permitted only after voiding.
        $this->assertDatabaseCount('payroll_runs', 2);
        $this->get('/payroll/advances')->assertOk();
    }

    public function test_validation_duplicates_wrong_scope_negative_net_and_period_lock(): void
    {
        $run = $this->createRun();
        $item = PayrollService::items($run)->first();
        $this->postJson('/payroll', $this->payload())->assertUnprocessable();
        DB::table('companies')->insert(['id' => 2, 'name' => 'Other company']);
        DB::table('departments')->insert(['id' => 2, 'name' => 'Other department', 'company_id' => 2]);
        $this->postJson('/payroll', $this->payload(['company_id' => 2]))->assertUnprocessable();
        $this->postJson('/payroll', $this->payload(['company_id' => 2, 'department_id' => 2]))->assertUnprocessable();
        $this->assertDatabaseCount('payroll_runs', 1);
        $this->postJson("/payroll/$run/employees/$item->id", $this->adjustments(['other_deduction' => 200000]))->assertUnprocessable();
        $this->postJson("/payroll/$run/employees/$item->id", $this->adjustments(['advance' => 1]))->assertUnprocessable();
        $this->pay($run, 'salary', 1000)->assertUnprocessable();
        $this->approve($run);
        DB::table('bank_accounts')->insert(['id' => 2, 'company_id' => 2, 'bank_name' => 'Wrong bank', 'account_no' => '22', 'currency' => 'LKR']);
        $this->pay($run, 'salary', 1000, ['bank_account_id' => 2])->assertUnprocessable();
        $this->pay($run, 'salary', 1000, ['paid_on' => '2026-09-30'])->assertUnprocessable();
        $this->pay($run, 'epf', 1000, ['paid_on' => '2026-10-30'])->assertUnprocessable();
        $this->assertDatabaseCount('transactions', 0);
        $year = DB::table('financial_years')->insertGetId(['company_id' => 1, 'title' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        DB::table('fiscal_periods')->insert(['financial_year_id' => $year, 'period_name' => 'November', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'status' => 'hard_closed']);
        $this->pay($run, 'salary', 1000)->assertUnprocessable();
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('payroll_payments', 0);
    }

    public function test_payroll_privacy_manager_approval_and_private_receipts(): void
    {
        Storage::fake('local');
        $run = $this->createRun();
        $this->approve($run);
        $this->pay($run, 'salary', 1000, ['receipt' => UploadedFile::fake()->create('receipt.pdf', 1, 'application/pdf')])->assertRedirect();
        $p = DB::table('payroll_payments')->first();
        $this->get("/payroll/payments/$p->id/receipt")->assertOk();
        foreach (['staff', 'viewer'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]));
            foreach (['/payroll', "/payroll/$run", '/payroll/create', '/payroll/advances', "/payroll/$run/export", "/payroll/payments/$p->id/receipt", '/employees', '/api/employees'] as $url) {
                $this->get($url)->assertForbidden();
            }
            $this->pay($run, 'salary', 1)->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'accountant', 'is_active' => true]));
        $this->get('/payroll')->assertOk();
        $this->postJson("/payroll/$run/approve")->assertForbidden();
        $this->postJson("/payroll/$run/void", ['date' => '2026-11-06', 'reason' => 'Not authorised'])->assertForbidden();
        $this->postJson("/payroll/payments/$p->id/reverse", ['date' => '2026-11-06', 'reason' => 'Not authorised'])->assertForbidden();
    }

    public function test_closed_month_blocks_approval_and_zero_pay_month_is_supported(): void
    {
        $run = $this->createRun();
        $item = PayrollService::items($run)->first();
        $this->post("/payroll/$run/employees/$item->id", $this->adjustments(['overtime' => 0, 'bonus' => 0, 'unpaid_leave' => 130000, 'other_deduction' => 0, 'epf_base' => 0, 'etf_base' => 0, 'apit' => 0]))->assertRedirect();
        $this->post("/payroll/$run/submit")->assertRedirect();
        $year = DB::table('financial_years')->insertGetId(['company_id' => 1, 'title' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $period = DB::table('fiscal_periods')->insertGetId(['financial_year_id' => $year, 'period_name' => 'October', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'status' => 'hard_closed']);
        $this->postJson("/payroll/$run/approve")->assertUnprocessable();
        $this->assertDatabaseHas('payroll_runs', ['id' => $run, 'status' => 'review']);
        DB::table('fiscal_periods')->where('id', $period)->update(['status' => 'open']);
        $this->postJson("/payroll/$run/approve")->assertRedirect();
        $this->assertDatabaseHas('payroll_runs', ['id' => $run, 'status' => 'approved', 'journal_entry_id' => null]);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->pay($run, 'salary', 1)->assertUnprocessable();
    }

    public function test_imported_cashbook_migration_preserves_existing_payment_and_fills_metadata(): void
    {
        $category = DB::table('categories')->insertGetId(['name' => 'Legacy expense', 'type' => 'expense']);
        $id = DB::table('transactions')->insertGetId(['type' => 'expense', 'category_id' => $category, 'department_id' => 1, 'amount' => 123.45, 'currency' => 'LKR', 'transaction_date' => '2026-10-31']);
        Schema::table('transactions', fn ($t) => $t->dropColumn(['payment_status', 'due_date', 'reconciled_at']));
        $migration = require database_path('migrations/2026_11_06_000007_align_payroll_cash_transactions.php');
        $migration->up();
        $this->assertDatabaseHas('transactions', ['id' => $id, 'amount' => 123.45, 'payment_status' => 'completed', 'due_date' => null]);
        $migration->up(); // Safe on already-aligned installations.
        $this->assertDatabaseCount('transactions', 1);
    }
}
