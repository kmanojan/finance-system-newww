<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LoanBalanceService;
use App\Services\LoanFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoanFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('companies')->insert(['id' => 1, 'name' => 'Flow test company', 'base_currency' => 'LKR']);
        DB::table('departments')->insert(['id' => 1, 'company_id' => 1, 'name' => 'Finance']);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    }

    private function loan(array $overrides = []): int
    {
        return DB::table('loans')->insertGetId(array_replace(['lender_name' => 'Flow lender', 'principal_amount' => 10000, 'currency' => 'LKR', 'interest_method' => 'no_interest', 'claimed_date' => '2026-10-01', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()], $overrides));
    }

    private function repayment(int $loan, float $amount = 10000): int
    {
        return DB::table('loan_principal_records')->insertGetId(['loan_id' => $loan, 'record_type' => 'repayment', 'amount' => $amount, 'record_date' => '2026-11-06']);
    }

    public function test_description_selection_keeps_both_loans_outstanding_and_posts_no_payment(): void
    {
        $first = $this->loan();
        $second = $this->loan(['claimed_date' => '2026-11-01', 'purpose' => '<p>Additional borrowing: <a href="/loans/'.$first.'">Earlier loan</a></p>']);
        $before = DB::table('loans')->orderBy('id')->get()->toJson();
        foreach ([$first, $second] as $id) {
            $this->assertEquals(10000, LoanBalanceService::calculate(DB::table('loans')->find($id))['total_outstanding']);
            $this->get("/loans/$id")->assertOk()->assertSee('related borrowing')->assertSee('No payment implied')
                ->assertDontSee('Link another borrowing')->assertDontSee('Link a past repayment')
                ->assertViewHas('loanFlow', fn ($flow) => count($flow['loans']) === 2 && $flow['edges']->first()->kind === 'description');
        }
        $this->assertSame($before, DB::table('loans')->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('loan_principal_records', 0);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->get('/loans/party-report')->assertOk()->assertViewHas('overallPayables', 20000);
    }

    public function test_paying_second_described_loan_does_not_settle_first_loan(): void
    {
        $first = $this->loan();
        $second = $this->loan(['purpose' => '<a href="/loans/'.$first.'">Previous borrowing</a>']);
        $this->postJson("/loans/$second/settle-fully", ['settlement_date' => '2026-11-06'])->assertRedirect();
        $this->assertDatabaseHas('loans', ['id' => $first, 'status' => 'active']);
        $this->assertDatabaseHas('loans', ['id' => $second, 'status' => 'settled']);
        $this->assertEquals(10000, LoanBalanceService::calculate(DB::table('loans')->find($first))['total_outstanding']);
        $this->assertEquals(0, LoanBalanceService::calculate(DB::table('loans')->find($second))['total_outstanding']);
        $this->assertDatabaseCount('loan_refinancing_links', 0);
        $this->assertCount(1, LoanFlowService::graph($first)['edges']);
        $this->get('/loans/party-report')->assertOk()->assertViewHas('overallPayables', 10000);
    }

    public function test_description_links_update_and_remove_through_existing_edit_form(): void
    {
        $first = $this->loan();
        $second = $this->loan();
        $this->putJson("/loans/$second", ['purpose' => '<p><a href="/loans/'.$first.'"><strong>Earlier loan</strong></a></p>'])->assertRedirect();
        $this->assertCount(2, LoanFlowService::graph($first)['loans']);
        $record = $this->repayment($second, 1000);
        $this->putJson("/loans/$second", ['purpose' => '<p>Loan reference removed</p>'])->assertRedirect();
        $this->assertCount(1, LoanFlowService::graph($first)['loans']);
        $this->assertDatabaseHas('loan_principal_records', ['id' => $record, 'amount' => 1000]);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_description_parser_ignores_external_self_missing_and_plain_text_references(): void
    {
        $first = $this->loan();
        $second = $this->loan();
        $html = '<p>Plain /loans/'.$first.'</p><a href="https://external.invalid/loans/'.$first.'">External</a><a href="javascript:/loans/'.$first.'">Unsafe</a><a href="/loans/99999">Missing</a><a href="/loans/'.$second.'">Self</a>';
        DB::table('loans')->where('id', $second)->update(['purpose' => $html]);
        $this->assertCount(1, LoanFlowService::graph($second)['loans']);
        $html .= '<a href="/loans/'.$first.'?tab=details#loan-flow">Selected loan</a><a href="'.url('/loans/'.$first).'">Duplicate</a>';
        DB::table('loans')->where('id', $second)->update(['purpose' => $html]);
        $this->assertCount(2, LoanFlowService::graph($first)['loans']);
        $this->assertCount(1, LoanFlowService::graph($first)['edges']);
        $this->assertEquals([$first], LoanFlowService::mentionedLoanIds('<a class="mention-chip mention-loan" href="/loans/'.$first.'">Loan</a>'));
    }

    public function test_reciprocal_mentions_deduplicate_and_chains_are_visible_from_both_ends(): void
    {
        $a = $this->loan();
        $b = $this->loan();
        $c = $this->loan();
        $d = $this->loan();
        DB::table('loans')->where('id', $a)->update(['purpose' => '<a href="/loans/'.$b.'">B</a>']);
        DB::table('loans')->where('id', $b)->update(['purpose' => '<a href="/loans/'.$a.'">A</a><a href="/loans/'.$c.'">C</a><a href="/loans/'.$d.'">D</a>']);
        foreach ([$a, $b, $c, $d] as $id) {
            $graph = LoanFlowService::graph($id);
            $this->assertCount(4, $graph['loans']);
            $this->assertCount(3, $graph['edges']);
            $this->assertEquals(2, $graph['loans'][$c]->flow_level);
        }
    }

    public function test_description_and_actual_funding_have_distinct_edges(): void
    {
        $first = $this->loan();
        $second = $this->loan(['purpose' => '<a href="/loans/'.$first.'">Earlier loan</a>']);
        LoanFlowService::link($first, $this->repayment($first, 1000), $second, 1000);
        $this->assertEquals(['description', 'repayment'], LoanFlowService::graph($first)['edges']->pluck('kind')->sort()->values()->all());
        $this->get("/loans/$first")->assertOk()->assertSee('data-flow-kind="description"', false)->assertSee('data-flow-kind="repayment"', false);
    }

    public function test_description_direction_does_not_block_a_real_repayment(): void
    {
        $first = $this->loan();
        $second = $this->loan(['purpose' => '<a href="/loans/'.$first.'">Related loan</a>']);
        $this->postJson("/loans/$second/repayment", ['amount' => 1000, 'record_date' => '2026-11-06', 'source_loan_id' => $first])->assertRedirect();
        $this->assertEquals(9000, LoanBalanceService::calculate(DB::table('loans')->find($second))['total_outstanding']);
        $this->assertEquals(10000, LoanBalanceService::calculate(DB::table('loans')->find($first))['total_outstanding']);
        $this->get("/loans/$second")->assertOk();
    }

    public function test_new_loan_description_is_visible_without_a_separate_link_action(): void
    {
        $first = $this->loan();
        $this->postJson('/loans', ['lender_name' => 'Next lender', 'principal_amount' => 10000, 'currency' => 'LKR', 'claimed_date' => '2026-11-01', 'interest_method' => 'no_interest', 'purpose' => '<a href="/loans/'.$first.'">Earlier loan</a>'])->assertRedirect();
        $second = DB::table('loans')->orderByDesc('id')->first();
        $this->assertCount(2, LoanFlowService::graph($first)['loans']);
        $this->assertEquals('pending', $second->status);
        $this->assertDatabaseCount('transactions', 0);
        $this->actingAs(User::factory()->create(['role' => 'staff', 'is_active' => true]));
        $this->get("/loans/$first")->assertOk()->assertSee('related borrowing');
        $this->putJson("/loans/$first", ['purpose' => 'Changed'])->assertForbidden();
    }

    public function test_new_loan_funds_full_settlement_without_reducing_the_new_loan_balance(): void
    {
        $old = $this->loan();
        $new = $this->loan(['claimed_date' => '2026-11-01']);
        $this->postJson("/loans/$old/settle-fully", ['settlement_date' => '2026-11-06', 'source_loan_id' => $new])->assertRedirect();
        $this->assertDatabaseHas('loans', ['id' => $old, 'status' => 'settled']);
        $this->assertEquals(0, LoanBalanceService::calculate(DB::table('loans')->find($old))['outstanding_principal']);
        $this->assertEquals(10000, LoanBalanceService::calculate(DB::table('loans')->find($new))['outstanding_principal']);
        $this->assertDatabaseHas('loan_refinancing_links', ['source_loan_id' => $new, 'amount' => 10000]);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertEquals(10000, DB::table('journal_entry_lines')->sum('debit'));
        $this->assertEquals(10000, DB::table('journal_entry_lines')->sum('credit'));
        foreach ([$old, $new] as $id) {
            $this->get("/loans/$id")->assertOk()->assertSee('Loan flow')->assertSee('principal repaid using')->assertViewHas('loanFlow', fn ($flow) => $flow['loans']->count() === 2 && $flow['edges']->count() === 1);
        }
        $this->get('/loans/party-report')->assertOk()->assertViewHas('overallPayables', 10000);
        $this->postJson("/loans/$old/settle-fully", ['source_loan_id' => $new])->assertUnprocessable();
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_partial_repayment_tracks_the_allocated_amount_and_remaining_debt(): void
    {
        $old = $this->loan();
        $new = $this->loan();
        $this->postJson("/loans/$old/repayment", ['amount' => 4000, 'record_date' => '2026-11-06', 'source_loan_id' => $new])->assertRedirect();
        $this->assertEquals(6000, LoanBalanceService::calculate(DB::table('loans')->find($old))['outstanding_principal']);
        $this->assertDatabaseHas('loan_refinancing_links', ['source_loan_id' => $new, 'amount' => 4000]);
    }

    public function test_linking_and_unlinking_existing_repayment_never_posts_money_and_supports_legacy_audit_schema(): void
    {
        Schema::table('activity_logs', fn ($table) => $table->dropColumn('ip_address'));
        $old = $this->loan();
        $new = $this->loan();
        $record = $this->repayment($old);
        $payload = ['principal_record_id' => $record, 'source_loan_id' => $new, 'amount' => 10000, 'notes' => '<script>alert(1)</script>'];
        $this->postJson("/loans/$old/funding-links", $payload)->assertRedirect();
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('loan_principal_records', 1);
        $this->get("/loans/$old")->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $link = DB::table('loan_refinancing_links')->value('id');
        $this->deleteJson("/loans/$new/funding-links/$link")->assertNotFound();
        $this->deleteJson("/loans/$old/funding-links/$link")->assertRedirect();
        $this->assertDatabaseCount('loan_refinancing_links', 0);
        $this->assertDatabaseCount('loan_principal_records', 1);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('activity_logs', 2);
    }

    public function test_invalid_funding_rolls_back_repayment_and_ledger(): void
    {
        $old = $this->loan();
        $invalid = [$old, $this->loan(['currency' => 'USD']), $this->loan(['status' => 'pending']), $this->loan(['claimed_date' => '2026-12-01']), $this->loan(['principal_amount' => 5000])];
        foreach ($invalid as $source) {
            $this->postJson("/loans/$old/settle-fully", ['source_loan_id' => $source, 'settlement_date' => '2026-11-06'])->assertUnprocessable();
            $this->assertDatabaseHas('loans', ['id' => $old, 'status' => 'active']);
            $this->assertDatabaseCount('loan_principal_records', 0);
            $this->assertDatabaseCount('transactions', 0);
            $this->assertDatabaseCount('journal_entries', 0);
            $this->assertDatabaseCount('loan_refinancing_links', 0);
        }
    }

    public function test_duplicate_excess_and_foreign_repayment_links_are_rejected(): void
    {
        $old = $this->loan();
        $new = $this->loan();
        $third = $this->loan();
        $record = $this->repayment($old, 6000);
        $payload = ['principal_record_id' => $record, 'source_loan_id' => $new, 'amount' => 4000];
        $this->postJson("/loans/$third/funding-links", $payload)->assertUnprocessable();
        $this->postJson("/loans/$old/funding-links", $payload)->assertRedirect();
        $this->postJson("/loans/$old/funding-links", array_replace($payload, ['amount' => 1000]))->assertUnprocessable();
        $this->postJson("/loans/$old/funding-links", array_replace($payload, ['source_loan_id' => $third, 'amount' => 2001]))->assertUnprocessable();
        $this->assertDatabaseCount('loan_refinancing_links', 1);
        $another = $this->repayment($third, 7000);
        $this->postJson("/loans/$third/funding-links", ['principal_record_id' => $another, 'source_loan_id' => $new, 'amount' => 7000])->assertUnprocessable();
    }

    public function test_chain_and_branch_are_visible_from_every_loan_and_cycles_rejected(): void
    {
        $a = $this->loan();
        $b = $this->loan();
        $c = $this->loan();
        $d = $this->loan();
        LoanFlowService::link($a, $this->repayment($a), $b, 5000);
        LoanFlowService::link($b, $this->repayment($b), $c, 5000);
        LoanFlowService::link($d, $this->repayment($d), $c, 5000);
        $record = $this->repayment($c);
        $this->postJson("/loans/$c/funding-links", ['principal_record_id' => $record, 'source_loan_id' => $a, 'amount' => 5000])->assertUnprocessable();
        foreach ([$a, $b, $c, $d] as $id) {
            $graph = LoanFlowService::graph($id);
            $this->assertCount(4, $graph['loans']);
            $this->assertCount(3, $graph['edges']);
            $this->assertEquals(2, $graph['loans'][$c]->flow_level);
        }
    }

    public function test_legacy_journal_header_migration_preserves_existing_entries(): void
    {
        Schema::table('journal_entries', fn ($table) => $table->dropUnique('journal_entries_entry_no_unique'));
        Schema::table('journal_entries', fn ($table) => $table->dropColumn(['entry_no', 'status']));
        $id = DB::table('journal_entries')->insertGetId(['entry_date' => '2026-10-01', 'description' => 'Existing journal']);
        $migration = require database_path('migrations/2026_11_06_000004_align_legacy_journal_headers.php');
        $migration->up();
        $this->assertDatabaseHas('journal_entries', ['id' => $id, 'description' => 'Existing journal', 'status' => 'posted']);
        $old = $this->loan();
        $new = $this->loan();
        $this->postJson("/loans/$old/settle-fully", ['settlement_date' => '2026-11-06', 'source_loan_id' => $new])->assertRedirect();
        $this->assertDatabaseCount('journal_entries', 2);
    }

    public function test_readonly_accounts_can_view_but_cannot_change_links(): void
    {
        $old = $this->loan();
        $new = $this->loan();
        $record = $this->repayment($old);
        $link = LoanFlowService::link($old, $record, $new, 10000);
        $this->actingAs(User::factory()->create(['role' => 'staff', 'is_active' => true]));
        $this->get("/loans/$old")->assertOk()->assertSee('Loan flow')->assertDontSee('Save funding link')->assertDontSee('Remove link');
        $this->postJson("/loans/$old/funding-links", ['principal_record_id' => $record, 'source_loan_id' => $new, 'amount' => 1])->assertForbidden();
        $this->deleteJson("/loans/$old/funding-links/$link")->assertForbidden();
    }
}
