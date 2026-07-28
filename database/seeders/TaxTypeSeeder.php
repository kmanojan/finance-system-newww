<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TaxType;

class TaxTypeSeeder extends Seeder
{
    /**
     * Run the database seeds for Sri Lanka Tax Config.
     */
    public function run(): void
    {
        $taxes = [
            [
                'name' => 'VAT — Standard (18%)',
                'category' => 'vat',
                'rate' => 18.00,
                'effective_from' => '2024-01-01',
                'applies_to' => 'invoice_item',
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'name' => 'VAT — Zero-Rated Export (0%)',
                'category' => 'vat',
                'rate' => 0.00,
                'effective_from' => '2024-01-01',
                'applies_to' => 'invoice_item',
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'WHT — Resident Individual Service (5%)',
                'category' => 'wht',
                'rate' => 5.00,
                'effective_from' => '2023-01-01',
                'applies_to' => 'commission_payment',
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'name' => 'WHT — Rent & Interest (10%)',
                'category' => 'wht',
                'rate' => 10.00,
                'effective_from' => '2023-01-01',
                'applies_to' => 'loan_interest',
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'name' => 'WHT — Dividend Distributions (15%)',
                'category' => 'wht',
                'rate' => 15.00,
                'effective_from' => '2023-01-01',
                'applies_to' => 'other',
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'APIT — Advance Personal Income Tax (Progressive 6%–36%)',
                'category' => 'other',
                'rate' => 36.00,
                'effective_from' => '2023-01-01',
                'applies_to' => 'other',
                'is_default' => false,
                'is_active' => true,
            ],
            [
                'name' => 'CIT — IT/BPO Service Export Concessionary (15%)',
                'category' => 'cit',
                'rate' => 15.00,
                'effective_from' => '2025-04-01',
                'applies_to' => 'other',
                'is_default' => true,
                'is_active' => true,
            ],
            [
                'name' => 'CIT — Corporate Standard (30%)',
                'category' => 'cit',
                'rate' => 30.00,
                'effective_from' => '2023-01-01',
                'applies_to' => 'other',
                'is_default' => false,
                'is_active' => true,
            ],
        ];

        foreach ($taxes as $tax) {
            TaxType::updateOrCreate(
                ['name' => $tax['name']],
                $tax
            );
        }
    }
}
