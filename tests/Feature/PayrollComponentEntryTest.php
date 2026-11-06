<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\PayrollCalculationService;
use App\Services\PayrollService;
use Database\Seeders\SalaryComponentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayrollComponentEntryTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private SalaryComponent $commission;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        DB::table('companies')->insert(['id' => 1, 'name' => 'Payroll components', 'base_currency' => 'LKR']);
        DB::table('departments')->insert(['id' => 1, 'company_id' => 1, 'name' => 'Finance']);
        $this->seed(SalaryComponentSeeder::class);
        $this->employee = Employee::create(['external_id' => 99110, 'employee_code' => 'MONTHLY-1', 'first_name' => 'Monthly', 'last_name' => 'Employee', 'full_name' => 'Monthly Employee', 'status' => 'active', 'basic_salary' => 100000, 'currency' => 'LKR']);
        foreach (['FIXED_ALLOW' => 20000, 'TRAVEL_ALLOW' => 10000, 'EPF_EE' => 8, 'EPF_ER' => 12, 'ETF_ER' => 3, 'APIT' => 2000] as $code => $value) {
            $this->employee->salaryComponents()->create(['salary_component_id' => SalaryComponent::where('code', $code)->value('id'), 'value' => $value, 'is_enabled' => true]);
        }
        $this->commission = $this->variable('Project commission', 'COMMISSION', 'earning');
    }

    private function variable(string $name, string $code, string $type): SalaryComponent
    {
        $component = SalaryComponent::create(['name' => $name, 'code' => $code, 'type' => $type, 'calculation_type' => 'fixed_amount', 'calculation_base' => 'on_basic', 'default_value' => 5000, 'payroll_input_mode' => 'monthly_amount', 'is_active' => true, 'is_epf_eligible' => true, 'is_taxable' => true]);
        $this->employee->salaryComponents()->create(['salary_component_id' => $component->id, 'value' => 5000, 'is_enabled' => true]);

        return $component;
    }

    private function payload(array $amounts = []): array
    {
        return ['month' => '2026-10', 'company_id' => 1, 'department_id' => 1, 'currency' => 'LKR', 'employee_ids' => [$this->employee->id], 'salary_due_date' => '2026-10-31', 'statutory_due_date' => '2026-11-30', 'tax_due_date' => '2026-11-15', 'salary_confirmation' => 1, 'component_amounts' => [$this->employee->id => $amounts]];
    }

    public function test_create_page_groups_components_and_only_variable_components_have_inputs(): void
    {
        $html = $this->get('/payroll/create')->assertOk()->assertSee('Income')->assertSee('Expenses')->assertSee('Monthly Employee')->assertSee('Project commission')->getContent();
        $this->assertStringContainsString('name="component_amounts['.$this->employee->id.']['.$this->commission->id.']"', $html);
        $fixed = SalaryComponent::where('code', 'FIXED_ALLOW')->value('id');
        $this->assertStringNotContainsString('name="component_amounts['.$this->employee->id.']['.$fixed.']"', $html);
        $this->get('/master/salary-components')->assertOk()->assertSee('Enter amount each payroll')->assertSee('Display only');
    }

    public function test_preview_and_saved_payroll_match_and_preserve_regular_salary_profile(): void
    {
        $data = ['employee_ids' => [$this->employee->id], 'component_amounts' => [$this->employee->id => [$this->commission->id => 15000]]];
        $preview = $this->postJson('/payroll/preview', $data)->assertOk()->json('employees.'.$this->employee->id);
        $this->assertEquals(14500000, $preview['gross']);
        $this->assertEquals(13500000, $preview['epfBase']);
        $this->assertEquals(1080000, $preview['ee']);
        $this->assertEquals(13220000, $preview['net']);
        $this->assertDatabaseCount('payroll_runs', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->post('/payroll', $this->payload([$this->commission->id => 15000]))->assertRedirect();
        $item = PayrollService::items(DB::table('payroll_runs')->value('id'))->first();
        $this->assertEquals($preview, $item->calculation);
        $this->assertEquals(15000, $item->adjustments['component_amounts'][$this->commission->id]);
        $this->assertEquals(5000, $this->employee->salaryComponents()->where('salary_component_id', $this->commission->id)->value('value'));
        $this->assertEquals(100000, $this->employee->fresh()->basic_salary);
        $this->commission->update(['payroll_input_mode' => 'display', 'name' => 'Changed master']);
        $this->assertEquals($preview, PayrollCalculationService::calculate($item->snapshot, $item->adjustments));
        $this->get('/payroll/'.$item->payroll_run_id.'/employees/'.$item->id)->assertOk()->assertSee('Project commission');
    }

    public function test_monthly_amounts_start_at_zero_and_support_deductions_and_employer_contributions(): void
    {
        $deduction = $this->variable('Monthly deduction', 'MONTHLY_DED', 'deduction');
        $employer = $this->variable('Monthly employer contribution', 'MONTHLY_ER', 'employer_contribution');
        $this->employee->refresh();
        $c = PayrollCalculationService::calculate(PayrollCalculationService::snapshot($this->employee));
        $this->assertEquals(13000000, $c['gross']);
        $this->assertEquals(0, $c['other']);
        $amounts = [$this->commission->id => 10000, $deduction->id => 700, $employer->id => 1000];
        $response = $this->postJson('/payroll/preview', ['employee_ids' => [$this->employee->id], 'component_amounts' => [$this->employee->id => $amounts]])->assertOk();
        $result = $response->json('employees.'.$this->employee->id);
        $this->assertEquals(14000000, $result['gross']);
        $this->assertEquals(70000, $result['other']);
        $this->assertEquals(100000, $result['employerOther']);
        $this->assertEquals(170000, $result['other_payable']);
        $this->assertEquals(12690000, $result['net']);
        $this->assertEquals(16050000, $result['cost']);
    }

    public function test_fixed_unassigned_inactive_negative_and_other_employee_overrides_are_rejected(): void
    {
        $fixed = SalaryComponent::where('code', 'FIXED_ALLOW')->value('id');
        foreach ([[$fixed => 123], [999999 => 1], [$this->commission->id => -1], [$this->commission->id => '1.001']] as $amounts) {
            $this->postJson('/payroll', $this->payload($amounts))->assertUnprocessable();
        }
        $this->postJson('/payroll', array_replace($this->payload(), ['component_amounts' => [999 => [$this->commission->id => 10]]]))->assertUnprocessable();
        $this->commission->update(['is_active' => false]);
        $this->postJson('/payroll', $this->payload([$this->commission->id => 10]))->assertUnprocessable();
        $this->assertDatabaseCount('payroll_runs', 0);
        $this->assertDatabaseCount('payroll_items', 0);
    }

    public function test_salary_component_setting_is_saved_and_core_controls_remain_protected(): void
    {
        $data = ['name' => 'Variable award', 'code' => 'AWARD', 'type' => 'earning', 'calculation_type' => 'percentage', 'calculation_base' => 'on_gross', 'default_value' => 2, 'payroll_input_mode' => 'monthly_amount'];
        $this->postJson('/master/salary-components', $data)->assertOk()->assertJsonPath('component.payroll_input_mode', 'monthly_amount');
        $component = SalaryComponent::where('code', 'AWARD')->first();
        $this->putJson('/master/salary-components/'.$component->id, array_replace($data, ['payroll_input_mode' => 'display']))->assertOk();
        $this->assertEquals('display', $component->fresh()->payroll_input_mode);
        $basic = SalaryComponent::where('code', 'BASIC')->first();
        $this->putJson('/master/salary-components/'.$basic->id, array_replace($data, ['name' => 'Basic', 'code' => 'BASIC']))->assertUnprocessable();
        $this->actingAs(User::factory()->create(['role' => 'viewer', 'is_active' => true]));
        $this->postJson('/payroll/preview', ['employee_ids' => [$this->employee->id]])->assertForbidden();
    }

    public function test_existing_snapshot_without_new_attribute_keeps_its_calculation(): void
    {
        $snapshot = PayrollCalculationService::snapshot($this->employee->fresh());
        foreach ($snapshot['components'] as &$component) {
            unset($component['id'],$component['payroll_input_mode']);
        }
        unset($component);
        $c = PayrollCalculationService::calculate($snapshot);
        $this->assertEquals(13500000,$c['gross']); // Historical commission remains the previously configured 5,000.
    }
}
