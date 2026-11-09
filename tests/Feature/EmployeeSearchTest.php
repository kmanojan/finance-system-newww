<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeeSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        DB::table('employees')->insert([
            ['external_id' => 1, 'first_name' => 'Anita', 'last_name' => 'Kumar', 'full_name' => 'Anita Kumar', 'employee_code' => 'STAFF-ALPHA', 'job_position' => 'Finance Manager', 'role' => 'Approver', 'status' => 'active'],
            ['external_id' => 2, 'first_name' => 'Bala', 'last_name' => 'Kumar', 'full_name' => 'Bala Kumar', 'employee_code' => 'STAFF-BETA', 'job_position' => 'Engineer', 'role' => 'Reviewer', 'status' => 'inactive'],
        ]);
    }

    public function test_search_matches_name_code_job_title_and_role_ignoring_case_and_extra_spaces(): void
    {
        foreach (['ANITA', 'staff-alpha', 'finance manager', '  APPROVER  ', 'ANITA   KUMAR'] as $term) {
            $this->get('/employees?'.http_build_query(['search' => $term]))->assertOk()
                ->assertViewHas('employees', fn ($employees) => $employees->total() === 1 && $employees->first()->full_name === 'Anita Kumar');
        }
        $this->get('/employees?search=reviewer&status=active')->assertOk()
            ->assertViewHas('employees', fn ($employees) => $employees->total() === 0)
            ->assertSee('Try another search or reset the filters.');
        $this->get('/employees?search=reviewer&status=inactive')->assertOk()
            ->assertViewHas('employees', fn ($employees) => $employees->total() === 1 && $employees->first()->full_name === 'Bala Kumar');
    }

    public function test_pagination_preserves_search_and_status_and_reset_restores_directory(): void
    {
        for ($i = 3; $i <= 25; $i++) {
            DB::table('employees')->insert(['external_id' => $i, 'first_name' => 'Team', 'last_name' => (string) $i,
                'full_name' => 'Team member '.$i, 'employee_code' => 'TEAM-'.$i, 'role' => 'Reviewer', 'status' => 'active']);
        }
        $this->get('/employees?search=REVIEWER&status=active')->assertOk()
            ->assertViewHas('employees', fn ($employees) => $employees->total() === 23
                && str_contains($employees->nextPageUrl(), 'search=REVIEWER') && str_contains($employees->nextPageUrl(), 'status=active'));
        $this->get('/employees?search=REVIEWER&status=active&page=2')->assertOk()
            ->assertViewHas('employees', fn ($employees) => $employees->count() === 3 && $employees->every(fn ($employee) => $employee->status === 'active'));
        $this->get('/employees')->assertOk()->assertViewHas('employees', fn ($employees) => $employees->total() === 25)
            ->assertSee('Employees &amp; payroll', false)->assertDontSee('<span>People</span>', false);
    }

    public function test_salary_estimates_keep_active_employees_and_currencies_separate(): void
    {
        DB::table('employees')->where('external_id', 1)->update(['basic_salary' => 12500.25, 'currency' => 'LKR']);
        DB::table('employees')->where('external_id', 2)->update(['basic_salary' => 30000, 'currency' => 'LKR']);
        DB::table('employees')->insert(['external_id' => 3, 'first_name' => 'Casey', 'last_name' => '', 'full_name' => 'Casey',
            'employee_code' => 'STAFF-GAMMA', 'status' => 'active', 'basic_salary' => 800.75, 'currency' => 'USD']);

        $response = $this->get('/employees?search=ANITA')->assertOk();
        $response->assertViewHas('activeCount', 2)->assertSee('12,500.25')->assertSee('800.75')->assertDontSee('30,000.00');
        $totals = $response->viewData('salaryTotals');
        $this->assertSame(1250025, $totals['LKR']['net_salary']);
        $this->assertSame(80075, $totals['USD']['net_salary']);
    }
}
