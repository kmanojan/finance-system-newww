<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoanInterestNoteTest extends TestCase
{
    use RefreshDatabase;

    private int $loanId;

    private int $scheduleId;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('companies')->insert(['id' => 1, 'name' => 'Interest note test', 'base_currency' => 'LKR']);
        DB::table('departments')->insert(['id' => 1, 'company_id' => 1, 'name' => 'Finance']);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $this->loanId = DB::table('loans')->insertGetId([
            'lender_name' => 'Test lender', 'principal_amount' => 100000, 'currency' => 'LKR',
            'interest_method' => 'fixed_amount', 'interest_amount' => 10000, 'status' => 'active',
        ]);
        $this->scheduleId = DB::table('loan_interest_schedule')->insertGetId([
            'loan_id' => $this->loanId, 'due_date' => '2026-11-01', 'interest_amount' => 10000,
            'paid_amount' => 0, 'status' => 'pending',
        ]);
    }

    private function url(): string
    {
        return "/loans/{$this->loanId}/schedule/{$this->scheduleId}/settle";
    }

    public function test_each_partial_payment_keeps_its_note_and_displays_it_safely(): void
    {
        $note = "Cheque 001\n<script>alert('test')</script>";
        $this->post($this->url(), ['paid_amount' => 3000, 'paid_date' => '2026-11-02', 'notes' => $note])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->post($this->url(), ['paid_amount' => 7000, 'paid_date' => '2026-11-03', 'notes' => 'Final interest balance'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('transactions', ['amount' => 3000, 'description' => "Interest Payment to Test lender\nNote: Cheque 001\n"]);
        $this->assertDatabaseHas('transactions', ['amount' => 7000, 'description' => "Interest Payment to Test lender\nNote: Final interest balance"]);
        $this->assertDatabaseHas('loan_interest_schedule', ['id' => $this->scheduleId, 'paid_amount' => 10000, 'status' => 'paid']);
        $this->get("/loans/{$this->loanId}")->assertOk()->assertSee('<th>Note</th>', false)
            ->assertSee('<th>Paid date</th>', false)->assertSee('<time datetime="2026-11-02">2026-11-02</time>', false)->assertSee('<time datetime="2026-11-03">2026-11-03</time>', false)
            ->assertSee('Cheque 001')->assertDontSee("alert('test')", false)->assertSee('Final interest balance');
        $this->assertDatabaseHas('loans', ['id' => $this->loanId, 'principal_amount' => 100000]);
    }

    public function test_note_can_be_omitted_null_empty_or_whitespace(): void
    {
        foreach ([[], ['notes' => null], ['notes' => ''], ['notes' => '   ']] as $input) {
            $this->post($this->url(), $input + ['paid_amount' => 1000, 'paid_date' => '2026-11-02'])
                ->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertSame(4, DB::table('transactions')->where('description', 'Interest Payment to Test lender')->count());
        $this->assertDatabaseHas('loan_interest_schedule', ['id' => $this->scheduleId, 'paid_amount' => 4000, 'status' => 'partially_paid']);
    }

    public function test_invalid_notes_do_not_record_payments(): void
    {
        foreach ([['invalid'], str_repeat('a', 20001)] as $note) {
            $this->postJson($this->url(), ['paid_amount' => 1000, 'notes' => $note])
                ->assertUnprocessable()->assertJsonValidationErrors('notes');
        }
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseHas('loan_interest_schedule', ['id' => $this->scheduleId, 'paid_amount' => 0, 'status' => 'pending']);
    }

    private function pay(int $amount = 10000): int
    {
        $this->post($this->url(), ['paid_amount' => $amount, 'paid_date' => '2026-11-02', 'notes' => 'Original note'])
            ->assertRedirect()->assertSessionHasNoErrors();

        return DB::table('transactions')->max('id');
    }

    private function paymentUrl(int $paymentId): string
    {
        return "/loans/{$this->loanId}/schedule/{$this->scheduleId}/payments/{$paymentId}";
    }

    public function test_completed_payment_can_be_edited_and_deleted_with_ledger_and_balance_reversal(): void
    {
        $paymentId = $this->pay();
        DB::table('loans')->where('id', $this->loanId)->update(['status' => 'settled']);
        $bankId = DB::table('bank_accounts')->insertGetId(['company_id' => 1, 'bank_name' => 'Test bank', 'account_no' => '123', 'currency' => 'LKR']);
        $this->get("/loans/{$this->loanId}")->assertOk()->assertSee('aria-label="Edit payment"', false)->assertSee('aria-label="Delete payment"', false);
        $this->putJson($this->paymentUrl($paymentId), ['paid_amount' => 8000, 'paid_date' => '2026-11-03', 'bank_account_id' => $bankId, 'notes' => 'Corrected note'])->assertRedirect();
        $this->assertDatabaseHas('transactions', ['id' => $paymentId, 'amount' => 8000, 'transaction_date' => '2026-11-03', 'bank_account_id' => $bankId, 'description' => "Interest Payment to Test lender\nNote: Corrected note"]);
        $this->assertDatabaseHas('loan_interest_schedule', ['id' => $this->scheduleId, 'paid_amount' => 8000, 'paid_date' => '2026-11-03', 'status' => 'partially_paid']);
        $this->assertDatabaseHas('loans', ['id' => $this->loanId, 'status' => 'active']);
        $this->assertEquals(-8000, $this->cashBalance());
        $this->deleteJson($this->paymentUrl($paymentId))->assertRedirect();
        $this->assertDatabaseMissing('transactions', ['id' => $paymentId]);
        $this->assertDatabaseHas('loan_interest_schedule', ['id' => $this->scheduleId, 'paid_amount' => 0, 'paid_date' => null, 'status' => 'pending']);
        $this->assertEquals(0, $this->cashBalance());
        $this->assertTrue(DB::table('activity_logs')->where('model_type', 'Transaction')->where('model_id', $paymentId)->where('old_value', 'like', '%Corrected note%')->exists());
        $this->deleteJson($this->paymentUrl($paymentId))->assertNotFound();
        $this->assertEquals(0, $this->cashBalance());
    }

    private function cashBalance(): float
    {
        return (float) DB::table('journal_entry_lines as lines')->join('accounts', 'accounts.id', '=', 'lines.account_id')
            ->where('accounts.code', '1010')->selectRaw('SUM(lines.debit - lines.credit) as balance')->value('balance');
    }

    public function test_partial_payment_correction_preserves_other_payments_and_rejects_overpayment(): void
    {
        $first = $this->pay(3000);
        $second = $this->pay(7000);
        $this->putJson($this->paymentUrl($first), ['paid_amount' => 4000, 'paid_date' => '2026-11-03'])->assertUnprocessable();
        $this->assertEquals(-10000, $this->cashBalance());
        $this->putJson($this->paymentUrl($first), ['paid_amount' => 2000, 'paid_date' => '2026-11-04', 'notes' => null])->assertRedirect();
        $this->assertDatabaseHas('transactions', ['id' => $first, 'amount' => 2000, 'description' => 'Interest Payment to Test lender']);
        $this->assertDatabaseHas('transactions', ['id' => $second, 'amount' => 7000]);
        $this->deleteJson($this->paymentUrl($first))->assertRedirect();
        $this->assertDatabaseHas('loan_interest_schedule', ['id' => $this->scheduleId, 'paid_amount' => 7000, 'paid_date' => '2026-11-02', 'status' => 'partially_paid']);
        $this->assertEquals(-7000, $this->cashBalance());
    }

    public function test_reconciled_closed_period_and_mismatched_history_cannot_be_changed(): void
    {
        $paymentId = $this->pay();
        $data = ['paid_amount' => 9000, 'paid_date' => '2026-11-03'];
        DB::table('transactions')->where('id', $paymentId)->update(['reconciled' => true]);
        $this->putJson($this->paymentUrl($paymentId), $data)->assertUnprocessable();
        $this->deleteJson($this->paymentUrl($paymentId))->assertUnprocessable();
        DB::table('transactions')->where('id', $paymentId)->update(['reconciled' => false]);
        DB::table('loan_interest_schedule')->where('id', $this->scheduleId)->update(['paid_amount' => 9500]);
        $this->deleteJson($this->paymentUrl($paymentId))->assertUnprocessable();
        DB::table('loan_interest_schedule')->where('id', $this->scheduleId)->update(['paid_amount' => 10000]);
        $year = DB::table('financial_years')->insertGetId(['company_id' => 1, 'title' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        DB::table('fiscal_periods')->insert(['financial_year_id' => $year, 'period_name' => 'November', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'status' => 'hard_closed']);
        $this->putJson($this->paymentUrl($paymentId), $data)->assertUnprocessable();
        $this->deleteJson($this->paymentUrl($paymentId))->assertUnprocessable();
        $this->assertDatabaseHas('transactions', ['id' => $paymentId, 'amount' => 10000]);
        $this->assertEquals(-10000, $this->cashBalance());
    }

    public function test_payment_scope_role_and_bank_currency_are_enforced(): void
    {
        $paymentId = $this->pay();
        $otherSchedule = DB::table('loan_interest_schedule')->insertGetId(['loan_id' => $this->loanId, 'due_date' => '2026-12-01', 'interest_amount' => 10000]);
        $this->deleteJson("/loans/{$this->loanId}/schedule/{$otherSchedule}/payments/{$paymentId}")->assertNotFound();
        $bank = DB::table('bank_accounts')->insertGetId(['company_id' => 1, 'bank_name' => 'USD bank', 'account_no' => '456', 'currency' => 'USD']);
        $this->putJson($this->paymentUrl($paymentId), ['paid_amount' => 9000, 'paid_date' => '2026-11-03', 'bank_account_id' => $bank])->assertUnprocessable();
        $this->actingAs(User::factory()->create(['role' => 'viewer', 'is_active' => true]));
        $this->putJson($this->paymentUrl($paymentId), ['paid_amount' => 9000, 'paid_date' => '2026-11-03'])->assertForbidden();
        $this->deleteJson($this->paymentUrl($paymentId))->assertForbidden();
        $this->get("/loans/{$this->loanId}")->assertOk()->assertDontSee('Delete payment');
        $this->assertEquals(-10000, $this->cashBalance());
    }
}
