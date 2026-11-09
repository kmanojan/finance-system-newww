<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\PayrollPayablesService;
use App\Services\PayrollService;
use App\Services\SalaryAdvanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PayrollSalaryBatchTest extends TestCase
{
    use RefreshDatabase;

    private array $employees;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 8));
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        DB::table('companies')->insert(['id' => 1, 'name' => 'Batch test', 'base_currency' => 'LKR']);
        DB::table('departments')->insert(['id' => 1, 'company_id' => 1, 'name' => 'Finance']);
        DB::table('bank_accounts')->insert(['id' => 1, 'company_id' => 1, 'bank_name' => 'Batch bank', 'account_no' => '123', 'currency' => 'LKR']);
        $this->employees = [];
        foreach (['Manojan' => 30000, 'Batch Employee Two' => 20000, 'Batch Employee Three' => 15000] as $name => $salary) {
            $this->employees[] = Employee::create(['full_name' => $name, 'first_name' => $name, 'last_name' => '', 'basic_salary' => $salary, 'currency' => 'LKR', 'status' => 'active']);
        }
    }

    private function createRun(bool $approve = true): int
    {
        $run = PayrollService::create(['month' => '2026-01', 'company_id' => 1, 'department_id' => 1, 'currency' => 'LKR',
            'employee_ids' => array_map(fn ($employee) => $employee->id, $this->employees), 'salary_due_date' => '2026-01-31', 'statutory_due_date' => '2026-02-28', 'tax_due_date' => '2026-02-15']);
        if ($approve) {
            DB::table('payroll_items')->where('payroll_run_id', $run)->update(['reviewed' => true]);
            PayrollService::transition($run, 'submit');
            PayrollService::transition($run, 'approve');
        }

        return $run;
    }

    private function batch(int $run, array $entries, array $extra = [])
    {
        return $this->postJson('/payroll/'.$run.'/salary-batches', $extra + ['entries' => $entries, 'paid_on' => '2026-02-01', 'reference' => 'January release', 'request_key' => (string) Str::uuid(), 'bank_account_id' => 1]);
    }

    private function summary(int $run): array
    {
        return PayrollService::salaryPaymentStatus(PayrollService::run($run), PayrollService::items($run));
    }

    private function ledger(string $code): float
    {
        return (float) DB::table('journal_entry_lines as l')->join('accounts as a', 'a.id', '=', 'l.account_id')->where('a.code', $code)->selectRaw('COALESCE(SUM(l.debit-l.credit),0) as balance')->value('balance');
    }

    public function test_three_releases_keep_employee_balances_after_processing_is_completed(): void
    {
        $run = $this->createRun();
        $items = PayrollService::items($run);
        $this->assertSame('unpaid', $this->summary($run)['state']);
        $this->get('/payroll/'.$run.'/salary-batches/create')->assertOk()->assertSee('Record salary batch');
        $this->batch($run, [], ['entries_json' => json_encode([['item_id' => $items[0]->id, 'amount' => '20000.00'], ['item_id' => $items[1]->id, 'amount' => '5000.00']]), 'amount' => 1])->assertRedirect();
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('payroll_payments', ['amount_cents' => 2500000]);
        $this->assertSame('partial', $this->summary($run)['state']);
        $this->assertSame(4000000, $this->summary($run)['balance']);
        $this->assertSame([1000000, 1500000, 1500000], PayrollPayablesService::items()->sortBy('id')->pluck('balance_cents')->all());
        $report = $this->get('/reports/party-ledger?role=employee')->assertOk()->assertSee('Salary pending');
        $manojan = $report->viewData('partySummaries')->firstWhere('id', $this->employees[0]->party_id);
        $this->assertEquals(10000, $manojan->salary_balance);
        $this->assertEquals(10000, $manojan->total_payables);
        $this->get('/reports/party-ledger?role=employee&salary_detail=1&party_id='.$this->employees[0]->party_id)->assertOk()->assertSee('employee payables')->assertSee('Record salary payment');
        $this->get('/payroll/'.$run.'/salary-batches/create?employee_id='.$this->employees[0]->id)->assertOk()->assertSee('value="Manojan"', false);
        $this->post('/payroll/'.$run.'/complete')->assertRedirect();
        $this->assertDatabaseHas('payroll_runs', ['id' => $run, 'status' => 'completed']);
        $this->assertDatabaseCount('journal_entries', 2); // Approval plus first bank release; completion posts nothing.
        $this->batch($run, [['item_id' => $items[0]->id, 'amount' => '10000.00']])->assertRedirect();
        $this->assertSame(0, PayrollPayablesService::items()->firstWhere('employee_id', $this->employees[0]->id)->balance_cents);
        $pending = $this->get('/payroll/'.$run.'/salary-batches/create')->assertOk();
        $this->assertSame([$items[1]->id, $items[2]->id], $pending->viewData('batchEmployees')->pluck('id')->all());
        $this->assertSame([1500000, 1500000], $pending->viewData('batchEmployees')->pluck('balance')->all());
        $paidEmployee = $this->get('/payroll/'.$run.'/salary-batches/create?employee_id='.$this->employees[0]->id)->assertOk();
        $this->assertSame('', $paidEmployee->viewData('initialBatchSearch'));
        $overview = $this->get('/payroll/'.$run)->assertOk()->assertSee('Salary batches')->assertSee('Batch bank');
        $this->assertSame([1, 2], $overview->viewData('salaryBatches')->pluck('employee_count')->all());
        $this->assertSame([1000000, 2500000], $overview->viewData('salaryBatches')->pluck('amount_cents')->map(fn ($amount) => (int) $amount)->all());
        $this->batch($run, [['item_id' => $items[1]->id, 'amount' => '15000.00'], ['item_id' => $items[2]->id, 'amount' => '15000.00']])->assertRedirect();
        $this->assertSame('paid', $this->summary($run)['state']);
        $this->assertSame(0, $this->summary($run)['balance']);
        $this->assertSame(0, PayrollPayablesService::items()->sum('balance_cents'));
        $this->assertDatabaseCount('payroll_payments', 3);
        $this->assertEquals(65000, $this->ledger('5210'));
        $this->assertEquals(0, $this->ledger('2310'));
        $this->get('/payroll/'.$run.'/salary-batches/create')->assertOk()->assertSee('All salaries are paid')->assertDontSee('data-salary-batch', false);
        $this->get('/payroll/'.$run)->assertOk()->assertSee('Processing: Completed')->assertSee('Salary: Paid');
        $this->get('/payroll/'.$run.'/employees/'.$items[0]->id)->assertOk()->assertSee('Salary payment batches')->assertSee('20,000.00')->assertSee('10,000.00');
        $this->get('/payroll/'.$run.'/employees/'.$items[0]->id.'/payslip')->assertOk();
    }

    public function test_overpayments_foreign_employees_duplicates_and_replays_are_rejected_atomically(): void
    {
        $run = $this->createRun();
        $item = PayrollService::items($run)->first();
        foreach ([
            [['item_id' => $item->id, 'amount' => 20000], ['item_id' => 99999, 'amount' => 1]],
            [['item_id' => $item->id, 'amount' => 30000.01]],
            [['item_id' => $item->id, 'amount' => -1]],
            [['item_id' => $item->id, 'amount' => 1.001]],
            [['item_id' => $item->id, 'amount' => 1], ['item_id' => $item->id, 'amount' => 1]],
            [],
        ] as $entries) {
            $this->batch($run, $entries)->assertUnprocessable();
        }
        $this->assertDatabaseCount('transactions', 0);
        $key = (string) Str::uuid();
        $this->batch($run, [['item_id' => $item->id, 'amount' => 20000]], ['request_key' => $key])->assertRedirect();
        $this->batch($run, [['item_id' => $item->id, 'amount' => 10000]], ['request_key' => $key])->assertUnprocessable();
        $this->batch($run, [['item_id' => $item->id, 'amount' => 10000.01]])->assertUnprocessable();
        $this->assertDatabaseCount('payroll_payments', 1);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_reversing_a_completed_payroll_batch_reopens_only_its_employee_balances(): void
    {
        $run = $this->createRun();
        $items = PayrollService::items($run);
        $this->batch($run, [['item_id' => $items[0]->id, 'amount' => 30000], ['item_id' => $items[1]->id, 'amount' => 5000]])->assertRedirect();
        $payment = DB::table('payroll_payments')->first();
        PayrollService::transition($run, 'complete');
        $this->get('/payroll/'.$run.'/salary-batches/'.$payment->id)->assertOk()->assertSee('30,000.00')->assertSee('5,000.00');
        $this->assertCount(2, $this->get('/payroll/'.$run.'/salary-batches/create')->viewData('batchEmployees'));
        $this->postJson('/payroll/payments/'.$payment->id.'/reverse', ['date' => '2026-02-02', 'reason' => 'Bank returned this salary batch'])->assertRedirect();
        $this->assertSame('unpaid', $this->summary($run)['state']);
        $this->assertSame(6500000, PayrollPayablesService::items()->sum('balance_cents'));
        $this->assertSame(0, PayrollPayablesService::payments()->count());
        $this->assertDatabaseHas('payroll_runs', ['id' => $run, 'status' => 'completed']);
        $this->assertCount(3, $this->get('/payroll/'.$run.'/salary-batches/create')->viewData('batchEmployees'));
        $overview = $this->get('/payroll/'.$run)->assertOk()->assertSee('Reversed');
        $this->assertSame('reversed', $overview->viewData('salaryBatches')->first()->status);
        $this->get('/payroll/'.$run.'/salary-batches/'.$payment->id)->assertOk()->assertSee('Reversed on');
        $this->postJson('/payroll/'.$run.'/void', ['date' => '2026-02-02', 'reason' => 'Correct completed payroll amounts'])->assertRedirect();
        $this->assertSame(0, PayrollPayablesService::items()->count());
    }

    public function test_drafts_roles_and_statutory_payments_cannot_clear_employee_salary(): void
    {
        $component = SalaryComponent::create(['code' => 'EPF_EE', 'name' => 'Employee EPF', 'type' => 'deduction', 'calculation_type' => 'percentage', 'calculation_base' => 'on_epf_base', 'is_active' => true]);
        $this->employees[0]->salaryComponents()->create(['salary_component_id' => $component->id, 'value' => 8, 'is_enabled' => true]);
        $run = $this->createRun(false);
        $item = PayrollService::items($run)->first();
        $this->assertCount(0, PayrollPayablesService::items());
        $this->batch($run, [['item_id' => $item->id, 'amount' => 1]])->assertUnprocessable();
        $this->postJson('/payroll/'.$run.'/complete')->assertUnprocessable();
        DB::table('payroll_items')->where('payroll_run_id', $run)->update(['reviewed' => true]);
        PayrollService::transition($run, 'submit');
        PayrollService::transition($run, 'approve');
        $this->postJson('/payroll/'.$run.'/payments', ['kind' => 'epf', 'amount' => 2400, 'paid_on' => '2026-02-01', 'reference' => 'EPF only', 'request_key' => (string) Str::uuid(), 'bank_account_id' => 1])->assertRedirect();
        $this->assertSame('unpaid', $this->summary($run)['state']);
        $this->assertSame(6260000, PayrollPayablesService::items()->sum('balance_cents'));
        $this->actingAs(User::factory()->create(['role' => 'accountant', 'is_active' => true]));
        $this->postJson('/payroll/'.$run.'/complete')->assertForbidden();
        $this->batch($run, [['item_id' => $item->id, 'amount' => 1]])->assertRedirect();
        $this->actingAs(User::factory()->create(['role' => 'viewer', 'is_active' => true]));
        $this->get('/payroll/'.$run.'/salary-batches/create')->assertForbidden();
        $this->get('/reports/party-ledger')->assertOk()->assertDontSee('Salary pending')->assertDontSee('January 2026 net salary');
    }

    public function test_completed_payroll_keeps_advance_recoveries_reserved(): void
    {
        PayrollService::advance(['employee_id' => $this->employees[0]->id, 'company_id' => 1, 'department_id' => 1, 'currency' => 'LKR', 'amount' => 10000, 'paid_on' => '2026-01-15', 'recovery_month' => '2026-01', 'reference' => 'Advance before payroll', 'request_key' => (string) Str::uuid(), 'bank_account_id' => 1]);
        $run = $this->createRun();
        PayrollService::transition($run, 'complete');
        $this->assertSame(0, PayrollService::advanceBalance($this->employees[0]->id, 1, 'LKR'));
        $this->assertSame(1000000, (int) SalaryAdvanceService::payments()->first()->recovered_cents);
        $this->assertCount(0, SalaryAdvanceService::eligible([$this->employees[0]->id], 1, 'LKR', '2026-02-01'));
    }
}
