<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\SalaryHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeSalaryHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;
    private array $payload = ['basic_salary' => 100000, 'currency' => 'LKR', 'payment_mode' => 'bank_transfer'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['name' => 'Salary reviewer', 'role' => 'admin', 'is_active' => true]));
        $this->employee = Employee::create(['external_id' => 'history-1', 'first_name' => 'History', 'last_name' => 'Employee', 'full_name' => 'History employee', 'status' => 'active', 'basic_salary' => 100000, 'currency' => 'LKR']);
    }

    private function save(array $overrides = [])
    {
        return $this->postJson('/employees/'.$this->employee->id.'/salary', array_replace($this->payload, $overrides));
    }

    public function test_snapshot_records_before_after_reason_actor_and_does_not_repeat_for_noop(): void
    {
        $this->save(['basic_salary' => 125000, 'salary_change_reason' => 'Promotion'])->assertOk();
        $row = SalaryHistoryService::query($this->employee->id)->first();
        $entry = SalaryHistoryService::present($row);
        $this->assertEquals(100000, $entry['before']['totals']['basic_salary']);
        $this->assertEquals(125000, $entry['after']['totals']['net_salary']);
        $this->assertSame('Promotion', $entry['after']['reason']);
        $this->assertSame('Salary reviewer', $entry['after']['changed_by_name']);
        $this->assertNotNull($row->user_id);
        $this->save(['basic_salary' => 125000, 'bank_account_no' => 'private-account'])->assertOk();
        $this->assertEquals(1, SalaryHistoryService::query($this->employee->id)->count());
        $this->assertStringNotContainsString('private-account', $row->new_value);
        $this->get('/employees/'.$this->employee->id)->assertOk()->assertSee('Promotion')->assertSee('Salary reviewer')->assertSee('salary-history-chart')->assertSee('Salary change history');
    }

    public function test_enabled_component_value_and_removal_are_snapshotted_without_mutating_earlier_history(): void
    {
        $component = SalaryComponent::create(['name' => 'Travel', 'code' => 'TRAVEL', 'type' => 'earning', 'calculation_type' => 'fixed_amount', 'calculation_base' => 'on_basic', 'default_value' => 0, 'is_active' => true]);
        $mapping = fn ($value, $enabled) => ['components' => [['id' => $component->id, 'value' => $value, 'enabled' => $enabled]]];
        $this->save($mapping(20000, 1))->assertOk();
        $first = SalaryHistoryService::query($this->employee->id)->first();
        $this->save($mapping(25000, 1))->assertOk();
        $this->save($mapping(25000, 0))->assertOk();
        $entries = SalaryHistoryService::query($this->employee->id)->orderBy('id')->get()->map(fn ($row) => SalaryHistoryService::present($row));
        $this->assertEquals([120000, 125000, 100000], $entries->pluck('after.totals.gross_salary')->all());
        $this->assertSame('Not assigned', $entries->last()['changes'][0]['after']);
        $this->assertEquals($first->new_value, DB::table('activity_logs')->find($first->id)->new_value);
        $this->save($mapping(30000, 0))->assertOk();
        $this->assertEquals(3, SalaryHistoryService::query($this->employee->id)->count());
    }

    public function test_component_master_changes_and_deletion_record_each_affected_salary(): void
    {
        $component = SalaryComponent::create(['name' => 'Allowance', 'code' => 'ALLOW', 'type' => 'earning', 'calculation_type' => 'percentage', 'calculation_base' => 'on_basic', 'default_value' => 10, 'is_active' => true]);
        $this->save(['components' => [['id' => $component->id, 'value' => 10, 'enabled' => 1]]])->assertOk();
        $master = ['name' => 'Renamed allowance', 'code' => 'ALLOW', 'type' => 'earning', 'calculation_type' => 'fixed_amount', 'calculation_base' => 'on_basic', 'default_value' => 10, 'is_active' => true];
        $this->putJson('/master/salary-components/'.$component->id, $master)->assertOk();
        $entry = SalaryHistoryService::present(SalaryHistoryService::query($this->employee->id)->orderByDesc('id')->first());
        $this->assertEquals(110000, $entry['before']['totals']['gross_salary']);
        $this->assertEquals(100010, $entry['after']['totals']['gross_salary']);
        $this->assertSame('Component master', $entry['after']['source']);
        $this->delete('/master/salary-components/'.$component->id)->assertRedirect();
        $last = SalaryHistoryService::present(SalaryHistoryService::query($this->employee->id)->orderByDesc('id')->first());
        $this->assertEquals(100000, $last['after']['totals']['gross_salary']);
        $this->assertSame('Renamed allowance', $last['before']['components'][0]['name']);
    }

    public function test_viewing_profile_does_not_assign_statutory_components_or_create_history(): void
    {
        SalaryComponent::create(['name' => 'EPF', 'code' => 'EPF_EE', 'type' => 'deduction', 'calculation_type' => 'percentage', 'calculation_base' => 'on_basic', 'default_value' => 8, 'is_active' => true, 'is_statutory' => true]);
        $this->get('/employees/'.$this->employee->id)->assertOk()->assertSee('No salary changes recorded yet');
        $this->assertDatabaseCount('employee_salary_components', 0);
        $this->assertEquals(0, SalaryHistoryService::query($this->employee->id)->count());
    }

    public function test_invalid_or_forbidden_changes_leave_salary_and_history_unchanged(): void
    {
        $this->save(['basic_salary' => -100])->assertUnprocessable();
        $this->save(['salary_change_reason' => str_repeat('x', 1001)])->assertUnprocessable();
        $this->save(['components' => [['id' => 9999, 'value' => 2, 'enabled' => 1]]])->assertUnprocessable();
        $this->actingAs(User::factory()->create(['role' => 'viewer', 'is_active' => true]));
        $this->save(['basic_salary' => 125000])->assertForbidden();
        $this->assertEquals(100000, $this->employee->fresh()->basic_salary);
        $this->assertEquals(0, SalaryHistoryService::query($this->employee->id)->count());
    }

    public function test_pagination_and_currency_changes_keep_their_original_units(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->save(['basic_salary' => 100000 + $i, 'currency' => $i === 12 ? 'USD' : 'LKR'])->assertOk();
        }
        $entry = SalaryHistoryService::present(SalaryHistoryService::query($this->employee->id)->orderByDesc('id')->first());
        $this->assertSame('LKR', $entry['before']['currency']);
        $this->assertSame('USD', $entry['after']['currency']);
        $this->get('/employees/'.$this->employee->id.'?salary_history_page=2')->assertOk()->assertViewHas('salaryHistory', fn ($history) => $history->total() === 12 && $history->count() === 2)->assertViewHas('salaryChart', fn ($chart) => $chart->count() === 12);
    }
}
