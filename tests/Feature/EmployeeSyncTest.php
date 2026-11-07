<?php

namespace Tests\Feature;

use App\Jobs\SyncEmployeesJob;
use App\Models\ApiIntegration;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EmployeeSyncTest extends TestCase
{
    use RefreshDatabase;

    private function integration(): ApiIntegration
    {
        return ApiIntegration::create(['name' => 'Nexus test', 'url' => 'https://nexus-api.apptimus.io/api/v1/employees?page=1&per_page=15&status=active&sort_by=id&sort_dir=desc',
            'method' => 'GET', 'bearer_token' => 'Bearer test-only-token', 'response_path' => 'data']);
    }

    public function test_all_pages_include_inactive_employees_preserve_salary_and_reuse_parties(): void
    {
        $integration = $this->integration();
        $employee = Employee::create(['external_id' => 1, 'first_name' => 'Old', 'last_name' => 'Name', 'full_name' => 'Old Name', 'basic_salary' => 125000, 'bank_account_no' => 'keep-private', 'status' => 'active']);
        $partyId = $employee->party_id;
        Http::fake(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);
            $this->assertArrayNotHasKey('status', $query);
            $this->assertSame('15', $query['per_page']);
            $this->assertSame('desc', $query['sort_dir']);
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer test-only-token'));
            $start = ((int) $query['page'] - 1) * 15 + 1;
            $rows = array_map(fn ($id) => ['id' => $id, 'full_name' => 'Synced employee '.$id, 'first_name' => 'Synced', 'last_name' => (string) $id,
                'employee_code' => 'NX-'.$id, 'status' => $id % 2 ? 'inactive' : 'active', 'job_position' => 'Engineer', 'role' => 'Staff',
                'basic_salary' => 1, 'bank_account_no' => 'do-not-copy'], range($start, $start + 14));

            return Http::response(['data' => $rows, 'meta' => ['current_page' => (int) $query['page'], 'last_page' => 3], 'links' => ['next' => 'https://untrusted.example/next']]);
        });
        $this->assertSame(45, (new SyncEmployeesJob($integration))->handle());
        $this->assertDatabaseCount('employees', 45);
        $this->assertDatabaseCount('parties', 45);
        $this->assertDatabaseHas('employees', ['external_id' => 1, 'status' => 'inactive', 'basic_salary' => 125000, 'bank_account_no' => 'keep-private', 'party_id' => $partyId]);
        $this->assertSame(23, Employee::where('status', 'inactive')->count());
        (new SyncEmployeesJob($integration))->handle();
        $this->assertDatabaseCount('employees', 45);
        $this->assertDatabaseCount('parties', 45);
        Http::assertSentCount(6);
        $this->assertSame('success', $integration->fresh()->last_sync_status);
    }

    public function test_failure_on_later_page_does_not_partially_import_or_leak_response_details(): void
    {
        $integration = $this->integration();
        Http::fake(['nexus-api.apptimus.io/*' => Http::sequence()
            ->push(['data' => [['id' => 1, 'full_name' => 'Must not import']], 'meta' => ['last_page' => 2]])
            ->push(['message' => 'private API body with test-only-token'], 401)]);
        try {
            (new SyncEmployeesJob($integration))->handle();
            $this->fail('A failed second page must fail the sync.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('HTTP 401', $error->getMessage());
        }
        $this->assertDatabaseCount('employees', 0);
        $this->assertDatabaseCount('parties', 0);
        $this->assertSame('failed', $integration->fresh()->last_sync_status);
        $this->assertStringNotContainsString('private API body', $integration->fresh()->last_sync_error);
    }

    public function test_setup_normalizes_token_url_and_never_returns_the_token_and_sync_reports_count(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $data = ['name' => 'Nexus', 'url' => 'https://nexus-api.apptimus.io/api/v1/employees?status=active', 'method' => 'GET', 'bearer_token' => 'Bearer test-only-token', 'response_path' => 'data'];
        $this->postJson('/api/api-integrations', $data)->assertCreated()->assertJsonMissingPath('bearer_token');
        $integration = ApiIntegration::first();
        $this->assertSame('test-only-token', $integration->bearer_token);
        $this->assertStringNotContainsString('status=', $integration->url);
        $this->assertStringNotContainsString('test-only-token', DB::table('api_integrations')->value('bearer_token'));
        $this->postJson('/api/api-integrations', array_replace($data, ['id' => $integration->id, 'name' => 'Renamed Nexus', 'bearer_token' => '']))->assertCreated();
        $this->assertDatabaseCount('api_integrations', 1);
        $this->assertSame('test-only-token', $integration->fresh()->bearer_token);
        Http::fake(['nexus-api.apptimus.io/*' => Http::sequence()->push(['data' => [['id' => 1, 'full_name' => 'One employee', 'status' => 'inactive']], 'meta' => ['last_page' => 1]])->push([], 401)]);
        $this->postJson('/api/api-integrations/'.$integration->id.'/sync')->assertOk()->assertJsonPath('employee_count', 1)->assertJsonPath('status', 'success');
        $this->postJson('/api/api-integrations/'.$integration->id.'/sync')->assertOk()->assertJsonPath('status', 'failed');
        $this->assertSame('failed', $integration->fresh()->last_sync_status);
    }

    public function test_invalid_response_path_and_repeated_pages_fail_without_changes(): void
    {
        $integration = $this->integration();
        $reply = [];
        Http::fake(function () use (&$reply) {
            return Http::response($reply);
        });
        foreach ([['wrong' => []], ['data' => [['id' => 1]], 'meta' => ['last_page' => 2]]] as $response) {
            $reply = $response;
            try {
                (new SyncEmployeesJob($integration))->handle();
                $this->fail('Invalid response must fail.');
            } catch (\RuntimeException $error) {
                $this->assertNotEmpty($error->getMessage());
            }
            $this->assertDatabaseCount('employees', 0);
        }
    }
}
