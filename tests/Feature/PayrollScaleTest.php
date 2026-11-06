<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayrollScaleTest extends TestCase
{
    use RefreshDatabase;

    private array $ids;

    private int $component;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        DB::table('companies')->insert(['id' => 1, 'name' => 'Scale company', 'base_currency' => 'LKR']);
        DB::table('departments')->insert(['id' => 1, 'company_id' => 1, 'name' => 'Finance']);
        $this->component = SalaryComponent::create(['name' => 'Project commission', 'code' => 'COMMISSION', 'type' => 'earning', 'calculation_type' => 'fixed_amount', 'calculation_base' => 'on_basic', 'default_value' => 0, 'payroll_input_mode' => 'monthly_amount', 'is_active' => true])->id;
        $employees = [];
        for ($i = 1; $i <= 1000; $i++) {
            $employees[] = ['external_id' => 100000 + $i, 'employee_code' => 'S-'.$i, 'first_name' => 'Scale', 'last_name' => (string) $i, 'full_name' => 'Scale employee '.str_pad($i, 4, '0', STR_PAD_LEFT), 'status' => 'active', 'basic_salary' => 100000, 'currency' => 'LKR'];
        }
        foreach (array_chunk($employees, 100) as $chunk) {
            DB::table('employees')->insert($chunk);
        }
        $this->ids = Employee::orderBy('id')->pluck('id')->all();
        foreach (array_chunk($this->ids, 100) as $chunk) {
            DB::table('employee_salary_components')->insert(array_map(fn ($id) => ['employee_id' => $id, 'salary_component_id' => $this->component, 'value' => 0, 'is_enabled' => true], $chunk));
        }
    }

    public function test_large_directory_is_paginated_searchable_and_bulk_selection_is_bounded(): void
    {
        $html = $this->get('/payroll/create')->assertOk()->assertSee('of 1000 employees')->getContent();
        $this->assertSame(25, substr_count($html, 'data-employee-row'));
        $page = $this->getJson('/payroll/employees?page=2&per_page=25')->assertOk()->assertJsonPath('from', 26)->assertJsonPath('to', 50)->assertJsonPath('total', 1000)->json('html');
        $this->assertSame(25, substr_count($page, 'data-employee-row'));
        $this->assertStringContainsString('Scale employee 0026', $page);
        $this->assertStringNotContainsString('Scale employee 0001', $page);
        $this->getJson('/payroll/employees?search=Scale%20employee%200999')->assertOk()->assertJsonPath('total', 1);
        $ids = $this->getJson('/payroll/employees?select_all=1')->assertOk()->json('ids');
        $this->assertCount(1000, $ids);
        Employee::create(['external_id' => 999999, 'first_name' => 'Extra', 'last_name' => 'Employee', 'full_name' => 'Extra employee', 'status' => 'active', 'basic_salary' => 100000, 'currency' => 'LKR']);
        $this->getJson('/payroll/employees?select_all=1')->assertUnprocessable();
        $this->getJson('/payroll/employees?per_page=1000')->assertUnprocessable();
        $this->actingAs(User::factory()->create(['role' => 'viewer', 'is_active' => true]));
        $this->getJson('/payroll/employees')->assertForbidden();
    }

    public function test_json_submission_preserves_all_1000_employees_and_off_page_amounts(): void
    {
        $first = $this->ids[0];
        $last = $this->ids[999];
        // Over 2,000 logical inputs: JSON must bypass PHP form max_input_vars truncation.
        $amounts = array_fill_keys($this->ids, [$this->component => 0]);
        $amounts[$first] = [$this->component => 15000];
        $amounts[$last] = [$this->component => 25000];
        $response = $this->postJson('/payroll', ['month' => '2026-10', 'company_id' => 1, 'department_id' => 1, 'currency' => 'LKR', 'employee_ids' => $this->ids, 'component_amounts' => $amounts, 'salary_due_date' => '2026-10-31', 'statutory_due_date' => '2026-11-30', 'tax_due_date' => '2026-11-15', 'salary_confirmation' => 1]);
        $response->assertCreated();
        $id = DB::table('payroll_runs')->value('id');
        $response->assertJsonPath('redirect', '/payroll/'.$id);
        $items = PayrollService::items($id);
        $this->assertCount(1000, $items);
        $this->assertEquals(11500000, $items->firstWhere('employee_id', $first)->gross_cents);
        $this->assertEquals(12500000, $items->firstWhere('employee_id', $last)->gross_cents);
        $this->assertEquals(10004000000, $items->sum('gross_cents'));
        $this->assertDatabaseCount('journal_entries',0);
    }
}
