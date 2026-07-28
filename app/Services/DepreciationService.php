<?php

namespace App\Services;

use App\Models\FixedAsset;

class DepreciationService
{
    /**
     * Calculate and post monthly depreciation for an asset.
     */
    public static function processMonthlyDepreciation(FixedAsset $asset, string $postingDate): float
    {
        if ($asset->status !== 'active') {
            return 0.0;
        }

        $depreciableBase = $asset->purchase_cost - $asset->salvage_value;

        if ($asset->accumulated_depreciation >= $depreciableBase) {
            $asset->update(['status' => 'fully_depreciated']);
            return 0.0;
        }

        $monthlyAmount = 0.0;

        if ($asset->depreciation_method === 'straight_line') {
            $totalMonths = max(1, $asset->lifespan_years * 12);
            $monthlyAmount = round($depreciableBase / $totalMonths, 2);
        } else {
            // Reducing balance method (assumes 20% annual rate)
            $bookValue = $asset->purchase_cost - $asset->accumulated_depreciation;
            $monthlyAmount = round(($bookValue * 0.20) / 12, 2);
        }

        // Cap to remaining depreciable base
        $remaining = $depreciableBase - $asset->accumulated_depreciation;
        if ($monthlyAmount > $remaining) {
            $monthlyAmount = $remaining;
        }

        $newAccumulated = round($asset->accumulated_depreciation + $monthlyAmount, 2);
        $asset->update([
            'accumulated_depreciation' => $newAccumulated,
            'status' => $newAccumulated >= $depreciableBase ? 'fully_depreciated' : 'active',
        ]);

        // Post Double-Entry Journal:
        // Debit: Depreciation Expense (5800), Credit: Accumulated Depreciation (1800)
        LedgerPostingService::postJournal(
            $postingDate,
            "Monthly Depreciation for Asset {$asset->asset_code} — {$asset->asset_name}",
            [
                ['account_code' => '5800', 'debit' => $monthlyAmount, 'credit' => 0, 'currency' => 'LKR'],
                ['account_code' => '1800', 'debit' => 0, 'credit' => $monthlyAmount, 'currency' => 'LKR'],
            ],
            $asset->id,
            FixedAsset::class
        );

        return $monthlyAmount;
    }
}
