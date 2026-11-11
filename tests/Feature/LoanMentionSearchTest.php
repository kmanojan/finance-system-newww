<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoanMentionSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_visible_loan_fields_and_formatted_amounts_before_the_limit(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $party = DB::table('parties')->insertGetId(['name' => 'Abi Trading', 'types' => 'lender']);
        $id = DB::table('loans')->insertGetId(['loan_code' => 'LN-SEARCH-204', 'party_id' => $party, 'lender_name' => 'Original Lender', 'principal_amount' => 100000, 'currency' => 'LKR', 'status' => 'active', 'interest_method' => 'no_interest', 'purpose' => '<p>Office <strong>equipment</strong></p><p>R&amp;D work<b>ing</b> capital</p>']);
        for ($i = 0; $i < 205; $i++) {
            DB::table('loans')->insert(['loan_code' => 'OTHER-'.$i, 'lender_name' => 'Unrelated', 'principal_amount' => 54321, 'currency' => 'USD', 'interest_method' => 'no_interest', 'status' => 'pending']);
        }
        foreach (['LN-SEARCH', 'abi', 'Original Lender', 'office equipment', 'equipment R&D', 'working capital', '100000', '100,000', '100,000.00', '100 000', 'LKR100,000.00', 'Abi 100,000 equipment', 'lkr active abi'] as $query) {
            $response = $this->getJson('/rich-editor/mentions?'.http_build_query(['type' => 'loan', 'q' => $query, 'limit' => 10]));
            $response->assertOk()->assertJsonCount(1, 'loans')->assertJsonPath('loans.0.id', $id)->assertJsonPath('items.0.id', $id);
            $response->assertJsonPath('loans.0.purpose', 'Office equipment R&D working capital');
        }
        foreach (['Abi 200000', 'strong', 'no-such-person'] as $query) {
            $this->getJson('/rich-editor/mentions?'.http_build_query(['type' => 'loan', 'q' => $query]))->assertOk()->assertJsonCount(0, 'loans');
        }
        $this->getJson('/rich-editor/mentions?type=loan')->assertOk()->assertJsonCount(10, 'loans');
    }

    public function test_register_search_covers_displayed_fields_and_preserves_filters_and_totals(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $party = DB::table('parties')->insertGetId(['name' => 'Abi Trading', 'types' => 'lender']);
        $loan = [
            'party_id' => $party,
            'lender_name' => 'Original Lender',
            'principal_amount' => 100000,
            'currency' => 'LKR',
            'status' => 'active',
            'interest_method' => 'no_interest',
            'purpose' => '<p>Office <strong>equipment</strong> &amp; repairs</p>',
            'claimed_date' => '2026-10-09',
            'created_at' => '2026-10-09 00:00:00',
        ];
        $id = DB::table('loans')->insertGetId($loan + ['loan_code' => 'LN-EXAMPLE']);
        DB::table('loan_principal_records')->insert([
            'loan_id' => $id, 'record_type' => 'repayment', 'amount' => 12500, 'record_date' => '2026-10-11',
        ]);
        DB::table('loan_interest_schedule')->insert([
            'loan_id' => $id, 'due_date' => '2026-12-17', 'interest_amount' => 500, 'status' => 'pending',
        ]);
        DB::table('loans')->insert(array_replace($loan, ['status' => 'pending', 'loan_code' => 'LN-PENDING']));
        $legacy = DB::table('loans')->insertGetId(array_replace($loan, [
            'party_id' => null, 'principal_amount' => 2500, 'purpose' => null, 'created_at' => '2026-11-01 00:00:00',
        ]));
        for ($i = 0; $i < 16; $i++) {
            DB::table('loans')->insert(array_replace($loan, [
                'party_id' => null, 'principal_amount' => 2500, 'purpose' => null,
                'currency' => 'USD', 'created_at' => '2026-11-01 00:00:00',
            ]));
        }

        foreach (['ln-example', 'equipment & repairs', 'LKR100,000.00', 'Abi LKR 88,000', '09 Oct 2026 2026-12-17'] as $search) {
            $this->get('/loans?'.http_build_query(['status' => 'active', 'search' => $search]))
                ->assertOk()
                ->assertViewHas('loans', fn ($loans) => $loans->total() === 1 && $loans->first()->id === $id)
                ->assertViewHas('totalToSettle', 88000);
        }
        $this->get('/loans?search=strong')->assertOk()->assertViewHas('totalLoansCount', 0);
        $this->get('/loans?search=LN-'.str_pad($legacy, 4, '0', STR_PAD_LEFT))
            ->assertOk()->assertViewHas('loans', fn ($loans) => $loans->total() === 1 && $loans->first()->id === $legacy);
    }

    public function test_generated_codes_and_zero_amount_queries_are_searchable(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $id = DB::table('loans')->insertGetId(['lender_name' => 'Fallback', 'principal_amount' => 0, 'currency' => 'LKR', 'interest_method' => 'no_interest']);
        $code = 'LN-'.str_pad($id, 4, '0', STR_PAD_LEFT);
        $this->getJson('/api/rich-editor/mentions?type=loan&q='.$code)->assertOk()->assertJsonPath('loans.0.id', $id);
        $this->getJson('/rich-editor/mentions?type=loan&q=0')->assertOk()->assertJsonPath('loans.0.id', $id);
        $this->getJson('/rich-editor/mentions?q[]=bad')->assertUnprocessable();
    }
}
