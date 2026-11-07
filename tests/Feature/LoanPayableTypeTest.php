<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LoanBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoanPayableTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('companies')->insert(['id' => 1, 'name' => 'Loan test company', 'base_currency' => 'LKR']);
        DB::table('parties')->insert(['id' => 1, 'name' => 'Test lender', 'types' => 'lender']);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    }

    private function loan(?string $type = null): int
    {
        $data = ['party_id' => 1, 'lender_name' => 'Test lender', 'principal_amount' => 100000, 'currency' => 'LKR', 'term_months' => 4,
            'interest_method' => 'fixed_amount', 'interest_amount' => 10000, 'frequency' => 'monthly', 'claimed_date' => '2026-08-01', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()];
        if ($type !== null) {
            $data['loan_type'] = $type;
        }
        $id = DB::table('loans')->insertGetId($data);
        for ($month = 1; $month <= 4; $month++) {
            DB::table('loan_interest_schedule')->insert(['loan_id' => $id, 'due_date' => sprintf('2026-%02d-01', 8 + $month), 'interest_amount' => 10000,
                'paid_amount' => $month <= 2 ? 10000 : 0, 'status' => $month <= 2 ? 'paid' : 'pending']);
        }

        return $id;
    }

    private function balance(int $id): array
    {
        return LoanBalanceService::calculate(DB::table('loans')->find($id));
    }

    public function test_two_loan_types_match_the_examples_and_reconcile_across_views(): void
    {
        $first = $this->loan();
        $second = $this->loan(LoanBalanceService::PRINCIPAL_AND_INTEREST);
        $this->assertDatabaseHas('loans', ['id' => $first, 'loan_type' => LoanBalanceService::INTEREST_SEPARATE]);
        $this->assertEquals(100000, $this->balance($first)['total_outstanding']);
        $this->assertEquals(20000, $this->balance($first)['pending_interest']);
        $this->assertEquals(0, $this->balance($first)['repayment_percent']);
        $this->assertEquals(120000, $this->balance($second)['total_outstanding']);
        $this->assertEquals(140000, $this->balance($second)['total_obligation']);
        $this->assertEquals(14.3, $this->balance($second)['repayment_percent']);

        $this->get("/loans/{$first}")->assertOk()->assertViewHas('balance', fn ($b) => $b['total_outstanding'] === 100000 && $b['pending_interest'] === 20000 && $b['full_settlement_amount'] === 120000);
        $this->get("/loans/{$second}")->assertOk()->assertViewHas('balance', fn ($b) => $b['total_outstanding'] === 120000);
        $this->get('/loans')->assertOk()->assertViewHas('totalWantToPaid', 220000)
            ->assertViewHas('totalToSettle', 240000);
        $this->get('/loans/party-report')->assertOk()->assertViewHas('overallPayables', 220000)
            ->assertViewHas('partyReports', fn ($rows) => $rows->first()->total_pending_interest == 40000 && $rows->first()->total_paids == 40000);
        $response = $this->getJson('/loans/party-report/loans?party_id=1')->assertOk();
        $facilities = collect($response->json('facilities'))->keyBy('id');
        $this->assertEquals(100000, $facilities[$first]['total_outstanding']);
        $this->assertFalse($facilities[$first]['interest_included']);
        $this->assertEquals(120000, $facilities[$second]['total_outstanding']);
        $this->assertTrue($facilities[$second]['interest_included']);
    }

    public function test_principal_draws_repayments_and_partial_interest_remain_separate(): void
    {
        foreach ([LoanBalanceService::INTEREST_SEPARATE => 80000, LoanBalanceService::PRINCIPAL_AND_INTEREST => 95000] as $type => $expected) {
            $id = $this->loan($type);
            DB::table('loan_principal_records')->insert([
                ['loan_id' => $id, 'record_type' => 'draw', 'amount' => 5000],
                ['loan_id' => $id, 'record_type' => 'repayment', 'amount' => 25000],
            ]);
            DB::table('loan_interest_schedule')->where('loan_id', $id)->where('due_date', '2026-11-01')->update(['paid_amount' => 5000, 'status' => 'partially_paid']);
            $balance = $this->balance($id);
            $this->assertEquals(80000, $balance['outstanding_principal']);
            $this->assertEquals(15000, $balance['pending_interest']);
            $this->assertEquals($expected, $balance['total_outstanding']);
            $this->assertEquals(95000, $balance['full_settlement_amount']);
        }
    }

    public function test_unpaid_principal_and_interest_are_visible_as_total_to_settle_on_both_pages(): void
    {
        $id = $this->loan();
        DB::table('loans')->where('id', $id)->update(['principal_amount' => 1600000]);
        DB::table('loan_interest_schedule')->where('loan_id', $id)->update([
            'interest_amount' => 28000, 'paid_amount' => 0, 'status' => 'pending',
        ]);

        $this->get("/loans/{$id}")->assertOk()
            ->assertSeeInOrder(['Total to settle', '1,712,000.00', 'Unpaid principal', '1,600,000.00', 'Unpaid interest', '112,000.00'])
            ->assertViewHas('balance', fn ($balance) => $balance['total_outstanding'] == 1600000
                && $balance['full_settlement_amount'] == 1712000 && $balance['total_paid'] == 0);
        $this->get('/loans')->assertOk()
            ->assertViewHas('totalWantToPaid', 1600000)
            ->assertViewHas('totalToSettle', 1712000)
            ->assertSeeInOrder(['Total to settle', '1,712,000.00', 'Unpaid principal:', '1,600,000.00', 'Unpaid interest:', '112,000.00'])
            ->assertSeeInOrder(['<!-- Full settlement', 'Total to settle', '1,712,000.00', 'Principal 1,600,000.00', 'Interest 112,000.00'], false);

        // Reading a settlement total must not record a payment or reclassify the loan.
        $this->assertDatabaseHas('loans', ['id' => $id, 'loan_type' => LoanBalanceService::INTEREST_SEPARATE]);
        $this->assertDatabaseCount('loan_principal_records', 0);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_type_can_be_reclassified_without_changing_paid_history_or_terms(): void
    {
        $id = $this->loan();
        $schedules = DB::table('loan_interest_schedule')->where('loan_id', $id)->get()->toJson();
        $this->put("/loans/{$id}", ['loan_type' => LoanBalanceService::PRINCIPAL_AND_INTEREST])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals(120000, $this->balance($id)['total_outstanding']);
        $this->assertEquals($schedules, DB::table('loan_interest_schedule')->where('loan_id', $id)->get()->toJson());
        $this->assertDatabaseHas('loans', ['id' => $id, 'principal_amount' => 100000, 'interest_amount' => 10000]);
        $this->assertDatabaseCount('transactions', 0);
        $this->putJson("/loans/{$id}", ['loan_type' => 'invalid'])->assertUnprocessable();
        $this->assertEquals(120000, $this->balance($id)['total_outstanding']);
    }

    public function test_create_defaults_to_type_one_and_type_two_includes_all_four_periods(): void
    {
        $data = ['lender_name' => 'New lender', 'principal_amount' => 100000, 'currency' => 'LKR', 'claimed_date' => '2026-08-01', 'term_months' => 4, 'interest_method' => 'fixed_amount', 'interest_amount' => 10000, 'frequency' => 'monthly'];
        $this->post('/loans', $data)->assertSessionHasNoErrors()->assertRedirect();
        $first = DB::table('loans')->orderByDesc('id')->first();
        $this->assertEquals(LoanBalanceService::INTEREST_SEPARATE, $first->loan_type);
        $this->assertEquals(100000, $this->balance($first->id)['total_outstanding']);
        $this->post('/loans', $data + ['loan_type' => LoanBalanceService::PRINCIPAL_AND_INTEREST])->assertSessionHasNoErrors()->assertRedirect();
        $second = DB::table('loans')->orderByDesc('id')->first();
        $this->assertEquals(140000, $this->balance($second->id)['total_outstanding']);
    }

    public function test_settled_loans_report_actual_payments_and_zero_remaining_balance(): void
    {
        $id = $this->loan(LoanBalanceService::PRINCIPAL_AND_INTEREST);
        DB::table('loan_principal_records')->insert(['loan_id' => $id, 'record_type' => 'repayment', 'amount' => 100000]);
        DB::table('loan_interest_schedule')->where('loan_id', $id)->update(['paid_amount' => 10000, 'status' => 'paid']);
        DB::table('loans')->where('id', $id)->update(['status' => 'settled']);
        $this->assertEquals(0, $this->balance($id)['total_outstanding']);
        $this->assertEquals(0, $this->balance($id)['full_settlement_amount']);
        $this->assertEquals(140000, $this->balance($id)['total_paid']);
        $this->get('/loans/party-report')->assertOk()->assertViewHas('overallPayables', 0);
    }

    public function test_migration_assigns_type_one_to_existing_loans_without_rewriting_history(): void
    {
        $id = $this->loan();
        $before = DB::table('loan_interest_schedule')->get()->toJson();
        Schema::table('loans', fn ($table) => $table->dropColumn('loan_type'));
        $migration = require database_path('migrations/2026_11_06_000002_add_loan_type_to_loans_table.php');
        $migration->up();
        $this->assertDatabaseHas('loans', ['id' => $id, 'loan_type' => LoanBalanceService::INTEREST_SEPARATE]);
        $this->assertEquals($before, DB::table('loan_interest_schedule')->get()->toJson());
    }
}
