<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\SalaryComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeSalaryComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\SalaryComponentSeeder::class);
    }

    public function test_salary_components_master_screen_accessible(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/master/salary-components');

        $response->assertStatus(200);
        $response->assertSee('Salary Components');
        $response->assertSee('Basic Salary');
        $response->assertSee('Employee EPF (8%)');
        $response->assertSee('Employer EPF (12%)');
        $response->assertSee('Employer ETF (3%)');
    }

    public function test_employee_index_displays_salary_breakdown_columns_and_kpis(): void
    {
        $user = User::factory()->create();
        $employee = Employee::create([
            'external_id'   => 99991,
            'employee_code' => 'TEST-001',
            'first_name'    => 'Saman',
            'last_name'     => 'Perera',
            'full_name'     => 'Saman Perera',
            'status'        => 'active',
            'basic_salary'  => 100000.00,
            'currency'      => 'LKR',
        ]);

        $response = $this->actingAs($user)->get('/employees');

        $response->assertStatus(200);
        $response->assertSee('Employees &amp; Salary Management', false);
        $response->assertSee('Net Take-Home');
        $response->assertSee('Total CTC');
        $response->assertSee('100,000.00');
    }

    public function test_employee_salary_configuration_and_accurate_calculation(): void
    {
        $user = User::factory()->create();
        $employee = Employee::create([
            'external_id'   => 99992,
            'employee_code' => 'TEST-002',
            'first_name'    => 'Kamal',
            'last_name'     => 'Silva',
            'full_name'     => 'Kamal Silva',
            'status'        => 'active',
            'basic_salary'  => 100000.00,
            'currency'      => 'LKR',
        ]);

        $fixedAllow = SalaryComponent::where('code', 'FIXED_ALLOW')->first();
        $travelAllow = SalaryComponent::where('code', 'TRAVEL_ALLOW')->first();
        $epfEe = SalaryComponent::where('code', 'EPF_EE')->first();
        $epfEr = SalaryComponent::where('code', 'EPF_ER')->first();
        $etfEr = SalaryComponent::where('code', 'ETF_ER')->first();
        $apit = SalaryComponent::where('code', 'APIT')->first();

        $postData = [
            'basic_salary'    => 100000.00,
            'currency'        => 'LKR',
            'payment_mode'    => 'bank_transfer',
            'bank_name'       => 'Commercial Bank',
            'bank_account_no' => '1234567890',
            'bank_branch'     => 'Colombo',
            'epf_number'      => 'EPF-1234',
            'components'      => [
                ['id' => $fixedAllow->id, 'value' => 20000.00, 'enabled' => '1'],
                ['id' => $travelAllow->id, 'value' => 15000.00, 'enabled' => '1'],
                ['id' => $epfEe->id, 'value' => 8.00, 'enabled' => '1'],
                ['id' => $apit->id, 'value' => 2500.00, 'enabled' => '1'],
                ['id' => $epfEr->id, 'value' => 12.00, 'enabled' => '1'],
                ['id' => $etfEr->id, 'value' => 3.00, 'enabled' => '1'],
            ],
        ];

        $response = $this->actingAs($user)->postJson('/employees/' . $employee->id . '/salary', $postData);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        // Verification of math:
        // Basic: 100,000
        // Allowances: 20,000 (EPF) + 15,000 (Non-EPF) = 35,000
        // Gross: 135,000
        // EPF Base: 100,000 + 20,000 = 120,000
        // Employee EPF 8%: 9,600
        // APIT: 2,500
        // Total Deductions: 12,100
        // Net Salary: 135,000 - 12,100 = 122,900
        // Employer EPF 12%: 14,400
        // Employer ETF 3%: 3,600
        // Total Employer: 18,000
        // CTC: 135,000 + 18,000 = 153,000
        $response->assertJsonPath('breakdown.basic_salary', 100000);
        $response->assertJsonPath('breakdown.gross_salary', 135000);
        $response->assertJsonPath('breakdown.epf_base_salary', 120000);
        $response->assertJsonPath('breakdown.employee_epf', 9600);
        $response->assertJsonPath('breakdown.apit_tax', 2500);
        $response->assertJsonPath('breakdown.total_deductions', 12100);
        $response->assertJsonPath('breakdown.net_salary', 122900);
        $response->assertJsonPath('breakdown.employer_epf', 14400);
        $response->assertJsonPath('breakdown.employer_etf', 3600);
        $response->assertJsonPath('breakdown.total_employer_cost', 18000);
        $response->assertJsonPath('breakdown.ctc', 153000);
    }

    public function test_role_specific_allowances_for_field_vs_desk_employees(): void
    {
        $user = User::factory()->create();

        // 1. Create Petrol Allowance & Telephone Allowance in Masters
        $petrolAllow = SalaryComponent::create([
            'name'             => 'Petrol Allowance',
            'code'             => 'PETROL_ALLOW',
            'type'             => 'earning',
            'calculation_type' => 'fixed_amount',
            'calculation_base' => 'on_basic',
            'default_value'    => 0,
            'is_epf_eligible'  => false,
            'is_taxable'       => true,
            'is_statutory'     => false,
            'sort_order'       => 5,
        ]);

        $telAllow = SalaryComponent::create([
            'name'             => 'Telephone Allowance',
            'code'             => 'TEL_ALLOW',
            'type'             => 'earning',
            'calculation_type' => 'fixed_amount',
            'calculation_base' => 'on_basic',
            'default_value'    => 0,
            'is_epf_eligible'  => false,
            'is_taxable'       => true,
            'is_statutory'     => false,
            'sort_order'       => 6,
        ]);

        $epfEe = SalaryComponent::where('code', 'EPF_EE')->first();
        $epfEr = SalaryComponent::where('code', 'EPF_ER')->first();
        $etfEr = SalaryComponent::where('code', 'ETF_ER')->first();

        // 2. Field Employee (gets Petrol Allowance 25,000, Telephone disabled)
        $fieldEmployee = Employee::create([
            'external_id'   => 99993,
            'employee_code' => 'FIELD-01',
            'first_name'    => 'Rohan',
            'last_name'     => 'Fernando',
            'full_name'     => 'Rohan Fernando',
            'status'        => 'active',
            'basic_salary'  => 80000.00,
            'currency'      => 'LKR',
        ]);

        $this->actingAs($user)->postJson('/employees/' . $fieldEmployee->id . '/salary', [
            'basic_salary'    => 80000.00,
            'currency'        => 'LKR',
            'payment_mode'    => 'bank_transfer',
            'components'      => [
                ['id' => $petrolAllow->id, 'value' => 25000.00, 'enabled' => '1'],
                ['id' => $telAllow->id, 'value' => 0.00, 'enabled' => '0'],
                ['id' => $epfEe->id, 'value' => 8.00, 'enabled' => '1'],
                ['id' => $epfEr->id, 'value' => 12.00, 'enabled' => '1'],
                ['id' => $etfEr->id, 'value' => 3.00, 'enabled' => '1'],
            ],
        ])->assertStatus(200);

        // 3. Desk Employee (gets Telephone Allowance 5,000, Petrol disabled)
        $deskEmployee = Employee::create([
            'external_id'   => 99994,
            'employee_code' => 'DESK-01',
            'first_name'    => 'Anoma',
            'last_name'     => 'De Silva',
            'full_name'     => 'Anoma De Silva',
            'status'        => 'active',
            'basic_salary'  => 70000.00,
            'currency'      => 'LKR',
        ]);

        $this->actingAs($user)->postJson('/employees/' . $deskEmployee->id . '/salary', [
            'basic_salary'    => 70000.00,
            'currency'        => 'LKR',
            'payment_mode'    => 'bank_transfer',
            'components'      => [
                ['id' => $petrolAllow->id, 'value' => 0.00, 'enabled' => '0'],
                ['id' => $telAllow->id, 'value' => 5000.00, 'enabled' => '1'],
                ['id' => $epfEe->id, 'value' => 8.00, 'enabled' => '1'],
                ['id' => $epfEr->id, 'value' => 12.00, 'enabled' => '1'],
                ['id' => $etfEr->id, 'value' => 3.00, 'enabled' => '1'],
            ],
        ])->assertStatus(200);

        // Verify Field Employee Breakdown
        $fieldBreakdown = $fieldEmployee->fresh()->salary_breakdown;
        $this->assertEquals(25000, $fieldBreakdown['total_allowances']);
        $this->assertEquals(105000, $fieldBreakdown['gross_salary']); // 80,000 + 25,000
        $this->assertCount(1, $fieldBreakdown['earnings']);
        $this->assertEquals('Petrol Allowance', $fieldBreakdown['earnings'][0]['name']);

        // Verify Desk Employee Breakdown
        $deskBreakdown = $deskEmployee->fresh()->salary_breakdown;
        $this->assertEquals(5000, $deskBreakdown['total_allowances']);
        $this->assertEquals(75000, $deskBreakdown['gross_salary']); // 70,000 + 5,000
        $this->assertCount(1, $deskBreakdown['earnings']);
        $this->assertEquals('Telephone Allowance', $deskBreakdown['earnings'][0]['name']);
    }

    public function test_employee_show_view_renders_save_button_and_dropdowns_without_filter_buttons(): void
    {
        $user = User::factory()->create();
        $employee = Employee::create([
            'external_id'   => 99995,
            'employee_code' => 'TEST-005',
            'first_name'    => 'Nuwan',
            'last_name'     => 'Pradeep',
            'full_name'     => 'Nuwan Pradeep',
            'status'        => 'active',
            'basic_salary'  => 85000.00,
            'currency'      => 'LKR',
        ]);

        $response = $this->actingAs($user)->get('/employees/' . $employee->id);

        $response->assertStatus(200);

        // 1. Verify Save button text is explicitly present inside the HTML
        $response->assertSee('Save Salary Profile');

        // 2. Verify filter pills (All, Active, Inactive) are removed
        $response->assertDontSee("earningsFilter = 'all'");
        $response->assertDontSee("deductionsFilter = 'all'");
        $response->assertDontSee('earningsFilter');
        $response->assertDontSee('deductionsFilter');

        // 3. Verify + Assign Role Allowance dropdown is present
        $response->assertSee('+ Assign Role Allowance');
        $response->assertSee('allowanceDropdownOpen');
        $response->assertSee('+ Assign Deduction');
        $response->assertSee('deductionDropdownOpen');

        // 4. Verify modal is no longer present
        $response->assertDontSee('id="assignAllowanceModal"', false);
        $response->assertDontSee('openAllowanceModal');

        // 5. Verify basic salary and components use amount-input styling with comma formatting
        $response->assertSee('amount-input-wrapper');
        $response->assertSee('amount-display-input');
        $response->assertSee('name="basic_salary"', false);
        $response->assertSee('formatComponentAmount');
        $response->assertSee('formatComponentBlur');
    }
}

