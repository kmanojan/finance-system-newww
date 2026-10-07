<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'employee_code',
        'first_name',
        'last_name',
        'full_name',
        'personal_email',
        'mobile_phone',
        'profile_picture_url',
        'status',
        'user_type',
        'job_position',
        'role',
        'basic_salary',
        'currency',
        'payment_mode',
        'bank_name',
        'bank_account_no',
        'bank_branch',
        'epf_number',
        'synced_at',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'synced_at'    => 'datetime',
    ];

    public function costAllocations()
    {
        return $this->hasMany(CostAllocation::class);
    }

    public function salaryComponents()
    {
        return $this->hasMany(EmployeeSalaryComponent::class)->with('component');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Compute full comprehensive salary breakdown:
     * Gross Salary, EPF Base, Deductions, Net Pay, Employer EPF/ETF, CTC
     */
    public function calculateSalaryBreakdown(?array $customComponentOverrides = null): array
    {
        $basicSalary = (float) ($this->basic_salary ?? 0.00);

        // Load active components with their master definitions
        $mappings = $this->relationLoaded('salaryComponents')
            ? $this->salaryComponents
            : $this->salaryComponents()->with('component')->get();

        $activeEarnings = [];
        $activeDeductions = [];
        $activeEmployerContributions = [];

        $totalAllowances = 0.0;
        $epfEligibleAllowances = 0.0;

        // Process earnings first to determine gross salary & EPF base
        foreach ($mappings as $mapping) {
            $comp = $mapping->component;
            if (!$comp || !$comp->is_active || !$mapping->is_enabled) {
                continue;
            }

            if ($comp->type === 'earning') {
                $val = (float) $mapping->value;
                $amount = $val; // For earnings, usually fixed amount
                $activeEarnings[] = [
                    'id'               => $comp->id,
                    'code'             => $comp->code,
                    'name'             => $comp->name,
                    'calculation_type' => $comp->calculation_type,
                    'value'            => $val,
                    'amount'           => $amount,
                    'is_epf_eligible'  => (bool) $comp->is_epf_eligible,
                ];

                $totalAllowances += $amount;
                if ($comp->is_epf_eligible) {
                    $epfEligibleAllowances += $amount;
                }
            }
        }

        $grossSalary = $basicSalary + $totalAllowances;
        $epfBaseSalary = $basicSalary + $epfEligibleAllowances;

        $totalDeductions = 0.0;
        $employeeEpf = 0.0;
        $apitTax = 0.0;

        // Process deductions
        foreach ($mappings as $mapping) {
            $comp = $mapping->component;
            if (!$comp || !$comp->is_active || !$mapping->is_enabled) {
                continue;
            }

            if ($comp->type === 'deduction') {
                $val = (float) $mapping->value;
                $amount = $comp->calculateAmount($val, $basicSalary, $epfBaseSalary, $grossSalary);

                $activeDeductions[] = [
                    'id'               => $comp->id,
                    'code'             => $comp->code,
                    'name'             => $comp->name,
                    'calculation_type' => $comp->calculation_type,
                    'calculation_base' => $comp->calculation_base,
                    'value'            => $val,
                    'amount'           => $amount,
                ];

                $totalDeductions += $amount;

                if ($comp->code === 'EPF_EE') {
                    $employeeEpf = $amount;
                } elseif ($comp->code === 'APIT') {
                    $apitTax = $amount;
                }
            }
        }

        $netSalary = max(0, $grossSalary - $totalDeductions);

        $totalEmployerCost = 0.0;
        $employerEpf = 0.0;
        $employerEtf = 0.0;

        // Process employer contributions (EPF 12%, ETF 3%)
        foreach ($mappings as $mapping) {
            $comp = $mapping->component;
            if (!$comp || !$comp->is_active || !$mapping->is_enabled) {
                continue;
            }

            if ($comp->type === 'employer_contribution') {
                $val = (float) $mapping->value;
                $amount = $comp->calculateAmount($val, $basicSalary, $epfBaseSalary, $grossSalary);

                $activeEmployerContributions[] = [
                    'id'               => $comp->id,
                    'code'             => $comp->code,
                    'name'             => $comp->name,
                    'calculation_type' => $comp->calculation_type,
                    'calculation_base' => $comp->calculation_base,
                    'value'            => $val,
                    'amount'           => $amount,
                ];

                $totalEmployerCost += $amount;

                if ($comp->code === 'EPF_ER') {
                    $employerEpf = $amount;
                } elseif ($comp->code === 'ETF_ER') {
                    $employerEtf = $amount;
                }
            }
        }

        $ctc = $grossSalary + $totalEmployerCost;

        return [
            'currency'               => $this->currency ?? 'LKR',
            'basic_salary'           => $basicSalary,
            'earnings'               => $activeEarnings,
            'total_allowances'       => $totalAllowances,
            'gross_salary'           => $grossSalary,
            'epf_base_salary'        => $epfBaseSalary,
            'deductions'             => $activeDeductions,
            'employee_epf'           => $employeeEpf,
            'apit_tax'               => $apitTax,
            'total_deductions'       => $totalDeductions,
            'net_salary'             => $netSalary,
            'employer_contributions' => $activeEmployerContributions,
            'employer_epf'           => $employerEpf,
            'employer_etf'           => $employerEtf,
            'total_employer_cost'    => $totalEmployerCost,
            'ctc'                    => $ctc,
        ];
    }

    /**
     * Accessor for $employee->salary_breakdown
     */
    public function getSalaryBreakdownAttribute(): array
    {
        return $this->calculateSalaryBreakdown();
    }
}
