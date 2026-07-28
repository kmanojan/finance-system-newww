<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FinancialYear;
use App\Models\FiscalPeriod;
use App\Models\Company;

class FinancialYearSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (!$company) {
            $company = Company::create([
                'name' => 'Apptimus Tech',
                'base_currency' => 'LKR',
            ]);
        }

        $fy = FinancialYear::firstOrCreate(
            ['title' => 'FY 2025/2026', 'company_id' => $company->id],
            [
                'start_date' => '2025-04-01',
                'end_date' => '2026-03-31',
                'is_closed' => false,
            ]
        );

        if ($fy->periods()->count() === 0) {
            $start = new \DateTime('2025-04-01');
            for ($i = 0; $i < 12; $i++) {
                $periodStart = clone $start;
                $periodEnd = (clone $start)->modify('last day of this month');

                FiscalPeriod::create([
                    'financial_year_id' => $fy->id,
                    'period_name' => $periodStart->format('F Y'),
                    'start_date' => $periodStart->format('Y-m-d'),
                    'end_date' => $periodEnd->format('Y-m-d'),
                    'status' => $i < 3 ? 'hard_closed' : 'open', // first quarter hard closed as example
                ]);

                $start->modify('first day of next month');
            }
        }
    }
}
