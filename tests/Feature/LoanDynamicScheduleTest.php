<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoanDynamicScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('companies')->insert([
            'name' => 'Apptimus',
            'base_currency' => 'LKR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('categories')->insert([
            'name' => 'Loan Disbursement',
            'type' => 'income',
            'company_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_loan_schedule_is_generated_on_claim_date_plus_one_month(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin_loan@example.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Create loan with claimed date on the 18th
        $this->actingAs($admin)->post('/loans', [
            'lender_name' => 'Commercial Bank',
            'principal_amount' => 100000,
            'currency' => 'LKR',
            'claimed_date' => '2026-08-18',
            'term_months' => 3,
            'interest_method' => 'fixed_amount',
            'interest_amount' => 2000,
            'frequency' => 'monthly',
            'status' => 'pending',
        ]);

        $loan = DB::table('loans')->where('lender_name', 'Commercial Bank')->first();
        $this->assertNotNull($loan);
        $this->assertEquals(18, $loan->due_day);

        // Activate loan
        $this->actingAs($admin)->post("/loans/{$loan->id}/activate");

        $schedules = DB::table('loan_interest_schedule')
            ->where('loan_id', $loan->id)
            ->orderBy('due_date', 'asc')
            ->get();

        $this->assertCount(3, $schedules);
        // Assert dates are 2026-09-18, 2026-10-18, 2026-11-18 (Claim date + 1 month, etc.)
        $this->assertEquals('2026-09-18', $schedules[0]->due_date);
        $this->assertEquals('2026-10-18', $schedules[1]->due_date);
        $this->assertEquals('2026-11-18', $schedules[2]->due_date);
    }

    public function test_upfront_loan_schedule_has_first_period_on_claim_date_and_subsequent_periods_monthly(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin_upfront@example.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Create upfront loan
        $this->actingAs($admin)->post('/loans', [
            'lender_name' => 'Private Lender',
            'principal_amount' => 50000,
            'currency' => 'LKR',
            'claimed_date' => '2026-08-22',
            'term_months' => 3,
            'interest_method' => 'fixed_amount',
            'interest_amount' => 1500,
            'is_upfront_interest' => '1',
            'upfront_interest_amount' => 1500,
            'frequency' => 'monthly',
            'status' => 'pending',
        ]);

        $loan = DB::table('loans')->where('lender_name', 'Private Lender')->first();
        $this->assertNotNull($loan);

        // Activate loan
        $this->actingAs($admin)->post("/loans/{$loan->id}/activate");

        $schedules = DB::table('loan_interest_schedule')
            ->where('loan_id', $loan->id)
            ->orderBy('due_date', 'asc')
            ->get();

        $this->assertCount(3, $schedules);
        // Period 1 is upfront on claimed date (paid)
        $this->assertEquals('2026-08-22', $schedules[0]->due_date);
        $this->assertEquals('paid', $schedules[0]->status);

        // Period 2 and 3 are 1 month and 2 months after
        $this->assertEquals('2026-09-22', $schedules[1]->due_date);
        $this->assertEquals('pending', $schedules[1]->status);
        $this->assertEquals('2026-10-22', $schedules[2]->due_date);
        $this->assertEquals('pending', $schedules[2]->status);
    }

    public function test_user_can_view_app_install_tab_in_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'user_app_tab@example.com',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/profile?tab=app');
        $response->assertStatus(200);
        $response->assertSee('Install Finance App');
        $response->assertSee('Install App Now');
    }

    public function test_loan_can_be_created_and_updated_with_empty_maturity_date(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin_open_loan@example.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Create loan with empty maturity_date
        $this->actingAs($admin)->post('/loans', [
            'lender_name' => 'Open Term Bank',
            'principal_amount' => 75000,
            'currency' => 'LKR',
            'claimed_date' => '2026-09-01',
            'term_months' => 6,
            'maturity_date' => '',
            'interest_method' => 'no_interest',
            'status' => 'pending',
        ]);

        $loan = DB::table('loans')->where('lender_name', 'Open Term Bank')->first();
        $this->assertNotNull($loan);
        $this->assertNull($loan->maturity_date);

        // Update loan and ensure maturity_date remains null
        $this->actingAs($admin)->put("/loans/{$loan->id}", [
            'lender_name' => 'Open Term Bank Updated',
            'principal_amount' => 80000,
            'currency' => 'LKR',
            'claimed_date' => '2026-09-01',
            'term_months' => 12,
            'maturity_date' => '',
            'interest_method' => 'no_interest',
            'status' => 'pending',
        ]);

        $updatedLoan = DB::table('loans')->where('id', $loan->id)->first();
        $this->assertEquals('Open Term Bank Updated', $updatedLoan->lender_name);
        $this->assertNull($updatedLoan->maturity_date);
    }
}
