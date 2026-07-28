<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;
use App\Models\Company;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();

        $accounts = [
            // Assets (1000s)
            ['code' => '1010', 'name' => 'Cash & Bank Accounts', 'type' => 'asset'],
            ['code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset'],
            ['code' => '1500', 'name' => 'Fixed Assets — Computer Equipment', 'type' => 'asset'],
            ['code' => '1800', 'name' => 'Accumulated Depreciation — Equipment', 'type' => 'asset'],

            // Liabilities (2000s)
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability'],
            ['code' => '2200', 'name' => 'Third-Party Loans Payable', 'type' => 'liability'],
            ['code' => '2300', 'name' => 'VAT / Tax Payable', 'type' => 'liability'],

            // Equity (3000s)
            ['code' => '3000', 'name' => 'Share Capital', 'type' => 'equity'],
            ['code' => '3200', 'name' => 'Retained Earnings', 'type' => 'equity'],

            // Revenue (4000s)
            ['code' => '4100', 'name' => 'Software & Consulting Revenue', 'type' => 'revenue'],
            ['code' => '4200', 'name' => 'Export IT Service Income', 'type' => 'revenue'],
            ['code' => '4900', 'name' => 'Realized Foreign Exchange Gain', 'type' => 'revenue'],

            // Expenses (5000s)
            ['code' => '5100', 'name' => 'Salaries & Staff Costs', 'type' => 'expense'],
            ['code' => '5200', 'name' => 'Cloud Hosting & Software Services', 'type' => 'expense'],
            ['code' => '5300', 'name' => 'Office Rent & Utilities', 'type' => 'expense'],
            ['code' => '5800', 'name' => 'Depreciation Expense', 'type' => 'expense'],
        ];

        foreach ($accounts as $acc) {
            Account::updateOrCreate(
                ['code' => $acc['code']],
                array_merge($acc, ['company_id' => $company->id])
            );
        }
    }
}
