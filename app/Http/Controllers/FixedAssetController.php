<?php

namespace App\Http\Controllers;

use App\Models\FixedAsset;
use App\Services\DepreciationService;
use App\Services\PeriodLockService;
use Illuminate\Http\Request;

class FixedAssetController extends Controller
{
    public function index()
    {
        $assets = FixedAsset::orderBy('asset_code')->get();
        return response()->json(['success' => true, 'data' => $assets]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_name' => 'required|string|max:255',
            'asset_code' => 'required|string|unique:fixed_assets,asset_code',
            'category' => 'required|string|max:100',
            'purchase_date' => 'required|date',
            'purchase_cost' => 'required|numeric|min:0',
            'salvage_value' => 'nullable|numeric|min:0',
            'lifespan_years' => 'required|integer|min:1',
            'depreciation_method' => 'required|in:straight_line,reducing_balance',
        ]);

        PeriodLockService::checkLockedDate($validated['purchase_date']);

        $asset = FixedAsset::create(array_merge($validated, [
            'salvage_value' => $validated['salvage_value'] ?? 0.00,
        ]));

        return response()->json(['success' => true, 'message' => 'Fixed asset registered.', 'data' => $asset], 201);
    }

    public function runDepreciation(Request $request, $id)
    {
        $asset = FixedAsset::findOrFail($id);
        $postingDate = $request->input('posting_date', date('Y-m-d'));

        $amount = DepreciationService::processMonthlyDepreciation($asset, $postingDate);

        return response()->json([
            'success' => true,
            'message' => "Depreciation of LKR {$amount} posted for asset {$asset->asset_code}.",
            'data' => $asset->fresh(),
        ]);
    }
}
