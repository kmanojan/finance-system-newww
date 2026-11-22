<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PartyLedgerReportTest extends TestCase
{
    use RefreshDatabase;

    private int $client;

    private int $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 11, 23));
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        DB::table('companies')->insert(['id' => 1, 'name' => 'Ledger company', 'base_currency' => 'LKR']);
        DB::table('departments')->insert(['id' => 1, 'company_id' => 1, 'name' => 'Finance']);
        $this->client = DB::table('parties')->insertGetId(['name' => 'Ledger client', 'types' => 'client,lender']);
        $this->project = DB::table('projects')->insertGetId(['name' => 'Ledger project', 'company_id' => 1, 'department_id' => 1, 'currency' => 'LKR', 'budget_limit' => 3000]);
        DB::table('project_party')->insert(['project_id' => $this->project, 'party_id' => $this->client, 'role' => 'client']);
    }

    public function test_currencies_receipts_and_loan_types_keep_separate_balances(): void
    {
        $invoice = ['project_id' => $this->project, 'client_id' => $this->client, 'department_id' => 1, 'issue_date' => '2026-08-01'];
        $issued = DB::table('invoices')->insertGetId($invoice + ['invoice_no' => 'LKR-ISSUED', 'currency' => 'LKR', 'amount' => 1000, 'status' => 'sent']);
        DB::table('invoices')->insert([
            $invoice + ['invoice_no' => 'USD-ISSUED', 'currency' => 'USD', 'amount' => 100, 'status' => 'sent'],
            $invoice + ['invoice_no' => 'LKR-DRAFT', 'currency' => 'LKR', 'amount' => 2000, 'status' => 'draft'],
            $invoice + ['invoice_no' => 'LKR-CANCELLED', 'currency' => 'LKR', 'amount' => 3000, 'status' => 'cancelled'],
        ]);
        $receipt = DB::table('payments')->insertGetId(['project_id' => $this->project, 'currency' => 'LKR', 'payment_date' => '2026-09-01', 'total_amount' => 600]);
        DB::table('payment_allocations')->insert(['invoice_id' => $issued, 'payment_id' => $receipt, 'amount' => 200]);

        foreach (['interest_separate', 'principal_and_interest'] as $type) {
            $id = DB::table('loans')->insertGetId([
                'party_id' => $this->client, 'lender_name' => 'Ledger client', 'currency' => 'LKR',
                'principal_amount' => 500, 'status' => 'active', 'loan_type' => $type, 'claimed_date' => '2026-08-01',
            ]);
            DB::table('loan_interest_schedule')->insert(['loan_id' => $id, 'due_date' => '2026-09-01', 'interest_amount' => 100, 'paid_amount' => 20, 'status' => 'partially_paid']);
            DB::table('loan_principal_records')->insert(['loan_id' => $id, 'record_type' => 'repayment', 'amount' => 100, 'record_date' => '2026-09-01']);
        }

        $response = $this->get('/reports/party-ledger?party_id='.$this->client)->assertOk()
            ->assertSee('LKR-ISSUED')->assertSee('USD-ISSUED')->assertDontSee('LKR-DRAFT')->assertDontSee('LKR-CANCELLED')
            ->assertSee('Remaining interest')->assertSee('PAY-'.$receipt);
        $party = $response->viewData('party');
        $this->assertEquals(80000, $party->balances['LKR']['receivable']);
        $this->assertEquals(10000, $party->balances['USD']['receivable']);
        $this->assertEquals(40000, $party->balances['LKR']['unapplied']);
        $this->assertEquals(88000, $party->balances['LKR']['loan']);
        $this->get('/reports/export/party-ledger/xlsx?party_id='.$this->client)->assertOk();
    }

    public function test_statutory_balances_belong_to_authorities_and_stay_private(): void
    {
        $staff = Employee::create([
            'first_name' => 'Sam', 'last_name' => 'Ledger', 'full_name' => 'Sam Ledger',
            'employee_code' => 'PL-101', 'basic_salary' => 1000, 'currency' => 'LKR', 'status' => 'active',
        ]);
        $run = DB::table('payroll_runs')->insertGetId([
            'company_id' => 1, 'department_id' => 1, 'month' => '2026-10-01', 'status' => 'approved', 'currency' => 'LKR',
            'salary_due_date' => '2026-10-31', 'statutory_due_date' => '2026-11-30', 'tax_due_date' => '2026-11-15', 'created_by' => auth()->id(),
        ]);
        DB::table('payroll_items')->insert([
            'payroll_run_id' => $run, 'employee_id' => $staff->id, 'snapshot' => '{}', 'adjustments' => '{}', 'calculation' => '{}',
            'net_cents' => 90000, 'epf_cents' => 23000, 'etf_cents' => 3000, 'apit_cents' => 10000,
        ]);
        DB::table('payroll_payments')->insert([
            'payroll_run_id' => $run, 'company_id' => 1, 'department_id' => 1, 'kind' => 'epf', 'currency' => 'LKR',
            'amount_cents' => 10000, 'paid_on' => '2026-11-10', 'reference' => 'Test partial EPF', 'request_key' => 'test-epf',
            'transaction_id' => 1, 'journal_entry_id' => 1, 'created_by' => auth()->id(),
        ]);

        $overview = $this->get('/reports/party-ledger?q=PL-101')->assertOk()->assertSee('Sam Ledger')->assertSee('EPF, ETF & tax', false);
        $this->assertSame(1, $overview->viewData('registry')->total());
        $this->assertEquals(26000, $overview->viewData('statutoryTotals')['LKR']);
        $this->assertSame('Partially paid', $overview->viewData('statutory')->firstWhere('kind', 'epf')->payment_status);
        $detail = $this->get('/reports/party-ledger?party_id='.$staff->party_id)->assertOk()->assertSee('Tax (APIT) deducted')
            ->assertSee('Record salary payment');
        $this->assertEquals(90000, $detail->viewData('party')->balances['LKR']['salary']);

        $this->actingAs(User::factory()->create(['role' => 'viewer', 'is_active' => true]));
        $this->get('/reports/party-ledger?party_id='.$staff->party_id)->assertOk()->assertDontSee('Tax (APIT) deducted')
            ->assertDontSee('Record salary payment')->assertDontSee('LKR 900.00');
    }
}
