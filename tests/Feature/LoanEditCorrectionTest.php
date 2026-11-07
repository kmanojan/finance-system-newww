<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FinancialReportingService;
use App\Services\LoanBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoanEditCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('companies')->insert(['id' => 1, 'name' => 'Loan correction test', 'base_currency' => 'LKR']);
        DB::table('departments')->insert(['id' => 1, 'company_id' => 1, 'name' => 'Finance']);
        DB::table('parties')->insert([['id' => 1, 'name' => 'First lender', 'types' => 'lender'], ['id' => 2, 'name' => 'Correct lender', 'types' => 'lender']]);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    }

    private function activate(array $overrides = []): int
    {
        $this->post('/loans', array_replace(['party_id' => 1, 'lender_name' => 'First lender', 'principal_amount' => 100000, 'currency' => 'LKR', 'claimed_date' => '2026-11-01', 'term_months' => 4, 'interest_method' => 'fixed_amount', 'interest_amount' => 10000, 'frequency' => 'monthly'], $overrides))->assertSessionHasNoErrors();
        $id = DB::table('loans')->max('id');
        $this->post("/loans/$id/activate")->assertSessionHasNoErrors();

        return $id;
    }

    public function test_active_loan_due_date_can_change_or_clear_without_rebuilding_interest(): void
    {
        $id = $this->activate();
        $schedules = DB::table('loan_interest_schedule')->where('loan_id', $id)->get()->toJson();
        $transactions = DB::table('transactions')->get()->toJson();
        $this->putJson("/loans/$id", ['maturity_date' => '2027-03-15', 'reminder_days' => 7])->assertRedirect();
        $this->assertDatabaseHas('loans', ['id' => $id, 'maturity_date' => '2027-03-15', 'reminder_days' => 7]);
        $this->assertDatabaseHas('reminders', ['reference_id' => $id, 'type' => 'loan', 'due_date' => '2027-03-15', 'notify_before_days' => 7]);
        $this->putJson("/loans/$id", ['maturity_date' => '2026-10-01'])->assertUnprocessable();
        $this->putJson("/loans/$id", ['maturity_date' => 'invalid'])->assertUnprocessable();
        $this->putJson("/loans/$id", ['maturity_date' => null])->assertRedirect();
        $this->assertDatabaseHas('loans', ['id' => $id, 'maturity_date' => null]);
        $this->assertDatabaseMissing('reminders', ['reference_id' => $id, 'type' => 'loan']);
        $this->assertSame($schedules, DB::table('loan_interest_schedule')->where('loan_id', $id)->get()->toJson());
        $this->assertSame($transactions, DB::table('transactions')->get()->toJson());
    }

    public function test_active_loan_identity_and_principal_can_be_corrected_without_erasing_payments(): void
    {
        $id = $this->activate();
        $this->post("/loans/$id/repayment", ['amount' => 20000, 'record_date' => '2026-11-02', 'payment_mode' => 'Normal'])->assertSessionHasNoErrors();
        $schedules = DB::table('loan_interest_schedule')->where('loan_id', $id)->get()->toJson();
        $records = DB::table('loan_principal_records')->where('loan_id', $id)->get()->toJson();
        $this->putJson("/loans/$id", ['party_id' => 2, 'lender_name' => 'Correct entity', 'principal_amount' => 120000, 'correction_reason' => 'Original amount entered incorrectly'])->assertRedirect();
        $this->assertDatabaseHas('loans', ['id' => $id, 'party_id' => 2, 'lender_name' => 'Correct entity', 'principal_amount' => 120000]);
        $this->assertDatabaseHas('transactions', ['reference_no' => "LOAN-ACT-$id", 'amount' => 120000]);
        $this->assertSame($schedules, DB::table('loan_interest_schedule')->where('loan_id', $id)->get()->toJson());
        $this->assertSame($records, DB::table('loan_principal_records')->where('loan_id', $id)->get()->toJson());
        $this->assertEquals(100000, LoanBalanceService::calculate(DB::table('loans')->find($id))['outstanding_principal']);
        $cash = FinancialReportingService::ledgerBalances('2026-01-01', '2026-12-31')->firstWhere('code', '1010');
        $this->assertEquals(100000, $cash->balance);
        $this->assertDatabaseHas('activity_logs', ['model_type' => 'Loan', 'model_id' => $id, 'action' => 'principal_correction']);
        $entries = DB::table('journal_entries')->count();
        $this->putJson("/loans/$id", ['principal_amount' => 120000])->assertRedirect();
        $this->assertSame($entries, DB::table('journal_entries')->count());
        $this->putJson("/loans/$id", ['party_id' => null, 'lender_name' => 'Custom lender'])->assertRedirect();
        $this->assertDatabaseHas('loans', ['id' => $id, 'party_id' => null, 'lender_name' => 'Custom lender']);
        $this->putJson("/loans/$id", ['party_id' => 2, 'lender_name' => ''])->assertRedirect();
        $this->assertDatabaseHas('loans', ['id' => $id, 'lender_name' => 'Correct lender']);
        $this->putJson("/loans/$id", ['party_id' => null, 'lender_name' => ''])->assertUnprocessable();
    }

    public function test_upfront_interest_stays_unchanged_and_cash_correction_uses_net_proceeds(): void
    {
        $id = $this->activate(['is_upfront_interest' => 1, 'upfront_interest_amount' => 10000]);
        $this->putJson("/loans/$id", ['principal_amount' => 125000, 'correction_reason' => 'Correct gross principal'])->assertRedirect();
        $this->assertDatabaseHas('transactions', ['reference_no' => "LOAN-ACT-$id", 'amount' => 115000]);
        $this->assertDatabaseHas('loans', ['id' => $id, 'upfront_interest_amount' => 10000]);
        $this->assertEquals(1, DB::table('journal_entries')->where('reference_type', 'LoanWithheldInterest')->count());
        $this->putJson("/loans/$id", ['principal_amount' => 10000, 'correction_reason' => 'Invalid correction'])->assertUnprocessable();
    }

    public function test_correction_cannot_remove_proceeds_already_used_to_fund_another_loan(): void
    {
        $old = $this->activate();
        $source = $this->activate();
        $this->postJson("/loans/$old/repayment", ['amount' => 60000, 'record_date' => '2026-11-02', 'payment_mode' => 'Normal', 'source_loan_id' => $source])->assertRedirect();
        $this->putJson("/loans/$source", ['principal_amount' => 50000, 'correction_reason' => 'Would invalidate allocated proceeds'])->assertUnprocessable()->assertJsonValidationErrors('principal_amount');
        $this->assertDatabaseHas('loans', ['id' => $source, 'principal_amount' => 100000]);
        $this->assertDatabaseHas('loan_refinancing_links', ['source_loan_id' => $source, 'amount' => 60000]);
    }

    public function test_invalid_corrections_leave_the_original_loan_and_disbursement_unchanged(): void
    {
        $id = $this->activate();
        $this->putJson("/loans/$id", ['principal_amount' => 120000])->assertUnprocessable()->assertJsonValidationErrors('correction_reason');
        $this->post("/loans/$id/repayment", ['amount' => 50000, 'record_date' => '2026-11-02', 'payment_mode' => 'Normal'])->assertSessionHasNoErrors();
        $this->putJson("/loans/$id", ['party_id' => 2, 'principal_amount' => 40000, 'correction_reason' => 'Below amount repaid'])->assertUnprocessable();
        DB::table('transactions')->where('reference_no', "LOAN-ACT-$id")->update(['reconciled' => true]);
        $this->putJson("/loans/$id", ['principal_amount' => 120000, 'correction_reason' => 'Reconciled disbursement'])->assertUnprocessable();
        $this->assertDatabaseHas('loans', ['id' => $id, 'party_id' => 1, 'principal_amount' => 100000]);
        $this->assertDatabaseHas('transactions', ['reference_no' => "LOAN-ACT-$id", 'amount' => 100000]);
        $this->putJson("/loans/$id", ['currency' => 'USD'])->assertUnprocessable();
        $this->putJson("/loans/$id", ['party_id' => 99999])->assertUnprocessable();
    }

    public function test_closed_period_blocks_principal_correction_and_rolls_back_identity_changes(): void
    {
        $id = $this->activate();
        $year = DB::table('financial_years')->insertGetId(['company_id' => 1, 'title' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        DB::table('fiscal_periods')->insert(['financial_year_id' => $year, 'period_name' => 'November', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'status' => 'hard_closed']);
        $this->putJson("/loans/$id", ['party_id' => 2, 'principal_amount' => 120000, 'correction_reason' => 'Closed month'])->assertUnprocessable();
        $this->assertDatabaseHas('loans', ['id' => $id, 'party_id' => 1, 'principal_amount' => 100000]);
        $this->assertDatabaseHas('transactions', ['reference_no' => "LOAN-ACT-$id", 'amount' => 100000]);
    }

    public function test_imported_loans_do_not_get_fabricated_cash_movements_and_viewers_cannot_correct(): void
    {
        $id = DB::table('loans')->insertGetId(['party_id' => 1, 'lender_name' => 'Legacy lender', 'principal_amount' => 100000, 'currency' => 'LKR', 'claimed_date' => '2026-11-01', 'status' => 'active', 'interest_method' => 'no_interest']);
        $this->putJson("/loans/$id", ['principal_amount' => 150000, 'correction_reason' => 'Correct imported record'])->assertRedirect();
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        DB::table('loans')->where('id', $id)->update(['status' => 'settled']);
        $this->putJson("/loans/$id", ['principal_amount' => 160000, 'correction_reason' => 'Would reopen liability'])->assertUnprocessable();
        $this->actingAs(User::factory()->create(['role' => 'viewer', 'is_active' => true]));
        $this->putJson("/loans/$id", ['party_id' => 2, 'lender_name' => 'Denied'])->assertForbidden();
    }
}
