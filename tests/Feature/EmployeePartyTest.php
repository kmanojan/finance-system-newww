<?php

namespace Tests\Feature;

use App\Jobs\SyncEmployeesJob;
use App\Models\ApiIntegration;
use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeePartyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmployeePartyTest extends TestCase
{
    use RefreshDatabase;

    private function employee(array $overrides = []): Employee
    {
        return Employee::create($overrides + ['external_id' => 7001, 'employee_code' => 'E-7001', 'first_name' => 'Test', 'last_name' => 'Employee', 'full_name' => 'Test Employee', 'personal_email' => 'employee@example.test', 'mobile_phone' => '0770000000', 'basic_salary' => 100000, 'bank_account_no' => 'private-bank-data', 'status' => 'active']);
    }

    public function test_new_employee_gets_one_linked_party_and_no_salary_or_bank_data_is_copied(): void
    {
        $employee = $this->employee();
        $party = $employee->party;
        $this->assertNotNull($party);
        $this->assertSame('employee', $party->types);
        $this->assertSame('Test Employee', $party->name);
        $this->assertSame($employee->id, $party->employee->id);
        $this->assertArrayNotHasKey('basic_salary', $party->getAttributes());
        $this->assertStringNotContainsString('private-bank-data', $party->toJson());
        $employee->update(['basic_salary' => 150000]);
        EmployeePartyService::sync($employee);
        $this->assertDatabaseCount('parties', 1);
        $this->assertSame($party->id, $employee->fresh()->party_id);
    }

    public function test_profile_changes_preserve_other_roles_and_offboarding_preserves_payable_identity(): void
    {
        $employee = $this->employee();
        DB::table('parties')->where('id', $employee->party_id)->update(['types' => 'employee,lender,vendor', 'notes' => 'Keep finance notes']);
        $employee->update(['full_name' => 'Updated Employee', 'personal_email' => 'updated@example.test', 'status' => 'inactive']);
        $this->assertDatabaseHas('parties', ['id' => $employee->party_id, 'name' => 'Updated Employee', 'email' => 'updated@example.test', 'types' => 'employee,lender,vendor', 'status' => 'active', 'notes' => 'Keep finance notes']);
        $this->assertDatabaseCount('parties', 1);
    }

    public function test_backfill_is_repeatable_and_names_never_merge_distinct_people(): void
    {
        DB::table('parties')->insert(['name' => 'Test Employee', 'types' => 'lender']);
        $first = Employee::withoutEvents(fn () => $this->employee());
        $second = Employee::withoutEvents(fn () => $this->employee(['external_id' => 7002, 'status' => 'inactive']));
        $this->artisan('employees:sync-parties')->assertSuccessful();
        $this->artisan('employees:sync-parties')->assertSuccessful();
        $this->assertDatabaseCount('parties', 3);
        $this->assertNotSame($first->fresh()->party_id, $second->fresh()->party_id);
        $this->assertDatabaseHas('parties', ['id' => 1, 'types' => 'lender']);
        $this->assertDatabaseCount('payroll_runs', 0);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_party_edit_cannot_remove_employee_link_or_identity_and_delete_is_blocked(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $employee = $this->employee();
        $this->put('/master/parties/'.$employee->party_id, ['name' => 'Different person', 'email' => 'other@example.test', 'types' => ['lender'], 'status' => 'active'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('parties', ['id' => $employee->party_id, 'name' => 'Test Employee', 'email' => 'employee@example.test', 'types' => 'lender,employee']);
        $this->delete('/master/parties/'.$employee->party_id)->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseCount('parties', 1);
        $this->get('/master/parties?type=employee')->assertOk()->assertSee('E-7001')->assertSee('Employee profile');
    }

    public function test_manual_sync_button_only_appears_for_unlinked_employees_and_repeated_clicks_are_safe(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $employee = Employee::withoutEvents(fn () => $this->employee());
        $action = '/employees/'.$employee->id.'/sync-party';
        $this->get('/employees')->assertOk()->assertSee($action, false)->assertSee('Sync as party');
        $this->get('/employees/'.$employee->id)->assertOk()->assertSee($action, false);
        $salary = $employee->fresh()->basic_salary;
        $this->from('/employees')->post($action)->assertRedirect('/employees')->assertSessionHas('success');
        $partyId = $employee->fresh()->party_id;
        $this->assertNotNull($partyId);
        $this->post($action)->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseCount('parties', 1);
        $this->assertSame($partyId, $employee->fresh()->party_id);
        $this->assertSame($salary, $employee->fresh()->basic_salary);
        $this->get('/employees')->assertOk()->assertDontSee($action, false)->assertSee('View party');
        $this->get('/employees/'.$employee->id)->assertOk()->assertDontSee($action, false)->assertSee('View party');
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('payroll_runs', 0);
        $this->post('/employees/999999/sync-party')->assertNotFound();
    }

    public function test_manual_sync_requires_a_finance_role(): void
    {
        $employee = Employee::withoutEvents(fn () => $this->employee());
        foreach (['viewer', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]));
            $this->post('/employees/'.$employee->id.'/sync-party')->assertForbidden();
        }
        $this->assertDatabaseCount('parties', 0);
        $this->actingAs(User::factory()->create(['role' => 'accountant', 'is_active' => true]));
        $this->post('/employees/'.$employee->id.'/sync-party')->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseCount('parties', 1);
    }

    public function test_repeated_hr_sync_reuses_the_party_and_updates_contacts(): void
    {
        $integration = ApiIntegration::create(['name' => 'Test HR', 'url' => 'https://hr.example.test/employees', 'method' => 'GET', 'bearer_token' => 'test-only']);
        Http::fake(['hr.example.test/*' => Http::sequence()->push([['id' => 7001, 'full_name' => 'HR Employee', 'email' => 'hr@example.test']])->push([['id' => 7001, 'full_name' => 'Updated HR Employee', 'email' => 'new@example.test']])]);
        (new SyncEmployeesJob($integration))->handle();
        $partyId = Employee::first()->party_id;
        (new SyncEmployeesJob($integration))->handle();
        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseCount('parties', 1);
        $this->assertDatabaseHas('parties', ['id' => $partyId, 'name' => 'Updated HR Employee', 'email' => 'new@example.test']);
    }
}
