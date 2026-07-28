<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'category',
        'rate',
        'effective_from',
        'effective_to',
        'applies_to',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'rate' => 'float',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    /**
     * Scope active tax types.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope tax types effective on a specific date.
     */
    public function scopeEffectiveOn($query, $date = null)
    {
        $date = $date ? date('Y-m-d', strtotime($date)) : date('Y-m-d');
        return $query->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $date);
            });
    }
}
