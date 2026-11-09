<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayrollWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        DB::table('companies')->insert([
            ['id' => 1, 'name' => 'Payroll local', 'base_currency' => 'LKR'],
            ['id' => 2, 'name' => 'Payroll overseas', 'base_currency' => 'USD'],
        ]);
        DB::table('departments')->insert(['id' => 1, 'company_id' => 1, 'name' => 'Finance']);
        foreach (range(1, 24) as $id) {
            DB::table('payroll_runs')->insert([
                'id' => $id, 'company_id' => $id <= 22 ? 1 : 2, 'department_id' => 1,
                'month' => '2026-10-01', 'currency' => $id <= 22 ? 'LKR' : 'USD',
                'status' => $id <= 21 ? 'draft' : 'completed',
                'salary_due_date' => '2026-10-31', 'statutory_due_date' => '2026-11-30',
                'tax_due_date' => '2026-11-15', 'created_by' => auth()->id(),
            ]);
        }
    }

    public function test_status_counts_span_pages_and_retain_other_filters(): void
    {
        $response = $this->get('/payroll?company_id=1&month=2026-10&status=draft&page=2')->assertOk();
        $this->assertSame(21, $response->viewData('runs')->total());
        $this->assertCount(1, $response->viewData('runs'));
        $this->assertEquals(['draft' => 21, 'completed' => 1], $response->viewData('statusCounts')->all());
        $response->assertSee('company_id=1&amp;month=2026-10&amp;status=completed', false);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('payroll_payments', 0);
    }

    public function test_search_matches_company_and_padded_payroll_reference(): void
    {
        $response = $this->get('/payroll?q=overseas')->assertOk();
        $this->assertSame([24, 23], $response->viewData('runs')->pluck('id')->all());
        $this->assertSame(['completed' => 2], $response->viewData('statusCounts')->all());
        $response->assertSee('USD');
        $response = $this->get('/payroll?q=PR-00001')->assertOk();
        $this->assertSame([1], $response->viewData('runs')->pluck('id')->all());
        $this->get('/payroll?q=missing')->assertOk()->assertSee('No matching payrolls');
        $this->assertSame(0, $this->get('/payroll?q=0')->viewData('runs')->total());
    }

    public function test_register_rejects_invalid_filters(): void
    {
        $this->getJson('/payroll?status=paid')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->getJson('/payroll?month=2026-13')->assertUnprocessable()->assertJsonValidationErrors('month');
        $this->getJson('/payroll?company_id=999')->assertUnprocessable()->assertJsonValidationErrors('company_id');
    }
}
