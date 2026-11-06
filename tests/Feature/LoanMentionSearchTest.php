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
