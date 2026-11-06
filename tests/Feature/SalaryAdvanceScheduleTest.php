<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Services\PayrollService;
use App\Services\SalaryAdvanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalaryAdvanceScheduleTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 6));
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        DB::table('companies')->insert(['id' => 1, 'name' => 'Advance test', 'base_currency' => 'LKR']);
        DB::table('departments')->insert(['id' => 1, 'company_id' => 1, 'name' => 'Finance']);
        $this->employee = Employee::create(['external_id' => 112233, 'employee_code' => 'ADV-1', 'first_name' => 'Advance', 'last_name' => 'Employee', 'full_name' => 'Advance Employee', 'status' => 'active', 'basic_salary' => 10000, 'currency' => 'LKR']);
    }

    private function advance(string $month = '2026-03', float $amount = 2000): int
    {
        $this->postJson('/payroll/advances', ['employee_id' => $this->employee->id, 'company_id' => 1, 'department_id' => 1, 'currency' => 'LKR', 'amount' => $amount, 'paid_on' => '2026-02-12', 'recovery_month' => $month, 'reference' => 'Advance reference', 'request_key' => (string) Str::uuid()])->assertRedirect();

        return DB::table('payroll_payments')->max('id');
    }

    private function draft(string $month): int
    {
        $this->postJson('/payroll', ['month' => $month, 'company_id' => 1, 'department_id' => 1, 'currency' => 'LKR', 'employee_ids' => [$this->employee->id], 'salary_due_date' => $month.'-28', 'statutory_due_date' => '2026-12-31', 'tax_due_date' => '2026-12-15', 'salary_confirmation' => 1])->assertCreated();

        return DB::table('payroll_runs')->max('id');
    }

    private function review(int $run, ?float $advance = null): void
    {
        $i = PayrollService::items($run)->first();
        $this->postJson("/payroll/$run/employees/$i->id", ['overtime' => 0, 'bonus' => 0, 'allowance' => 0, 'unpaid_leave' => 0, 'advance' => $advance ?? $i->advance_cents / 100, 'other_deduction' => 0, 'epf_base' => 10000, 'etf_base' => 10000, 'apit' => 0, 'notes' => 'Reviewed scheduled advance and payroll', 'reviewed' => 1])->assertRedirect();
        $this->post("/payroll/$run/submit")->assertRedirect();
    }

    private function preview(string $month, int $expected): void
    {
        $this->postJson('/payroll/preview', ['employee_ids' => [$this->employee->id], 'company_id' => 1, 'month' => $month])->assertOk()->assertJsonPath('employees.'.$this->employee->id.'.advance', $expected);
    }

    public function test_february_payment_deducts_in_march_only_and_never_twice(): void
    {
        $advance = $this->advance();
        $this->assertDatabaseHas('payroll_payments', ['id' => $advance, 'paid_on' => '2026-02-12', 'recovery_month' => '2026-03-01']);
        $this->preview('2026-02', 0);
        $feb = $this->draft('2026-02');
        $item = PayrollService::items($feb)->first();
        $this->assertEquals(0, $item->advance_cents);
        $this->postJson("/payroll/$feb/employees/$item->id", ['overtime' => 0, 'bonus' => 0, 'allowance' => 0, 'unpaid_leave' => 0, 'advance' => 2000, 'other_deduction' => 0, 'epf_base' => 10000, 'etf_base' => 10000, 'apit' => 0, 'notes' => 'Attempt early recovery', 'reviewed' => 1])->assertUnprocessable();
        $this->preview('2026-03', 200000);
        $march = $this->draft('2026-03');
        $i = PayrollService::items($march)->first();
        $this->assertEquals(200000, $i->advance_cents);
        $this->assertEquals(800000, $i->net_cents);
        $this->assertDatabaseCount('payroll_advance_recoveries', 0); // A draft never consumes an advance.
        $this->review($march);
        $this->post("/payroll/$march/approve")->assertRedirect();
        $this->assertDatabaseHas('payroll_advance_recoveries', ['payroll_payment_id' => $advance, 'payroll_item_id' => $i->id, 'amount_cents' => 200000]);
        $this->preview('2026-04', 0);
        $this->assertEquals(0, PayrollService::advanceBalance($this->employee->id, 1, 'LKR'));
        $this->get('/payroll/advances')->assertOk()->assertSee('March 2026')->assertSee('Recovered: 2,000.00');
        $this->get("/payroll/$march/employees/$i->id/payslip")->assertOk();
    }

    public function test_insufficient_net_salary_carries_remaining_advance_forward(): void
    {
        $id = $this->advance('2026-03', 15000);
        $march = $this->draft('2026-03');
        $this->assertEquals(1000000, PayrollService::items($march)->first()->advance_cents);
        $this->assertEquals(0, PayrollService::items($march)->first()->net_cents);
        $this->review($march);
        $this->post("/payroll/$march/approve")->assertRedirect();
        $this->preview('2026-04', 500000);
        $april = $this->draft('2026-04');
        $this->review($april);
        $this->post("/payroll/$april/approve")->assertRedirect();
        $this->assertEquals(1500000, SalaryAdvanceService::payments()->where('p.id', $id)->first()->recovered_cents);
        $this->preview('2026-05', 0);
    }

    public function test_rescheduling_rechecks_approval_and_reversal_is_specific_to_the_advance(): void
    {
        $id = $this->advance();
        $other = $this->advance('2026-05', 5000);
        $run = $this->draft('2026-03');
        $this->review($run);
        $this->postJson("/payroll/advances/$id/schedule", ['recovery_month' => '2026-04'])->assertRedirect();
        $this->postJson("/payroll/$run/approve")->assertUnprocessable();
        $this->assertDatabaseCount('payroll_advance_recoveries', 0);
        $this->postJson("/payroll/advances/$id/schedule", ['recovery_month' => '2026-03'])->assertRedirect();
        $this->post("/payroll/$run/approve")->assertRedirect();
        $this->postJson("/payroll/advances/$id/schedule", ['recovery_month' => '2026-04'])->assertUnprocessable();
        // Another unused advance cannot substitute for the already recovered one.
        $this->postJson("/payroll/payments/$id/reverse", ['date' => '2026-11-06', 'reason' => 'Incorrect payment'])->assertUnprocessable();
        $this->postJson("/payroll/payments/$other/reverse", ['date' => '2026-11-06', 'reason' => 'Unused advance returned'])->assertRedirect();
        $this->postJson("/payroll/$run/void", ['date' => '2026-11-06', 'reason' => 'Payroll needs correction'])->assertRedirect();
        $this->preview('2026-03', 200000);
        $this->postJson("/payroll/payments/$id/reverse", ['date' => '2026-11-06', 'reason' => 'Advance returned'])->assertRedirect();
        $this->preview('2026-03', 0);
    }

    public function test_month_is_required_and_cannot_precede_payment_month(): void
    {
        $data = ['employee_id' => $this->employee->id, 'company_id' => 1, 'department_id' => 1, 'currency' => 'LKR', 'amount' => 2000, 'paid_on' => '2026-02-12', 'reference' => 'Invalid advance', 'request_key' => (string) Str::uuid()];
        $this->postJson('/payroll/advances', $data)->assertUnprocessable();
        $this->postJson('/payroll/advances', $data + ['recovery_month' => '2026-01'])->assertUnprocessable();
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('payroll_payments', 0);
        $id = $this->advance();
        $this->actingAs(User::factory()->create(['role' => 'viewer', 'is_active' => true]));
        $this->postJson("/payroll/advances/$id/schedule", ['recovery_month' => '2026-04'])->assertForbidden();
    }

    public function test_legacy_backfill_keeps_journals_and_leaves_old_advances_unscheduled(): void
    {
        $id = $this->advance();
        $run = $this->draft('2026-03');
        $this->review($run, 1000);
        $this->post("/payroll/$run/approve")->assertRedirect();
        $journals = DB::table('journal_entry_lines')->orderBy('id')->get()->toJson();
        Schema::drop('payroll_advance_recoveries');
        Schema::table('payroll_payments', function ($t) {
            $t->dropIndex(['recovery_month']);
            $t->dropColumn('recovery_month');
        });
        $migration = require database_path('migrations/2026_11_06_000009_schedule_salary_advance_recoveries.php');
        $migration->up();
        $this->assertDatabaseHas('payroll_payments', ['id' => $id, 'recovery_month' => null]);
        $this->assertDatabaseHas('payroll_advance_recoveries', ['payroll_payment_id' => $id, 'amount_cents' => 100000]);
        $this->assertEquals($journals, DB::table('journal_entry_lines')->orderBy('id')->get()->toJson());
        $this->preview('2026-04', 0); // Legacy amounts need explicit scheduling or manual review.
        $this->assertEquals(100000, SalaryAdvanceService::eligible([$this->employee->id], 1, 'LKR', '2026-04')[$this->employee->id]->sum('remaining_cents'));
    }
}
