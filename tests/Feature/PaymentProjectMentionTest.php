<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LoanFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentProjectMentionTest extends TestCase
{
    use RefreshDatabase;

    private function records(): array
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $company = DB::table('companies')->insertGetId(['name' => 'Mention company', 'base_currency' => 'LKR']);
        $project = DB::table('projects')->insertGetId(['company_id' => $company, 'name' => 'Orchid portal', 'currency' => 'LKR', 'budget_limit' => 250000, 'status' => 'active']);
        $payment = DB::table('payments')->insertGetId(['project_id' => $project, 'total_amount' => 125000, 'currency' => 'LKR', 'payment_date' => '2026-10-15']);
        DB::table('payment_modes')->insert(['payment_id' => $payment, 'mode' => 'bank_transfer', 'amount' => 125000, 'bank_name' => 'HNB', 'reference_no' => 'ORCHID-RECEIPT-42']);

        return [$project, $payment];
    }

    public function test_payment_and_project_search_fields_limits_and_links(): void
    {
        [$project, $payment] = $this->records();
        foreach (['PAY-'.$payment, 'Orchid', '125,000.00', 'HNB', 'ORCHID-RECEIPT-42', '2026-10-15', 'Orchid 125000'] as $q) {
            $this->getJson('/rich-editor/mentions?'.http_build_query(['type' => 'payment', 'q' => $q]))->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.url', '/payments/'.$payment);
        }
        foreach (['PRJ-'.$project, 'orchid', 'Mention company', '250,000'] as $q) {
            $this->getJson('/rich-editor/mentions?'.http_build_query(['type' => 'project', 'q' => $q]))->assertOk()->assertJsonPath('items.0.url', '/projects/'.$project);
        }
        $this->getJson('/api/rich-editor/mentions?type=all&q=Orchid')->assertOk()->assertJsonCount(1, 'projects')->assertJsonCount(1, 'payments');
        $this->getJson('/rich-editor/mentions?type=payment&q=%25')->assertOk()->assertJsonCount(0, 'items');
        for ($i = 0; $i < 15; $i++) {
            DB::table('payments')->insert(['project_id' => $project, 'total_amount' => 1, 'currency' => 'LKR', 'payment_date' => '2026-10-16']);
        }
        $this->getJson('/rich-editor/mentions?type=payment')->assertOk()->assertJsonCount(10, 'items');
        $this->get('/payments/'.$payment)->assertOk()->assertSee('125,000.00')->assertSee('ORCHID-RECEIPT-42')->assertSee('Orchid portal');
        $this->get('/payments/999999')->assertNotFound();
        auth()->logout();
        $this->getJson('/rich-editor/mentions?type=payment')->assertUnauthorized();
        $this->get('/payments/'.$payment)->assertRedirect('/login');
    }

    public function test_settlement_references_are_cards_without_changing_financial_records(): void
    {
        [$project, $payment] = $this->records();
        $loan = DB::table('loans')->insertGetId(['lender_name' => 'Note test', 'principal_amount' => 10000, 'currency' => 'LKR', 'interest_method' => 'no_interest', 'status' => 'active']);
        DB::table('loan_principal_records')->insert(['loan_id' => $loan, 'record_type' => 'repayment', 'amount' => 1000, 'record_date' => '2026-11-09', 'notes' => '<p><a href="/payments/'.$payment.'">Receipt</a><a href="/projects/'.$project.'">Project</a><a href="/projects/999">Missing</a></p>']);
        $before = DB::table('payments')->find($payment);
        $graph = LoanFlowService::graph($loan);
        $this->assertCount(2, $graph['references']);
        $this->assertCount(2, $graph['edges']);
        $this->get('/loans/'.$loan)->assertOk()->assertSee('Referenced payment')->assertSee('Referenced project')->assertSee('data-flow-node="payment-'.$payment.'"', false);
        $this->assertEquals($before, DB::table('payments')->find($payment));
        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame([], LoanFlowService::mentionedReferenceIds('<a href="https://evil.example/projects/'.$project.'">External</a>', 'project'));
    }
}
