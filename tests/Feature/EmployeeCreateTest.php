<?php

namespace Tests\Feature;

use App\Jobs\SyncEmployeesJob;
use App\Models\ApiIntegration;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmployeeCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_employee_links_party_and_opens_salary_setup_without_a_fake_hr_id(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->get('/employees/create')->assertOk()->assertSee('Create employee &amp; configure salary', false);
        $this->post('/employees', ['first_name' => 'Manual', 'last_name' => 'Employee', 'status' => 'active',
            'job_position' => 'Designer', 'personal_email' => 'manual@example.test', 'external_id' => 1, 'basic_salary' => 999999])
            ->assertSessionHasNoErrors()->assertRedirect('/employees/1');
        $employee = Employee::first();
        $this->assertNull($employee->external_id);
        $this->assertSame('MAN-000001', $employee->employee_code);
        $this->assertSame('Manual Employee', $employee->full_name);
        $this->assertEquals(0, $employee->basic_salary);
        $this->assertDatabaseHas('parties', ['id' => $employee->party_id, 'name' => 'Manual Employee', 'types' => 'employee', 'email' => 'manual@example.test']);
        $this->assertDatabaseHas('activity_logs', ['model_type' => 'Employee', 'model_id' => $employee->id]);
        $this->get('/employees?search=MAN-000001')->assertOk()->assertViewHas('employees', fn ($rows) => $rows->total() === 1);
    }

    public function test_validation_and_read_only_access_cannot_create_employees_or_parties(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'accountant', 'is_active' => true]));
        $this->post('/employees', ['first_name' => 'One', 'employee_code' => 'LOCAL-ONE', 'status' => 'inactive'])->assertRedirect();
        $this->postJson('/employees', ['first_name' => '', 'status' => 'unknown', 'personal_email' => 'invalid'])->assertUnprocessable();
        $this->postJson('/employees', ['first_name' => 'Duplicate', 'employee_code' => 'local-one', 'status' => 'active'])->assertUnprocessable()->assertJsonValidationErrors('employee_code');
        $this->actingAs(User::factory()->create(['role' => 'viewer', 'is_active' => true]));
        $this->get('/employees/create')->assertForbidden();
        $this->postJson('/employees', ['first_name' => 'Denied', 'status' => 'active'])->assertForbidden();
        $this->assertDatabaseCount('employees', 1);
        $this->assertDatabaseCount('parties', 1);
        $this->assertDatabaseHas('employees', ['employee_code' => 'LOCAL-ONE', 'status' => 'inactive', 'last_name' => '']);
    }

    public function test_nexus_sync_cannot_overwrite_manual_employee_with_same_name_or_code(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager', 'is_active' => true]));
        $this->post('/employees', ['first_name' => 'Shared name', 'employee_code' => 'SAME-CODE', 'status' => 'inactive'])->assertRedirect();
        $manual = Employee::first();
        $manual->update(['basic_salary' => 150000]);
        $integration = ApiIntegration::create(['name' => 'Nexus fixture', 'url' => 'https://nexus-api.apptimus.io/api/v1/employees', 'method' => 'GET', 'bearer_token' => 'test-only', 'response_path' => 'data']);
        Http::fake(['nexus-api.apptimus.io/*' => Http::response(['data' => [['id' => 1, 'full_name' => 'Shared name', 'employee_code' => 'SAME-CODE', 'status' => 'active']]])]);
        (new SyncEmployeesJob($integration))->handle();
        (new SyncEmployeesJob($integration))->handle();
        $this->assertDatabaseCount('employees', 2);
        $this->assertDatabaseCount('parties', 2);
        $this->assertDatabaseHas('employees', ['id' => $manual->id, 'external_id' => null, 'basic_salary' => 150000, 'status' => 'inactive']);
        $this->assertDatabaseHas('employees', ['external_id' => 1, 'status' => 'active']);
    }
}
