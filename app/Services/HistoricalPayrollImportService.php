<?php

namespace App\Services;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Import reviewed source amounts as drafts; never create historical payments or journals. */
class HistoricalPayrollImportService
{
    public const AMOUNTS = ['basic', 'package', 'commission', 'no_pay', 'tax', 'epf_ee', 'epf_er', 'etf', 'advance', 'loan', 'net'];

    public static function validate(array $data): array
    {
        $rules = ['version' => 'required|in:1', 'source_url' => 'required|url|max:1000', 'source_sha256' => 'required|regex:/^[a-f0-9]{64}$/',
            'rows' => 'required|array|min:1|max:15000', 'rows.*.employee_id' => 'required|integer',
            'rows.*.month' => 'required|date_format:Y-m', 'rows.*.sheet' => 'required|string|max:100',
            'rows.*.row' => 'required|integer|min:1', 'rows.*.name' => 'required|string|max:255'];
        foreach (self::AMOUNTS as $field) {
            $rules['rows.*.'.$field] = 'required|integer|min:0|max:999999999999';
        }
        $data = validator($data, $rules)->validate();
        foreach ($data['rows'] as &$row) {
            foreach (array_merge(self::AMOUNTS, ['employee_id', 'row']) as $field) {
                $row[$field] = (int) $row[$field];
            }
        }
        unset($row);

        return $data;
    }

    public static function calculate(Employee $employee, array $row, array $source): array
    {
        self::check($row['package'] >= $row['basic'], 'Source package cannot be less than basic salary.');
        $snapshot = PayrollCalculationService::snapshot($employee);
        $snapshot['basic'] = $row['basic'];
        $snapshot['currency'] = 'LKR';
        $snapshot['components'] = [];
        foreach ([
            ['FIXED_ALLOW', 'Fixed allowance', 'earning', $row['package'] - $row['basic']],
            ['COMMISSION', 'Commission', 'earning', $row['commission']],
            ['EPF_EE', 'Employee EPF', 'deduction', $row['epf_ee']],
            ['APIT', 'APIT', 'deduction', $row['tax']],
            ['HIST_LOAN', 'Employee loan deduction (source)', 'deduction', $row['loan']],
            ['EPF_ER', 'Employer EPF', 'employer_contribution', $row['epf_er']],
            ['ETF_ER', 'ETF', 'employer_contribution', $row['etf']],
        ] as [$code, $name, $type, $amount]) {
            $snapshot['components'][] = ['id' => null, 'code' => $code, 'name' => $name, 'type' => $type,
                'method' => 'fixed_amount', 'base' => 'custom', 'value' => $amount / 100,
                'epf_eligible' => false, 'taxable' => $type === 'earning', 'payroll_input_mode' => 'display'];
        }
        $snapshot['historical_source'] = $source + $row;
        $adjustments = ['unpaid_leave' => $row['no_pay'] / 100, 'apit' => $row['tax'] / 100,
            'advance' => $row['advance'] / 100, 'epf_base' => $row['basic'] / 100, 'etf_base' => $row['basic'] / 100,
            'notes' => 'Imported from '.$row['sheet'].' row '.$row['row'].'. Compare with the source before approval.'];
        $calculation = PayrollCalculationService::calculate($snapshot, $adjustments);
        foreach (['net' => 'net', 'ee' => 'epf_ee', 'er' => 'epf_er', 'etf' => 'etf', 'apit' => 'tax', 'advance' => 'advance'] as $actual => $expected) {
            self::check($calculation[$actual] === $row[$expected], $row['sheet'].' row '.$row['row'].': '.$expected.' differs from the source.');
        }

        return compact('snapshot', 'adjustments', 'calculation');
    }

    public static function import(array $input, int $companyId, int $departmentId, bool $fillProfiles = false): array
    {
        $data = self::validate($input);

        return DB::transaction(function () use ($data, $companyId, $departmentId, $fillProfiles) {
            $company = DB::table('companies')->where('id', $companyId)->lockForUpdate()->first();
            self::check($company && DB::table('departments')->where('id', $departmentId)->where('company_id', $companyId)->exists(), 'Choose a valid company and department.');
            $employees = Employee::with('salaryComponents.component')->whereIn('id', array_column($data['rows'], 'employee_id'))->lockForUpdate()->get()->keyBy('id');
            self::check($employees->count() === count(array_unique(array_column($data['rows'], 'employee_id'))), 'The import contains an unknown employee. Confirm every employee match first.');
            $source = ['url' => $data['source_url'], 'sha256' => $data['source_sha256']];
            $months = collect($data['rows'])->groupBy('month')->sortKeys();
            $runs = [];
            foreach ($months as $month => $rows) {
                self::check($rows->pluck('employee_id')->unique()->count() === $rows->count(), $month.': an employee is matched to more than one source row.');
                $key = $companyId.':'.$month.'-01:LKR';
                $existing = DB::table('payroll_runs')->where('active_key', $key)->first();
                if ($existing) {
                    self::check($existing->status === 'draft' && str_contains($existing->notes ?? '', 'Source SHA-256: '.$source['sha256']), $month.': a different payroll already exists.');
                    $existingItems = PayrollService::items($existing->id);
                    $byEmployee = $existingItems->keyBy('employee_id');
                    self::check((int) $existing->department_id === $departmentId && $existingItems->count() === $rows->count() && $rows->every(function ($row) use ($byEmployee, $source) {
                        $saved = $byEmployee->get($row['employee_id'])?->snapshot['historical_source'] ?? [];

                        return ($saved['sha256'] ?? '') === $source['sha256'] && ! array_diff_assoc($row, $saved);
                    }), 'Existing draft does not match the complete source import.');
                    $runs[] = $existing->id;

                    continue;
                }
                $date = Carbon::parse($month.'-01');
                $runId = DB::table('payroll_runs')->insertGetId(['company_id' => $companyId, 'department_id' => $departmentId,
                    'month' => $date->toDateString(), 'currency' => 'LKR', 'active_key' => $key, 'status' => 'draft',
                    'salary_due_date' => $date->copy()->endOfMonth()->toDateString(),
                    'statutory_due_date' => $date->copy()->addMonth()->endOfMonth()->toDateString(),
                    'tax_due_date' => $date->copy()->addMonth()->day(15)->toDateString(),
                    'created_by' => auth()->id(), 'notes' => 'Historical comparison draft. '.$source['url'].' Source SHA-256: '.$source['sha256'].'. Due dates are provisional. No payments or journals imported.', 'created_at' => now(), 'updated_at' => now()]);
                $items = [];
                foreach ($rows as $row) {
                    $employee = $employees[$row['employee_id']];
                    self::check($employee->currency === 'LKR', $employee->full_name.' has a different salary currency.');
                    $values = self::calculate($employee, $row, $source);
                    $item = ['payroll_run_id' => $runId, 'employee_id' => $employee->id, 'active_key' => $employee->id.':'.$month.'-01',
                        'snapshot' => json_encode($values['snapshot']), 'adjustments' => json_encode($values['adjustments']),
                        'calculation' => json_encode($values['calculation']), 'reviewed' => false, 'created_at' => now(), 'updated_at' => now()];
                    foreach (['gross', 'net', 'epf', 'etf', 'apit', 'other', 'advance', 'cost'] as $field) {
                        $item[$field.'_cents'] = $values['calculation'][$field === 'other' ? 'other_payable' : $field];
                    }
                    $items[] = $item;
                }
                foreach (array_chunk($items, 100) as $chunk) {
                    DB::table('payroll_items')->insert($chunk);
                }
                ActivityLogService::logCreate('Payroll', $runId, ['source' => $source, 'month' => $month, 'employees' => count($items), 'status' => 'draft']);
                $runs[] = $runId;
            }
            if ($fillProfiles) {
                $latest = collect($data['rows'])->filter(fn ($row) => $row['basic'] > 0)->sortByDesc('month')->unique('employee_id');
                foreach ($latest as $row) {
                    $employee = $employees[$row['employee_id']];
                    if ($employee->basic_salary > 0 || $row['basic'] === 0) {
                        continue;
                    }
                    $before = SalaryHistoryService::snapshot($employee);
                    $employee->update(['basic_salary' => $row['basic'] / 100]);
                    SalaryHistoryService::record($employee, $before, 'Initial salary setup from '.$row['month'].' salary sheet; '.$source['url'], 'Historical payroll import');
                }
            }

            return $runs;
        });
    }

    public static function comparison(object $item): array
    {
        $source = $item->snapshot['historical_source'];
        $c = $item->calculation;
        $values = [
            'Basic' => [$c['basic'], $source['basic']],
            'Fixed allowance' => [collect($c['earnings'])->where('name', 'Fixed allowance')->sum('amount'), $source['package'] - $source['basic']],
            'Commission' => [collect($c['earnings'])->where('name', 'Commission')->sum('amount'), $source['commission']],
            'No pay' => [$c['leave'], $source['no_pay']], 'Gross' => [$c['gross'], $source['package'] + $source['commission'] - $source['no_pay']],
            'Tax' => [$c['apit'], $source['tax']], 'Employee EPF' => [$c['ee'], $source['epf_ee']],
            'Employer EPF' => [$c['er'], $source['epf_er']], 'ETF' => [$c['etf'], $source['etf']],
            'Advance' => [$c['advance'], $source['advance']],
            'Loan' => [collect($c['deductions'])->where('name', 'Employee loan deduction (source)')->sum('amount'), $source['loan']],
            'Net' => [$item->net_cents, $source['net']],
        ];

        return array_map(fn ($pair) => ['system' => $pair[0], 'source' => $pair[1], 'difference' => $pair[0] - $pair[1]], $values);
    }

    private static function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['import' => $message]);
        }
    }
}
