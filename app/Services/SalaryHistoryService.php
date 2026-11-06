<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\SalaryComponent;
use Closure;
use Illuminate\Support\Facades\DB;

/** Immutable salary snapshots in the existing append-only activity ledger. */
class SalaryHistoryService
{
    public static function snapshot(Employee $employee): array
    {
        $employee->loadMissing('salaryComponents.component');
        $components = $employee->salaryComponents->filter(fn ($mapping) => $mapping->is_enabled && $mapping->component)
            ->sortBy('salary_component_id')->map(fn ($mapping) => [
                'id' => $mapping->salary_component_id,
                'name' => $mapping->component->name, 'code' => $mapping->component->code,
                'type' => $mapping->component->type, 'value' => round((float) $mapping->value, 2),
                'calculation_type' => $mapping->component->calculation_type,
                'calculation_base' => $mapping->component->calculation_base,
                'is_active' => (bool) $mapping->component->is_active,
                'is_epf_eligible' => (bool) $mapping->component->is_epf_eligible,
                'is_taxable' => (bool) $mapping->component->is_taxable,
                'payroll_input_mode' => $mapping->component->payroll_input_mode,
            ])->values()->all();
        $breakdown = $employee->salary_breakdown;
        $totals = [];
        foreach (['basic_salary', 'total_allowances', 'gross_salary', 'total_deductions', 'net_salary', 'employee_epf', 'apit_tax', 'employer_epf', 'employer_etf', 'ctc'] as $key) {
            $totals[$key] = round((float) ($breakdown[$key] ?? 0), 2);
        }

        return ['currency' => $employee->currency ?: 'LKR', 'totals' => $totals, 'components' => $components];
    }

    public static function record(Employee $employee, array $before, ?string $reason = null, string $source = 'Salary profile'): void
    {
        $after = self::snapshot($employee->fresh());
        if ($before == $after) {
            return;
        }
        ActivityLogService::log('salary_change', 'Employee', $employee->id, $before, $after + [
            'reason' => $reason, 'source' => $source, 'changed_by_name' => auth()->user()?->name ?? 'System',
        ]);
    }

    /** Master definition edits can change salaries without editing the employee. */
    public static function changeComponent(SalaryComponent $component, Closure $change): void
    {
        DB::transaction(function () use ($component, $change) {
            $employees = Employee::whereHas('salaryComponents', fn ($q) => $q->where('salary_component_id', $component->id)->where('is_enabled', true))
                ->with('salaryComponents.component')->orderBy('id')->lockForUpdate()->get();
            $before = $employees->mapWithKeys(fn ($employee) => [$employee->id => self::snapshot($employee)]);
            $change();
            $employees->load('salaryComponents.component');
            $entries = [];
            foreach ($employees as $employee) {
                $after = self::snapshot($employee);
                if ($before[$employee->id] != $after) {
                    $entries[] = ['action' => 'salary_change', 'model_type' => 'Employee', 'model_id' => $employee->id,
                        'old_value' => $before[$employee->id], 'new_value' => $after + [
                            'reason' => 'Salary component definition changed: '.$component->name,
                            'source' => 'Component master', 'changed_by_name' => auth()->user()?->name ?? 'System',
                        ]];
                }
            }
            ActivityLogService::logBatch($entries);
        });
    }

    public static function query(int $employeeId)
    {
        return DB::table('activity_logs')->where('model_type', 'Employee')->where('model_id', $employeeId)->where('action', 'salary_change');
    }

    public static function present(object $record): array
    {
        $before = json_decode($record->old_value, true);
        $after = json_decode($record->new_value, true);
        $changes = [];
        if ($before['currency'] !== $after['currency']) {
            $changes[] = ['name' => 'Currency', 'before' => $before['currency'], 'after' => $after['currency']];
        }
        if ($before['totals']['basic_salary'] != $after['totals']['basic_salary']) {
            $changes[] = ['name' => 'Basic salary', 'before' => $before['currency'].' '.number_format($before['totals']['basic_salary'], 2), 'after' => $after['currency'].' '.number_format($after['totals']['basic_salary'], 2)];
        }
        $old = collect($before['components'])->keyBy('id');
        $new = collect($after['components'])->keyBy('id');
        $describe = fn ($component, $currency) => ! $component ? 'Not assigned' : implode(' · ', [
            $component['name'].' ('.$component['code'].')',
            $component['calculation_type'] === 'percentage' ? number_format($component['value'], 2).'% '.str_replace('_', ' ', $component['calculation_base']) : $currency.' '.number_format($component['value'], 2),
            str_replace('_', ' ', $component['type']), $component['is_active'] ? 'Active' : 'Inactive',
            $component['is_epf_eligible'] ? 'EPF eligible' : 'Not EPF eligible',
            ! empty($component['is_taxable']) ? 'Taxable' : 'Non-taxable',
            $component['payroll_input_mode'] === 'monthly_amount' ? 'Monthly payroll input' : 'Profile amount',
        ]);
        foreach ($old->keys()->merge($new->keys())->unique() as $id) {
            if ($old->get($id) != $new->get($id)) {
                $changes[] = ['name' => ($new->get($id) ?? $old->get($id))['name'], 'before' => $describe($old->get($id), $before['currency']), 'after' => $describe($new->get($id), $after['currency'])];
            }
        }

        return ['id' => $record->id, 'recorded_at' => $record->created_at, 'before' => $before, 'after' => $after, 'changes' => $changes];
    }
}
