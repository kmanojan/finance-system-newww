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
        $this->assertDatabaseHas('transactions', ['amount' => 3000, 'description' => "Interest Payment to Test lender\nNote: {$note}"]);
        $this->assertDatabaseHas('transactions', ['amount' => 7000, 'description' => "Interest Payment to Test lender\nNote: Final interest balance"]);
        $this->assertDatabaseHas('loan_interest_schedule', ['id' => $this->scheduleId, 'paid_amount' => 10000, 'status' => 'paid']);
        $this->get("/loans/{$this->loanId}")->assertOk()->assertSee('Payment details (2)')
            ->assertSee($note)->assertDontSee($note, false)->assertSee('Final interest balance');
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
        foreach ([['invalid'], str_repeat('a', 1001)] as $note) {
            $this->postJson($this->url(), ['paid_amount' => 1000, 'notes' => $note])
                ->assertUnprocessable()->assertJsonValidationErrors('notes');
        }
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseHas('loan_interest_schedule', ['id' => $this->scheduleId, 'paid_amount' => 0, 'status' => 'pending']);
    }
}
