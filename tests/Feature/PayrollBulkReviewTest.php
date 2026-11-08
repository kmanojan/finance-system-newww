<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PayrollReviewService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayrollBulkReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'accountant', 'is_active' => true]));
        DB::table('companies')->insert(['id' => 1, 'name' => 'Bulk review test', 'base_currency' => 'LKR']);
        DB::table('departments')->insert(['id' => 1, 'company_id' => 1, 'name' => 'Finance']);
    }

    private function createRun(int $count = 3, string $month = '2026-01'): int
    {
        $first = (int) DB::table('employees')->max('id') + 1;
        $employees = [];
        for ($i = $first; $i < $first + $count; $i++) {
            $employees[] = ['id' => $i, 'first_name' => 'Review', 'last_name' => (string) $i, 'full_name' => 'Review employee '.$i,
                'employee_code' => 'REV-'.$i, 'basic_salary' => 30000, 'currency' => 'LKR', 'status' => 'active'];
        }
        foreach (array_chunk($employees, 200) as $chunk) {
            DB::table('employees')->insert($chunk);
        }

        return PayrollService::create(['month' => $month, 'company_id' => 1, 'department_id' => 1, 'currency' => 'LKR',
            'employee_ids' => array_column($employees, 'id'), 'salary_due_date' => $month.'-28', 'statutory_due_date' => $month.'-28', 'tax_due_date' => $month.'-15']);
    }

    private function payload(int $run, ?array $ids = null, bool $reviewed = true): array
    {
        $items = PayrollService::items($run)->when($ids !== null, fn ($items) => $items->whereIn('id', $ids));

        return ['selection_json' => $items->map(fn ($item) => ['item_id' => $item->id, 'version' => PayrollReviewService::version($item)])->values()->toJson(),
            'reviewed' => $reviewed, 'review_note' => 'Checked against the monthly salary sheet.'];
    }

    public function test_selected_employees_can_be_reviewed_and_reset_without_altering_figures_or_notes(): void
    {
        $run = $this->createRun();
        $items = PayrollService::items($run);
        $ids = [$items[0]->id, $items[2]->id];
        DB::table('payroll_items')->where('id', $ids[0])->update(['adjustments' => json_encode(['notes' => 'Keep the original individual calculation note.'])]);
        $before = DB::table('payroll_items')->get()->map(fn ($i) => [$i->snapshot, $i->adjustments, $i->calculation, $i->gross_cents, $i->net_cents])->all();
        $this->post('/payroll/'.$run.'/bulk-review', $this->payload($run, $ids))->assertRedirect('/payroll/'.$run.'#employee-payroll')->assertSessionHasNoErrors();
        $this->assertSame(2, DB::table('payroll_items')->where('reviewed', true)->count());
        $this->assertDatabaseHas('payroll_items', ['id' => $items[1]->id, 'reviewed' => false]);
        $this->assertSame($before, DB::table('payroll_items')->get()->map(fn ($i) => [$i->snapshot, $i->adjustments, $i->calculation, $i->gross_cents, $i->net_cents])->all());
        $log = DB::table('activity_logs')->where('action', 'Marked payroll employees reviewed')->first();
        $this->assertNotNull($log);
        $this->assertSame($ids, json_decode($log->new_value, true)['item_ids']);
        $this->assertSame(auth()->id(), $log->user_id);
        $this->post('/payroll/'.$run.'/bulk-review', $this->payload($run, [$ids[0]], false))->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('payroll_items')->where('reviewed', true)->count());
        $this->assertDatabaseHas('payroll_runs', ['id' => $run, 'status' => 'draft']);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('payroll_payments', 0);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_one_thousand_employees_are_reviewed_using_one_json_field_and_can_then_be_submitted(): void
    {
        $run = $this->createRun(1000);
        $payload = $this->payload($run);
        $this->assertCount(3, $payload);
        $this->assertLessThan(250000, strlen($payload['selection_json']));
        $this->post('/payroll/'.$run.'/submit')->assertSessionHasErrors();
        $this->post('/payroll/'.$run.'/bulk-review', $payload)->assertSessionHasNoErrors();
        $this->assertSame(1000, DB::table('payroll_items')->where('reviewed', true)->count());
        $this->post('/payroll/'.$run.'/submit')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payroll_runs', ['id' => $run, 'status' => 'review']);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->get('/payroll/'.$run)->assertOk()->assertDontSee('data-payroll-review', false);
    }

    public function test_stale_changes_wrong_run_and_tampered_selections_are_rejected_atomically(): void
    {
        $run = $this->createRun();
        $payload = $this->payload($run);
        $item = PayrollService::items($run)->last();
        // Same-second edit still invalidates the signature, without relying on updated_at precision.
        DB::table('payroll_items')->where('id', $item->id)->update(['net_cents' => $item->net_cents - 1]);
        $this->postJson('/payroll/'.$run.'/bulk-review', $payload)->assertUnprocessable()->assertJsonValidationErrors('selection_json');
        $this->assertSame(0, DB::table('payroll_items')->where('reviewed', true)->count());
        $other = $this->createRun(1, '2026-02');
        $this->postJson('/payroll/'.$run.'/bulk-review', $this->payload($other))->assertUnprocessable();
        $fresh = $this->payload($run);
        $entries = json_decode($fresh['selection_json'], true);
        foreach (['[]', '{}', 'not json', json_encode([$entries[0], $entries[0]]), json_encode([['item_id' => $item->id, 'version' => str_repeat('a', 64)]])] as $invalid) {
            $this->postJson('/payroll/'.$run.'/bulk-review', ['selection_json' => $invalid] + $fresh)->assertUnprocessable();
        }
        $this->assertSame(0, DB::table('payroll_items')->where('reviewed', true)->count());
    }

    public function test_review_is_draft_only_and_replays_cannot_create_duplicate_review_events(): void
    {
        $run = $this->createRun();
        $payload = $this->payload($run);
        $this->postJson('/payroll/'.$run.'/bulk-review', $payload)->assertRedirect();
        $this->postJson('/payroll/'.$run.'/bulk-review', $payload)->assertUnprocessable();
        $this->assertSame(1, DB::table('activity_logs')->where('action', 'Marked payroll employees reviewed')->count());
        $this->post('/payroll/'.$run.'/submit')->assertSessionHasNoErrors();
        $this->postJson('/payroll/'.$run.'/bulk-review', $this->payload($run, null, false))->assertUnprocessable();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->post('/payroll/'.$run.'/approve')->assertSessionHasNoErrors();
        $this->postJson('/payroll/'.$run.'/bulk-review', $this->payload($run, null, false))->assertUnprocessable();
        $this->assertSame(3, DB::table('payroll_items')->where('reviewed', true)->count());
        $this->get('/payroll/'.$run)->assertOk()->assertDontSee('data-payroll-review', false);
    }

    public function test_viewers_are_blocked_and_reviewing_does_not_bypass_historical_deduction_reconciliation(): void
    {
        $run = $this->createRun();
        $this->get('/payroll/'.$run)->assertOk()->assertSee('data-payroll-review', false)->assertSee('Shared review note');
        $payload = $this->payload($run);
        $this->actingAs(User::factory()->create(['role' => 'viewer', 'is_active' => true]));
        $this->postJson('/payroll/'.$run.'/bulk-review', $payload)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'manager', 'is_active' => true]));
        $item = PayrollService::items($run)->first();
        $snapshot = $item->snapshot + ['historical_source' => ['loan' => 10000, 'advance' => 0]];
        DB::table('payroll_items')->where('id', $item->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->post('/payroll/'.$run.'/bulk-review', $this->payload($run))->assertSessionHasNoErrors();
        $this->post('/payroll/'.$run.'/submit')->assertSessionHasNoErrors();
        $this->post('/payroll/'.$run.'/approve')->assertSessionHasErrors();
        $this->assertDatabaseHas('payroll_runs', ['id' => $run, 'status' => 'review']);
        $this->assertDatabaseCount('journal_entries', 0);
    }
}
