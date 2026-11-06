<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\FinancialYear;
use App\Models\FiscalPeriod;
use App\Models\FixedAsset;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\FinancialReportingService;
use App\Services\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinanceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('companies')->insert(['id' => 1, 'name' => 'Finance Test', 'base_currency' => 'LKR']);
        DB::table('departments')->insert(['id' => 1, 'company_id' => 1, 'name' => 'Finance']);
        DB::table('categories')->insert(['id' => 1, 'company_id' => 1, 'name' => 'Expenses', 'type' => 'expense']);
        DB::table('parties')->insert(['id' => 1, 'name' => 'Test client', 'types' => 'client']);
        DB::table('projects')->insert(['id' => 1, 'name' => 'Test project', 'company_id' => 1, 'department_id' => 1, 'currency' => 'LKR', 'status' => 'active']);
        DB::table('invoice_types')->insert(['id' => 1, 'name' => 'Services', 'maps_to' => 'income']);
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    }

    private function transaction(array $overrides = []): array
    {
        return array_replace(['type' => 'expense', 'amount' => 100, 'category_id' => 1, 'department_id' => 1, 'currency' => 'LKR', 'transaction_date' => '2026-11-06', 'description' => 'Office supplies'], $overrides);
    }

    private function invoice(array $overrides = []): int
    {
        return DB::table('invoices')->insertGetId(array_replace(['invoice_no' => 'INV-TEST', 'client_id' => 1, 'project_id' => 1, 'department_id' => 1, 'amount' => 10000, 'grand_total' => 10000, 'currency' => 'LKR', 'status' => 'sent', 'issue_date' => '2026-11-01', 'invoice_date' => '2026-11-01', 'due_date' => '2026-11-06', 'created_at' => now()], $overrides));
    }

    public function test_failed_login_preserves_email_and_remember_choice_without_password(): void
    {
        $this->post('/logout')->assertRedirect('/login');
        $this->from('/login')->post('/login', [
            'email' => 'missing@example.test',
            'password' => 'incorrect-password',
            'remember' => '0',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors('email')
            ->assertSessionHasInput('email', 'missing@example.test')
            ->assertSessionHasInput('remember', '0')
            ->assertSessionMissing('_old_input.password');
    }

    public function test_dashboard_supports_invoices_without_legacy_invoice_date_column(): void
    {
        $issued = $this->invoice(['invoice_no' => 'ISSUED', 'issue_date' => '2026-11-01']);
        $fallback = $this->invoice(['invoice_no' => 'FALLBACK', 'issue_date' => null, 'invoice_date' => '2026-12-01', 'created_at' => '2026-10-01 00:00:00']);
        $this->invoice(['invoice_no' => 'FUTURE', 'issue_date' => '2026-12-01', 'created_at' => '2026-10-01 00:00:00']);
        $this->invoice(['invoice_no' => 'DRAFT', 'status' => 'draft']);

        $this->assertEquals([$issued], FinancialReportingService::issuedInvoices('2026-11-15')->orderBy('id')->pluck('id')->all());
        Schema::table('invoices', fn ($table) => $table->dropColumn('invoice_date'));
        $this->assertEquals([$issued, $fallback], FinancialReportingService::issuedInvoices('2026-11-15')->orderBy('id')->pluck('id')->all());
        $this->get('/dashboard')->assertOk();
    }

    public function test_viewer_cannot_escalate_privileges_or_write_money(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'viewer', 'is_active' => true]));
        $this->postJson('/master/users', ['name' => 'Intruder', 'email' => 'intruder@example.test', 'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'admin'])->assertForbidden();
        $this->postJson('/transactions', $this->transaction())->assertForbidden();
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_inactive_session_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => false]));
        $this->get('/dashboard')->assertForbidden();
    }

    public function test_maintenance_routes_are_absent_and_mentions_require_login(): void
    {
        $this->get('/migrate')->assertNotFound();
        $this->get('/run-migrations')->assertNotFound();
        auth()->logout();
        $this->getJson('/rich-editor/mentions?type=party')->assertUnauthorized();
    }

    public function test_invalid_amounts_never_write_transactions(): void
    {
        foreach ([0, -100] as $amount) {
            $this->postJson('/transactions', $this->transaction(['amount' => $amount]))->assertUnprocessable();
        }
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_closed_period_blocks_creates_and_existing_transaction_edits(): void
    {
        $id = DB::table('transactions')->insertGetId($this->transaction());
        $fy = FinancialYear::create(['company_id' => 1, 'title' => 'FY2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        FiscalPeriod::create(['financial_year_id' => $fy->id, 'period_name' => 'November', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'status' => 'hard_closed']);
        $this->postJson('/transactions', $this->transaction())->assertUnprocessable();
        $this->putJson('/transactions/'.$id, $this->transaction(['transaction_date' => '2026-12-01']))->assertUnprocessable();
        $this->deleteJson('/transactions/'.$id)->assertUnprocessable();
        $this->assertDatabaseHas('transactions', ['id' => $id, 'transaction_date' => '2026-11-06']);
    }

    public function test_balanced_journal_posts_on_fresh_migrations_and_unbalanced_is_rejected(): void
    {
        $data = ['entry_date' => '2026-11-06', 'description' => 'Opening entry', 'lines' => [['account_name' => 'Cash', 'account_type' => 'asset', 'debit' => 100], ['account_name' => 'Capital', 'account_type' => 'equity', 'credit' => 100]]];
        $this->postJson('/journal-entries', $data)->assertRedirect();
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertEquals(100, DB::table('journal_entry_lines')->sum('debit'));
        $this->assertEquals(100, DB::table('journal_entry_lines')->sum('credit'));
        $data['lines'][1]['credit'] = 1;
        $this->postJson('/journal-entries', $data)->assertUnprocessable();
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_invoice_creation_and_audit_work_on_fresh_migrations(): void
    {
        $this->postJson('/invoices', ['client_id' => 1, 'project_id' => 1, 'issue_date' => '2026-11-06', 'item_description' => ['Services'], 'item_qty' => [2], 'item_price' => [100], 'item_type_id' => [1], 'discount_type' => 'percentage', 'discount_value' => 10])->assertRedirect();
        $this->assertDatabaseHas('invoices', ['amount' => 180, 'discount_amount' => 20]);
        $this->assertDatabaseHas('activity_logs', ['model_type' => 'Invoice']);
    }

    public function test_share_creation_and_private_document_filter(): void
    {
        foreach ([true, false] as $visible) {
            DB::table('project_documents')->insert(['project_id' => 1, 'name' => $visible ? 'Public brief' : 'Private payroll', 'type' => 'brief', 'source_type' => 'link', 'url' => 'https://example.test/doc', 'visible_to_client' => $visible, 'created_at' => now()]);
        }
        $this->postJson('/share-links', ['shareable_type' => 'project', 'shareable_id' => 1, 'audience' => 'client', 'allow_downloads' => false])->assertRedirect();
        $token = DB::table('share_links')->value('token');
        auth()->logout();
        $this->get('/share/'.$token)->assertOk()->assertSee('Public brief')->assertDontSee('Private payroll');
        $this->get('/share/'.$token.'/documents/1')->assertForbidden();
        $this->get('/share/'.$token.'/documents/2')->assertNotFound();
    }

    public function test_invalid_payment_totals_roll_back_every_record(): void
    {
        $id = $this->invoice();
        $payment = ['payment_date' => '2026-11-06', 'total_amount' => 100, 'currency' => 'LKR', 'pm_mode' => ['cash'], 'pm_amount' => [1], 'alloc_invoice_id' => [$id], 'alloc_amount' => [100]];
        $this->postJson('/projects/1/payments', $payment)->assertUnprocessable();
        $payment['pm_amount'] = [100];
        $payment['alloc_amount'] = [1000];
        $this->postJson('/projects/1/payments', $payment)->assertUnprocessable();
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_cross_project_allocation_is_rejected(): void
    {
        DB::table('projects')->insert(['id' => 2, 'name' => 'Other project', 'company_id' => 1, 'department_id' => 1, 'currency' => 'LKR']);
        $id = $this->invoice(['project_id' => 2]);
        $this->postJson('/projects/1/payments', ['payment_date' => '2026-11-06', 'total_amount' => 100, 'currency' => 'LKR', 'pm_mode' => ['cash'], 'pm_amount' => [100], 'alloc_invoice_id' => [$id], 'alloc_amount' => [100]])->assertUnprocessable();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_paid_invoice_cannot_be_deleted_or_manually_marked_paid(): void
    {
        $id = $this->invoice(['status' => 'paid']);
        $this->deleteJson('/invoices/'.$id)->assertUnprocessable();
        $this->patchJson('/invoices/'.$id.'/status', ['status' => 'draft'])->assertUnprocessable();
        $this->assertDatabaseHas('invoices', ['id' => $id, 'status' => 'paid']);
    }

    public function test_depreciation_is_idempotent_per_month(): void
    {
        $asset = FixedAsset::create(['asset_name' => 'Laptop', 'asset_code' => 'FA-1', 'category' => 'Equipment', 'purchase_date' => '2026-01-01', 'purchase_cost' => 12000, 'salvage_value' => 0, 'lifespan_years' => 1, 'depreciation_method' => 'straight_line', 'status' => 'active']);
        $this->postJson('/assets/fixed-assets/'.$asset->id.'/depreciate', ['posting_date' => '2026-11-06'])->assertOk();
        $this->postJson('/assets/fixed-assets/'.$asset->id.'/depreciate', ['posting_date' => '2026-11-30'])->assertUnprocessable();
        $this->assertEquals(1000, $asset->fresh()->accumulated_depreciation);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_historical_receivables_use_cutoff_and_partial_payments(): void
    {
        $id = $this->invoice();
        $payment = DB::table('payments')->insertGetId(['project_id' => 1, 'total_amount' => 5000, 'currency' => 'LKR', 'payment_date' => '2026-11-05']);
        DB::table('payment_allocations')->insert(['payment_id' => $payment, 'invoice_id' => $id, 'amount' => 5000]);
        $this->get('/reports/ar-aging?from=2026-11-01&to=2026-11-06')->assertOk()->assertViewHas('agingData', fn ($rows) => $rows[0]->total === 5000.0);
        $this->get('/reports/ar-aging?from=2026-11-01&to=2026-11-02')->assertOk()->assertViewHas('agingData', fn ($rows) => $rows[0]->total === 10000.0);
        $this->get('/reports/balance-sheet?from=2020-01-01&to=2020-01-01')->assertOk()->assertViewHas('accountsReceivable', 0);
        $this->get('/reports/client-statement?client_id=1&from=2026-11-01&to=2026-11-06')->assertOk()->assertSee('INV-TEST');
    }

    public function test_loan_cash_is_counted_once_and_not_as_profit(): void
    {
        $category = DB::table('categories')->insertGetId(['company_id' => 1, 'name' => 'Loan Disbursement', 'type' => 'income']);
        $this->postJson('/transactions', $this->transaction(['type' => 'income', 'amount' => 1000, 'category_id' => $category]))->assertOk();
        $this->get('/reports/cash-flow?from=2026-11-01&to=2026-11-30')->assertOk()->assertViewHas('netCashFlow', 1000)->assertViewHas('operatingInflows', 0)->assertViewHas('financingInflows', 1000);
        $this->get('/reports/pnl?from=2026-11-01&to=2026-11-30')->assertOk()->assertViewHas('data', fn ($data) => $data['totalIncome'] == 0);
    }

    public function test_bank_matching_requires_correct_direction_and_updates_report_timestamp(): void
    {
        $bank = DB::table('bank_accounts')->insertGetId(['company_id' => 1, 'bank_name' => 'Test bank', 'account_no' => '123', 'currency' => 'LKR']);
        $tx = DB::table('transactions')->insertGetId($this->transaction(['bank_account_id' => $bank]));
        $statement = DB::table('bank_statement_imports')->insertGetId(['bank_account_id' => $bank, 'statement_date' => '2026-11-06', 'amount' => 100, 'type' => 'credit']);
        $this->postJson('/treasury/bank-reconciliation/'.$statement.'/match', ['transaction_id' => $tx])->assertUnprocessable();
        DB::table('bank_statement_imports')->where('id', $statement)->update(['type' => 'debit']);
        $this->postJson('/treasury/bank-reconciliation/'.$statement.'/match', ['transaction_id' => $tx])->assertRedirect();
        $this->assertNotNull(DB::table('transactions')->where('id', $tx)->value('reconciled_at'));
        $this->get('/treasury/bank-reconciliation')->assertOk();
        $this->deleteJson('/treasury/bank-reconciliation/'.$statement.'/match')->assertRedirect();
        $this->assertNull(DB::table('transactions')->where('id', $tx)->value('reconciled_at'));
    }

    public function test_vendor_bill_partial_payment_posts_balanced_entries_and_blocks_overpayment(): void
    {
        $bank = DB::table('bank_accounts')->insertGetId(['company_id' => 1, 'bank_name' => 'Test bank', 'account_no' => '123', 'currency' => 'LKR']);
        $this->postJson('/payables/vendor-bills', ['bill_number' => 'VB-001', 'vendor_id' => 1, 'department_id' => 1, 'amount' => 1000, 'currency' => 'LKR', 'issue_date' => '2026-11-01', 'due_date' => '2026-11-30'])->assertCreated();
        $this->postJson('/payables/vendor-bills/1/payments', ['amount' => 400, 'bank_account_id' => $bank, 'payment_date' => '2026-11-06'])->assertOk();
        $this->assertDatabaseHas('vendor_bills', ['id' => 1, 'status' => 'partially_paid']);
        $this->postJson('/payables/vendor-bills/1/payments', ['amount' => 700, 'bank_account_id' => $bank, 'payment_date' => '2026-11-06'])->assertUnprocessable();
        $this->assertDatabaseCount('vendor_payments', 1);
        $this->assertEquals(DB::table('journal_entry_lines')->sum('debit'), DB::table('journal_entry_lines')->sum('credit'));
        $this->get('/payables/vendor-bills')->assertOk()->assertSee('VB-001');
    }

    public function test_financial_year_overlap_is_rejected_and_management_pages_render(): void
    {
        $data = ['company_id' => 1, 'title' => 'FY 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31'];
        $this->postJson('/settings/financial-years', $data)->assertCreated();
        $this->postJson('/settings/financial-years', $data)->assertUnprocessable();
        $this->assertDatabaseCount('fiscal_periods', 12);
        foreach (['/settings/financial-years', '/master/accounts', '/payables/purchase-orders'] as $url) {
            $this->get($url)->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8');
        }
    }

    public function test_percentage_earning_and_historical_currency_rate(): void
    {
        $employee = new Employee(['basic_salary' => 100000]);
        $component = new SalaryComponent(['type' => 'earning', 'is_active' => true, 'calculation_type' => 'percentage', 'calculation_base' => 'on_basic']);
        $mapping = new EmployeeSalaryComponent(['is_enabled' => true, 'value' => 10]);
        $mapping->setRelation('component', $component);
        $employee->setRelation('salaryComponents', collect([$mapping]));
        $this->assertEquals(10000, $employee->calculateSalaryBreakdown()['total_allowances']);
        DB::table('currency_exchange_rates')->insert([['base_currency' => 'USD', 'target_currency' => 'LKR', 'rate' => 300, 'rate_date' => '2026-01-01'], ['base_currency' => 'USD', 'target_currency' => 'LKR', 'rate' => 350, 'rate_date' => '2026-12-01']]);
        $this->assertEquals(30000, FinancialReportingService::convert(100, 'USD', '2026-11-06'));
    }

    public function test_draft_edit_duplicate_and_issue_post_once(): void
    {
        $id = $this->invoice(['status' => 'draft']);
        DB::table('invoice_items')->insert(['invoice_id' => $id, 'invoice_type_id' => 1, 'description' => 'Design', 'qty' => 1, 'unit_price' => 10000, 'total' => 10000, 'currency' => 'LKR']);
        $this->get('/invoices/'.$id.'/edit')->assertOk();
        $payload = ['client_id' => 1, 'project_id' => 1, 'invoice_no' => 'INV-TEST', 'issue_date' => '2026-11-01', 'currency' => 'LKR', 'status' => 'draft', 'item_description' => ['Design'], 'item_qty' => [2], 'item_price' => [7500], 'item_type_id' => [1]];
        $this->putJson('/invoices/'.$id, $payload)->assertRedirect();
        $this->assertDatabaseHas('invoices', ['id' => $id, 'amount' => 15000]);
        $this->post('/invoices/'.$id.'/duplicate')->assertRedirect();
        $this->assertDatabaseCount('invoices', 2);
        $this->assertDatabaseCount('invoice_items', 2);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->patchJson('/invoices/'.$id.'/status', ['status' => 'sent'])->assertRedirect();
        $this->assertDatabaseCount('journal_entries', 1);
        $this->putJson('/invoices/'.$id, $payload)->assertUnprocessable();
        $this->patchJson('/invoices/'.$id.'/status', ['status' => 'cancelled'])->assertRedirect();
        $this->assertDatabaseCount('journal_entries', 2);
        $balances = FinancialReportingService::ledgerBalances('2026-01-01', '2026-12-31');
        $this->assertEquals(0, $balances->firstWhere('code', '1200')->balance);
    }

    public function test_loan_status_activation_posts_once_and_cannot_skip_settlement(): void
    {
        $this->postJson('/loans', ['lender_name' => 'Lender', 'principal_amount' => 10000, 'currency' => 'LKR', 'claimed_date' => '2026-11-01', 'interest_method' => 'no_interest', 'status' => 'active'])->assertRedirect();
        $this->assertDatabaseHas('loans', ['id' => 1, 'status' => 'pending']);
        $this->post('/loans/1/status', ['status' => 'active'])->assertRedirect();
        $this->post('/loans/1/activate')->assertRedirect();
        $this->assertDatabaseCount('transactions', 1);
        $this->postJson('/loans/1/status', ['status' => 'settled'])->assertUnprocessable();
        $this->putJson('/loans/1', ['principal_amount' => 20000])->assertUnprocessable();
        $this->assertDatabaseHas('transactions', ['amount' => 10000]);
    }

    public function test_exports_contain_cash_flow_data_and_search_renders(): void
    {
        $this->postJson('/transactions', $this->transaction())->assertOk();
        $this->get('/search?q=Office')->assertOk()->assertSee('Office supplies');
        $this->get('/reports/client-health')->assertOk();
        $response = $this->get('/reports/export/cash-flow/xlsx?from=2026-11-01&to=2026-11-30')->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path));
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertStringContainsString('Operating Expense Outflows', $xml);
        $this->assertStringContainsString('100.00', $xml);
        unlink($path);
        $this->get('/reports/export/pnl/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_company_period_lock_does_not_block_other_company_journal(): void
    {
        $year = FinancialYear::create(['company_id' => 1, 'title' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        FiscalPeriod::create(['financial_year_id' => $year->id, 'period_name' => 'November', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'status' => 'hard_closed']);
        DB::table('companies')->insert(['id' => 2, 'name' => 'Second', 'base_currency' => 'LKR']);
        $entry = LedgerPostingService::postJournal('2026-11-06', 'Second company', [['account_code' => '1010', 'debit' => 100], ['account_code' => '4100', 'credit' => 100]], null, null, 2);
        $this->assertNotNull($entry->id);
        $this->assertEquals(2, DB::table('accounts')->where('id', $entry->lines()->first()->account_id)->value('company_id'));
    }

    public function test_year_end_close_transfers_earnings_and_cannot_be_repeated(): void
    {
        $this->postJson('/settings/financial-years', ['company_id' => 1, 'title' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31'])->assertCreated();
        LedgerPostingService::postJournal('2026-11-01', 'Revenue', [['account_code' => '1010', 'debit' => 1000], ['account_code' => '4100', 'credit' => 1000]], null, null, 1);
        LedgerPostingService::postJournal('2026-11-02', 'Expense', [['account_code' => '5200', 'debit' => 400], ['account_code' => '1010', 'credit' => 400]], null, null, 1);
        $this->postJson('/settings/financial-years/1/close')->assertUnprocessable();
        DB::table('fiscal_periods')->update(['status' => 'hard_closed']);
        $this->postJson('/settings/financial-years/1/close')->assertOk();
        $this->assertDatabaseHas('financial_years', ['id' => 1, 'is_closed' => true]);
        $balances = FinancialReportingService::ledgerBalances('2026-01-01', '2026-12-31');
        $this->assertEquals(-600, $balances->firstWhere('code', '3100')->balance);
        $this->assertEquals(0, $balances->whereIn('type', ['revenue', 'expense'])->sum('balance'));
        $this->postJson('/settings/financial-years/1/close')->assertUnprocessable();
        $this->putJson('/settings/fiscal-periods/1/status', ['status' => 'open'])->assertUnprocessable();
        $this->assertDatabaseCount('journal_entries', 3);
    }

    public function test_public_share_enforces_project_password_and_download_scope(): void
    {
        $id = $this->invoice();
        DB::table('share_links')->insert(['id' => 1, 'token' => 'public-review-token', 'shareable_type' => 'project', 'shareable_id' => 1, 'audience' => 'client', 'is_active' => true, 'allow_downloads' => false, 'expires_at' => now()->addDay()]);
        DB::table('project_documents')->insert(['project_id' => 1, 'name' => 'Private internal agreement', 'type' => 'other', 'source_type' => 'link', 'url' => 'https://example.test/private', 'visible_to_client' => false]);
        auth()->logout();
        $this->get('/share/public-review-token')->assertOk()->assertDontSee('Private internal agreement');
        $this->get('/share/public-review-token/invoices/'.$id.'/pdf')->assertForbidden();
        DB::table('projects')->insert(['id' => 2, 'name' => 'Another project', 'company_id' => 1, 'department_id' => 1]);
        $other = $this->invoice(['invoice_no' => 'INV-OTHER', 'project_id' => 2]);
        $this->get('/share/public-review-token/invoices/'.$other.'/view')->assertNotFound();
        DB::table('share_links')->where('id', 1)->update(['password_hash' => Hash::make('secret-test-password')]);
        $this->get('/share/public-review-token/invoices/'.$id.'/view')->assertForbidden();
        $this->post('/share/public-review-token/password', ['password' => 'secret-test-password'])->assertRedirect();
        DB::table('share_links')->where('id', 1)->update(['revoked_at' => now()]);
        $this->get('/share/public-review-token/invoices/'.$id.'/view')->assertNotFound();
    }

    public function test_statement_import_is_atomic_and_skips_duplicate_files(): void
    {
        $bank = DB::table('bank_accounts')->insertGetId(['company_id' => 1, 'bank_name' => 'Import bank', 'account_no' => 'ABC', 'currency' => 'LKR']);
        $file = fn ($content) => UploadedFile::fake()->createWithContent('statement.csv', $content);
        $this->postJson('/treasury/bank-reconciliation/import', ['bank_account_id' => $bank, 'statement' => $file("date,amount,type\n2026-11-06,100,credit\ninvalid,-1,debit\n")])->assertUnprocessable();
        $this->assertDatabaseCount('bank_statement_imports', 0);
        for ($i = 0; $i < 2; $i++) {
            $this->post('/treasury/bank-reconciliation/import', ['bank_account_id' => $bank, 'statement' => $file("date,amount,type\n2026-11-06,100,credit\n")])->assertRedirect();
        }
        $this->assertDatabaseCount('bank_statement_imports', 1);
    }

    public function test_private_document_migration_previews_then_verifies_and_moves_only_referenced_files(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);
        Storage::disk('public')->put('attachments/projects/test.pdf', 'test document bytes');
        Storage::disk('public')->put('unrelated-logo.svg', 'logo');
        DB::table('attachments')->insert(['model_type' => 'ProjectDocument', 'model_id' => 1, 'file_name' => 'test.pdf', 'file_path' => 'attachments/projects/test.pdf']);
        $this->artisan('finance:privatize-documents')->assertSuccessful();
        Storage::disk('public')->assertExists('attachments/projects/test.pdf');
        $this->artisan('finance:privatize-documents', ['--apply' => true])->assertSuccessful();
        $this->assertEquals('test document bytes', Storage::disk('local')->get('attachments/projects/test.pdf'));
        Storage::disk('public')->assertMissing('attachments/projects/test.pdf');
        Storage::disk('public')->assertExists('unrelated-logo.svg');
    }
}
