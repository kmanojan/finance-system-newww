<?php

namespace Database\Seeders;

use App\Models\SalaryComponent;
use Illuminate\Database\Seeder;

class SalaryComponentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $components = [
            [
                'name'             => 'Basic Salary',
                'code'             => 'BASIC',
                'type'             => 'earning',
                'calculation_type' => 'fixed_amount',
                'calculation_base' => 'on_basic',
                'default_value'    => 0.00,
                'is_epf_eligible'  => true,
                'is_taxable'       => true,
                'is_statutory'     => true,
                'sort_order'       => 1,
                'description'      => 'Core baseline contracted monthly salary. Primary base for EPF, ETF, and APIT calculations.',
            ],
            [
                'name'             => 'Budgetary / Fixed Allowance',
                'code'             => 'FIXED_ALLOW',
                'type'             => 'earning',
                'calculation_type' => 'fixed_amount',
                'calculation_base' => 'on_basic',
                'default_value'    => 0.00,
                'is_epf_eligible'  => true, // In Sri Lanka, statutory budgetary relief allowance is EPF-eligible
                'is_taxable'       => true,
                'is_statutory'     => false,
                'sort_order'       => 2,
                'description'      => 'Fixed monthly allowance considered part of total earnings for EPF/ETF contributions.',
            ],
            [
                'name'             => 'Transport / Travel Allowance',
                'code'             => 'TRAVEL_ALLOW',
                'type'             => 'earning',
                'calculation_type' => 'fixed_amount',
                'calculation_base' => 'on_basic',
                'default_value'    => 0.00,
                'is_epf_eligible'  => false,
                'is_taxable'       => true,
                'is_statutory'     => false,
                'sort_order'       => 3,
                'description'      => 'Monthly reimbursement or travel allowance. Exempt from EPF/ETF calculations.',
            ],
            [
                'name'             => 'Performance / Attendance Allowance',
                'code'             => 'PERF_ALLOW',
                'type'             => 'earning',
                'calculation_type' => 'fixed_amount',
                'calculation_base' => 'on_basic',
                'default_value'    => 0.00,
                'is_epf_eligible'  => false,
                'is_taxable'       => true,
                'is_statutory'     => false,
                'sort_order'       => 4,
                'description'      => 'Discretionary or performance incentive paid monthly.',
            ],
            [
                'name'             => 'Employee EPF (8%)',
                'code'             => 'EPF_EE',
                'type'             => 'deduction',
                'calculation_type' => 'percentage',
                'calculation_base' => 'on_epf_base',
                'default_value'    => 8.00,
                'is_epf_eligible'  => false,
                'is_taxable'       => false,
                'is_statutory'     => true,
                'sort_order'       => 10,
                'description'      => 'Statutory 8% employee contribution deducted from salary towards the Employees\' Provident Fund.',
            ],
            [
                'name'             => 'Advance Personal Income Tax (APIT)',
                'code'             => 'APIT',
                'type'             => 'deduction',
                'calculation_type' => 'fixed_amount',
                'calculation_base' => 'custom',
                'default_value'    => 0.00,
                'is_epf_eligible'  => false,
                'is_taxable'       => false,
                'is_statutory'     => true,
                'sort_order'       => 11,
                'description'      => 'Monthly statutory tax withheld at source under Sri Lanka Inland Revenue APIT rules.',
            ],
            [
                'name'             => 'Salary Advance / Loan Recovery',
                'code'             => 'SALARY_ADVANCE',
                'type'             => 'deduction',
                'calculation_type' => 'fixed_amount',
                'calculation_base' => 'custom',
                'default_value'    => 0.00,
                'is_epf_eligible'  => false,
                'is_taxable'       => false,
                'is_statutory'     => false,
                'sort_order'       => 12,
                'description'      => 'Periodic recovery for cash advances or personal loans given to the employee.',
            ],
            [
                'name'             => 'Employer EPF (12%)',
                'code'             => 'EPF_ER',
                'type'             => 'employer_contribution',
                'calculation_type' => 'percentage',
                'calculation_base' => 'on_epf_base',
                'default_value'    => 12.00,
                'is_epf_eligible'  => false,
                'is_taxable'       => false,
                'is_statutory'     => true,
                'sort_order'       => 20,
                'description'      => 'Statutory 12% employer contribution towards the Employees\' Provident Fund (Company Cost).',
            ],
            [
                'name'             => 'Employer ETF (3%)',
                'code'             => 'ETF_ER',
                'type'             => 'employer_contribution',
                'calculation_type' => 'percentage',
                'calculation_base' => 'on_epf_base',
                'default_value'    => 3.00,
                'is_epf_eligible'  => false,
                'is_taxable'       => false,
                'is_statutory'     => true,
                'sort_order'       => 21,
                'description'      => 'Statutory 3% employer contribution towards the Employees\' Trust Fund (Company Cost).',
            ],
        ];

        foreach ($components as $comp) {
            SalaryComponent::updateOrCreate(
                ['code' => $comp['code']],
                $comp
            );
        }
    }
}
