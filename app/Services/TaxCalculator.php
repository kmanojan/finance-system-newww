<?php

namespace App\Services;

use App\Models\TaxType;

class TaxCalculator
{
    /**
     * Calculate VAT for an invoice line item or amount.
     */
    public static function vat(float $amount, ?int $taxTypeId = null, ?string $date = null): array
    {
        $taxType = null;
        if ($taxTypeId) {
            $taxType = TaxType::find($taxTypeId);
        } else {
            $taxType = TaxType::active()
                ->effectiveOn($date)
                ->where('category', 'vat')
                ->where('applies_to', 'invoice_item')
                ->where('is_default', true)
                ->first();
        }

        $rate = $taxType ? (float) $taxType->rate : 0.0;
        $taxAmount = round($amount * ($rate / 100), 2);
        $totalAmount = round($amount + $taxAmount, 2);

        return [
            'tax_type_id' => $taxType?->id,
            'tax_name' => $taxType?->name ?? 'No Tax',
            'rate' => $rate,
            'subtotal' => $amount,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Calculate WHT for payments (commission, rent, loan interest).
     */
    public static function wht(float $amount, ?int $whtTypeId = null, ?string $appliesTo = 'commission_payment', ?string $date = null): array
    {
        $taxType = null;
        if ($whtTypeId) {
            $taxType = TaxType::find($whtTypeId);
        } else {
            $taxType = TaxType::active()
                ->effectiveOn($date)
                ->where('category', 'wht')
                ->where('applies_to', $appliesTo)
                ->where('is_default', true)
                ->first();
        }

        $rate = $taxType ? (float) $taxType->rate : 0.0;
        $whtAmount = round($amount * ($rate / 100), 2);
        $netPaid = round($amount - $whtAmount, 2);

        return [
            'wht_type_id' => $taxType?->id,
            'wht_name' => $taxType?->name ?? 'No WHT',
            'rate' => $rate,
            'gross_amount' => $amount,
            'wht_amount' => $whtAmount,
            'net_paid' => $netPaid,
        ];
    }

    /**
     * Calculate APIT (Advance Personal Income Tax) for monthly salary (Sri Lanka progressive tax bands).
     * Tax Free Relief: LKR 150,000 / month (LKR 1,800,000 / year).
     * Progressive bands above relief (in monthly slabs of LKR 41,667): 6%, 12%, 18%, 24%, 30%, 36%.
     */
    public static function apit(float $monthlySalary): array
    {
        $taxable = max(0, $monthlySalary - 150000); // LKR 150,000 monthly relief
        $tax = 0.0;
        $slabSize = 41666.67; // LKR 500,000 / 12 months
        $rates = [0.06, 0.12, 0.18, 0.24, 0.30, 0.36];

        $remaining = $taxable;
        $breakdown = [];

        foreach ($rates as $index => $rate) {
            if ($remaining <= 0) break;

            $isLastBand = ($index === count($rates) - 1);
            $taxableInSlab = $isLastBand ? $remaining : min($remaining, $slabSize);
            $slabTax = round($taxableInSlab * $rate, 2);

            $tax += $slabTax;
            $breakdown[] = [
                'band_rate' => ($rate * 100) . '%',
                'taxable_amount' => round($taxableInSlab, 2),
                'tax_amount' => $slabTax,
            ];

            $remaining -= $taxableInSlab;
        }

        $effectiveRate = $monthlySalary > 0 ? round(($tax / $monthlySalary) * 100, 2) : 0.0;

        return [
            'gross_salary' => $monthlySalary,
            'personal_relief' => 150000.0,
            'taxable_salary' => round($taxable, 2),
            'apit_tax_amount' => round($tax, 2),
            'net_salary' => round($monthlySalary - $tax, 2),
            'effective_rate' => $effectiveRate,
            'breakdown' => $breakdown,
        ];
    }
}
