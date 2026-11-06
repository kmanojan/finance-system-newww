<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Validation\ValidationException;

class PayrollCalculationService
{
    public static function cents($amount): int
    {
        return (int) round((float) $amount * 100);
    }

    public static function snapshot(Employee $employee): array
    {
        return [
            'name' => $employee->full_name, 'code' => $employee->employee_code,
            'basic' => self::cents($employee->basic_salary), 'currency' => $employee->currency,
            'bank' => $employee->bank_name, 'account' => $employee->bank_account_no,
            'branch' => $employee->bank_branch, 'payment_mode' => $employee->payment_mode,
            'epf_number' => $employee->epf_number, 'captured_at' => now()->toIso8601String(),
            'components' => $employee->salaryComponents->filter(fn ($m) => $m->is_enabled && $m->component?->is_active && $m->component->code !== 'BASIC')->map(fn ($m) => [
                'id' => $m->component->id, 'payroll_input_mode' => $m->component->payroll_input_mode ?? 'display',
                'code' => $m->component->code, 'name' => $m->component->name,
                'type' => $m->component->type, 'method' => $m->component->calculation_type,
                'base' => $m->component->calculation_base, 'value' => (float) $m->value,
                'epf_eligible' => (bool) $m->component->is_epf_eligible,
                'taxable' => (bool) $m->component->is_taxable,
            ])->values()->all(),
        ];
    }

    public static function calculate(array $s, array $a = []): array
    {
        $s = self::applyMonthlyAmounts($s, $a['component_amounts'] ?? []);
        $basic = $s['basic'];
        $earnings = [];
        $gross = $epfBase = $taxable = $basic;
        foreach ($s['components'] as $c) {
            if ($c['type'] !== 'earning') {
                continue;
            }
            $amount = self::component($c, $basic, $basic, $basic);
            $earnings[] = ['id' => $c['id'] ?? null, 'name' => $c['name'], 'amount' => $amount];
            $gross += $amount;
            if ($c['epf_eligible']) {
                $epfBase += $amount;
            }
            if ($c['taxable']) {
                $taxable += $amount;
            }
        }
        foreach (['overtime' => 'Overtime', 'bonus' => 'Bonus', 'allowance' => 'Additional allowance'] as $key => $name) {
            $amount = self::cents($a[$key] ?? 0);
            if ($amount) {
                $earnings[] = compact('name', 'amount');
            }
            $gross += $amount;
            $taxable += $amount;
        }
        $leave = self::cents($a['unpaid_leave'] ?? 0);
        $gross -= $leave;
        $taxable = max(0, $taxable - $leave);
        // Bases are independently reviewed; ETF is not assumed to share EPF eligibility.
        $epfBase = isset($a['epf_base']) ? self::cents($a['epf_base']) : max(0, $epfBase - $leave);
        $etfBase = isset($a['etf_base']) ? self::cents($a['etf_base']) : $epfBase;
        $ee = $er = $etf = $apit = $other = $employerOther = 0;
        $deductions = $employer = [];
        foreach ($s['components'] as $c) {
            if ($c['type'] === 'earning') {
                continue;
            }
            if ($c['code'] === 'SALARY_ADVANCE') {
                continue;
            } // Actual tracked recoveries only.
            $amount = self::component($c, $basic, $c['code'] === 'ETF_ER' ? $etfBase : $epfBase, $gross);
            if ($c['code'] === 'APIT') {
                $amount = self::cents($a['apit'] ?? ($amount / 100));
            }
            if ($c['type'] === 'deduction') {
                if ($c['code'] === 'EPF_EE') {
                    $ee += $amount;
                } elseif ($c['code'] === 'APIT') {
                    $apit += $amount;
                } else {
                    $other += $amount;
                }
                $deductions[] = ['id' => $c['id'] ?? null, 'name' => $c['name'], 'amount' => $amount];
            } else {
                if ($c['code'] === 'EPF_ER') {
                    $er += $amount;
                } elseif ($c['code'] === 'ETF_ER') {
                    $etf += $amount;
                } else {
                    $employerOther += $amount;
                }
                $employer[] = ['id' => $c['id'] ?? null, 'name' => $c['name'], 'amount' => $amount];
            }
        }
        if (! collect($s['components'])->contains('code', 'APIT')) {
            $apit = self::cents($a['apit'] ?? 0);
            $deductions[] = ['name' => 'APIT (reviewed)', 'amount' => $apit];
        }
        $extra = self::cents($a['other_deduction'] ?? 0);
        $advance = self::cents($a['advance'] ?? 0);
        if ($extra) {
            $deductions[] = ['name' => 'Other deduction', 'amount' => $extra];
        }
        if ($advance) {
            $deductions[] = ['name' => 'Advance recovery', 'amount' => $advance];
        }
        $other += $extra;
        $net = $gross - $ee - $apit - $other - $advance;
        if ($gross < 0 || $net < 0) {
            throw ValidationException::withMessages(['adjustments' => 'Deductions cannot exceed earnings. Check unpaid leave, tax and advances.']);
        }

        return compact('basic', 'gross', 'net', 'earnings', 'deductions', 'employer', 'ee', 'er', 'etf', 'apit', 'other', 'employerOther', 'advance', 'epfBase', 'etfBase', 'taxable', 'leave') + [
            'epf' => $ee + $er, 'cost' => $gross + $er + $etf + $employerOther,
            'other_payable' => $other + $employerOther,
        ];
    }

    private static function applyMonthlyAmounts(array $snapshot, array $amounts): array
    {
        $allowed = collect($snapshot['components'])->filter(fn ($c) => ($c['payroll_input_mode'] ?? 'display') === 'monthly_amount'
            && ! in_array($c['code'], ['BASIC', 'EPF_EE', 'EPF_ER', 'ETF_ER', 'APIT', 'SALARY_ADVANCE']))->pluck('id')->all();
        foreach ($amounts as $id => $value) {
            if (! in_array((string) $id, array_map('strval', $allowed), true)) {
                throw ValidationException::withMessages(['component_amounts' => 'Only assigned components configured for monthly entry can be changed. Refresh the employee list if a salary profile changed.']);
            }
        }
        foreach ($snapshot['components'] as &$component) {
            if (in_array($component['id'] ?? null, $allowed, true)) {
                $component['value'] = $amounts[$component['id']] ?? 0;
                validator(['amount' => $component['value']], ['amount' => 'required|numeric|min:0|max:999999999.99|decimal:0,2'])->validate();
                // Monthly entry is a final currency amount, never a percentage or a recurring default.
                $component['method'] = 'fixed_amount';
            }
        }
        unset($component);

        return $snapshot;
    }

    private static function component(array $c, int $basic, int $epf, int $gross): int
    {
        $base = match ($c['base']) {
            'on_epf_base' => $epf, 'on_gross' => $gross, default => $basic
        };

        return $c['method'] === 'percentage' ? (int) round($base * $c['value'] / 100) : self::cents($c['value']);
    }
}
