<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FixedAsset extends Model
{
    protected $fillable = [
        'asset_name',
        'asset_code',
        'category',
        'purchase_date',
        'purchase_cost',
        'salvage_value',
        'lifespan_years',
        'depreciation_method',
        'accumulated_depreciation',
        'status',
    ];

    protected $casts = [
        'purchase_cost' => 'float',
        'salvage_value' => 'float',
        'accumulated_depreciation' => 'float',
        'purchase_date' => 'date',
    ];
}
