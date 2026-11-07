<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\HistoricalPayrollImportService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HistoricalPayrollImportTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['version' => 1, 'source_url' => 'https://docs.google.com/spreadsheets/d/test/edit', 'source_sha256' => str_repeat('a', 64),
            'rows' => [['employee_id' => 1, 'month' => '2025-01', 'sheet' => 'January', 'row' => 4, 'name' => 'Sheet Name',
                'basic' => 10000000, 'package' => 35000000, 'commission' => 1000000, 'no_pay' => 500000, 'tax' => 3250000,
                'epf_ee' => 800000, 'epf_er' => 1200000, 'etf' => 300000, 'advance' => 2000000, 'loan' => 1000000, 'net' => 28450000]]];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        DB::table('companies')->insert(['id' => 1, 'name' => 'Import test', 'base_currency' => 'LKR']);
        DB::table('departments')->insert(['id' => 1, 'company_id' => 1, 'name' => 'Finance']);
        Employee::create(['external_id' => 1, 'first_name' => 'Historical', 'last_name' => 'Employee', 'full_name' => 'Historical Employee', 'employee_code' => 'EMP-1', 'currency' => 'LKR', 'status' => 'inactive', 'basic_salary' => 0]);
    }

    public function test_import_reconciles_source_without_payments_or_journals_and_is_repeatable(): void
    {
        $component = SalaryComponent::create(['code' => 'CUSTOM_ALLOW', 'name' => 'Existing allowance', 'type' => 'earning', 'calculation_type' => 'fixed_amount', 'is_active' => true]);
        EmployeeSalaryComponent::create(['employee_id' => 1, 'salary_component_id' => $component->id, 'value' => 1234, 'is_enabled' => true]);
        $payload = $this->payload();
        $second = $payload['rows'][0];
        $second['month'] = '2025-02';
        $second['sheet'] = 'February';
        $second['basic'] = 12000000;
        $payload['rows'][] = $second;
        $ids = HistoricalPayrollImportService::import($payload, 1, 1, true);
        $this->assertCount(2, $ids);
        $this->assertDatabaseHas('employees', ['id' => 1, 'basic_salary' => 120000, 'status' => 'inactive']);
        $this->assertDatabaseHas('employee_salary_components', ['employee_id' => 1, 'salary_component_id' => $component->id, 'value' => 1234, 'is_enabled' => true]);
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('payroll_payments', 0);
        $this->assertDatabaseCount('payroll_runs', 2);
        $this->assertDatabaseCount('payroll_items', 2);
        foreach ($ids as $id) {
            $item = PayrollService::items($id)->first();
            $this->assertSame(28450000, $item->net_cents);
            $this->assertFalse((bool) $item->reviewed);
            $this->assertSame(0, collect(HistoricalPayrollImportService::comparison($item))->sum('difference'));
            $this->get('/payroll/'.$id)->assertOk()->assertSee('Salary sheet comparison')->assertSee('0 employees with differences');
        }
        $this->assertSame($ids, HistoricalPayrollImportService::import($payload, 1, 1, true));
        $this->assertDatabaseCount('payroll_runs', 2);
        $this->assertDatabaseCount('payroll_items', 2);
    }

    public function test_source_difference_or_duplicate_employee_rolls_back_whole_import(): void
    {
        $payload = $this->payload();
        $payload['rows'][0]['net']++;
        try {
            HistoricalPayrollImportService::import($payload, 1, 1);
            $this->fail('Difference must be rejected.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('differs', $error->getMessage());
        }
        $payload = $this->payload();
        $payload['rows'][] = $payload['rows'][0];
        try {
            HistoricalPayrollImportService::import($payload, 1, 1);
            $this->fail('Duplicate must be rejected.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('more than one', $error->getMessage());
        }
        $this->assertDatabaseCount('payroll_runs', 0);
        $this->assertDatabaseCount('payroll_items', 0);
        $this->assertDatabaseHas('employees', ['id' => 1, 'basic_salary' => 0]);
    }

    public function test_existing_profiles_are_preserved_and_historical_recoveries_cannot_be_approved(): void
    {
        Employee::find(1)->update(['basic_salary' => 200000]);
        $id = HistoricalPayrollImportService::import($this->payload(), 1, 1, true)[0];
        $this->assertDatabaseHas('employees', ['id' => 1, 'basic_salary' => 200000]);
        DB::table('payroll_items')->where('payroll_run_id', $id)->update(['reviewed' => true]);
        PayrollService::transition($id, 'submit');
        try {
            PayrollService::transition($id, 'approve');
            $this->fail('Unlinked historical recoveries must block approval.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('opening-balance reconciliation', $error->getMessage());
        }
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseHas('payroll_runs', ['id' => $id, 'status' => 'review']);
    }

    public function test_comparison_catches_offsetting_changes_even_if_net_is_unchanged(): void
    {
        $id = HistoricalPayrollImportService::import($this->payload(), 1, 1)[0];
        $item = PayrollService::items($id)->first();
        $item->calculation['apit'] += 100;
        $item->calculation['advance'] -= 100;
        $fields = HistoricalPayrollImportService::comparison($item);
        $this->assertSame(0, $fields['Net']['difference']);
        $this->assertSame(100, $fields['Tax']['difference']);
        $this->assertSame(-100, $fields['Advance']['difference']);
    }

    public function test_upload_requires_confirmed_mapping_and_conflicting_source_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('history.json', json_encode($this->payload()));
        $this->post('/payroll/import-history', ['company_id' => 1, 'department_id' => 1, 'source_file' => $file])->assertSessionHasErrors('confirm_mapping');
        $this->assertDatabaseCount('payroll_runs', 0);
        $this->post('/payroll/import-history', ['company_id' => 1, 'department_id' => 1, 'source_file' => $file, 'confirm_mapping' => 1])->assertRedirect('/payroll?company_id=1');
        $different = $this->payload();
        $different['source_sha256'] = str_repeat('b', 64);
        try {
            HistoricalPayrollImportService::import($different, 1, 1);
            $this->fail('A conflicting source must not replace an existing draft.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('different payroll', $error->getMessage());
        }
        $this->assertDatabaseCount('payroll_runs', 1);
        $this->assertDatabaseCount('payroll_items', 1);
    }

    public function test_separate_allowance_and_partial_package_retain_source_basic_and_explanation(): void
    {
        $payload = $this->payload();
        foreach (HistoricalPayrollImportService::AMOUNTS as $field) {
            $payload['rows'][0][$field] = 0;
        }
        $payload['rows'][0] = array_replace($payload['rows'][0], ['basic' => 3000000, 'package' => 2318181,
            'allowance' => 305000, 'net' => 2623181, 'source_note' => 'Source basic is 30,000; package is 23,181.81. Breakdown <review>.']);
        $id = HistoricalPayrollImportService::import($payload, 1, 1)[0];
        $item = PayrollService::items($id)->first();
        $fields = HistoricalPayrollImportService::comparison($item);
        $this->assertSame(3000000, $item->snapshot['basic']);
        $this->assertSame(-681819, $fields['Package balance']['system']);
        $this->assertSame(305000, $fields['Allowance']['system']);
        $this->assertSame(2623181, $item->gross_cents);
        $this->assertSame(2623181, $item->net_cents);
        $this->assertSame(0, collect($fields)->sum('difference'));
        $this->assertSame($payload['rows'][0]['source_note'], $item->snapshot['historical_source']['source_note']);
        $this->assertStringContainsString('Breakdown <review>.', $item->adjustments['notes']);
        $this->get('/payroll/'.$id)->assertOk()->assertSee('Package balance')->assertSee('Breakdown <review>.');
        $this->assertSame([$id], HistoricalPayrollImportService::import($payload, 1, 1));
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_mixed_optional_allowance_rows_render_with_consistent_columns(): void
    {
        $employee = Employee::create(['external_id' => 2, 'first_name' => 'Second', 'last_name' => 'Employee', 'full_name' => 'Second Employee', 'currency' => 'LKR', 'status' => 'active']);
        $payload = $this->payload();
        $second = $payload['rows'][0];
        $second['employee_id'] = $employee->id;
        $second['allowance'] = 123400;
        $second['net'] += 123400;
        $payload['rows'][] = $second;
        $id = HistoricalPayrollImportService::import($payload, 1, 1)[0];
        $this->get('/payroll/'.$id)->assertOk()->assertSee('0 employees with differences')->assertSee('Allowance')->assertSee('1,234.00');
    }
}
