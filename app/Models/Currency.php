<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'is_active',
        'is_base',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_base' => 'boolean',
    ];

    public static function seedDefaultCurrencies()
    {
        $defaults = [
            ['code' => 'LKR', 'name' => 'Sri Lankan Rupee', 'symbol' => 'Rs', 'is_base' => 1, 'is_active' => 1],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'is_base' => 0, 'is_active' => 1],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'is_base' => 0, 'is_active' => 1],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$', 'is_base' => 0, 'is_active' => 1],
            ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'AED', 'is_base' => 0, 'is_active' => 1],
            ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹', 'is_base' => 0, 'is_active' => 1],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'is_base' => 0, 'is_active' => 1],
            ['code' => 'CAD', 'name' => 'Canadian Dollar', 'symbol' => 'C$', 'is_base' => 0, 'is_active' => 1],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$', 'is_base' => 0, 'is_active' => 1],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥', 'is_base' => 0, 'is_active' => 1],
        ];

        foreach ($defaults as $curr) {
            \Illuminate\Support\Facades\DB::table('currencies')->updateOrInsert(
                ['code' => $curr['code']],
                [
                    'name' => $curr['name'],
                    'symbol' => $curr['symbol'],
                    'is_active' => $curr['is_active'],
                    'is_base' => $curr['is_base'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
