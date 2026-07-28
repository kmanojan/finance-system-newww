<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'po_number',
        'vendor_id',
        'department_id',
        'status',
        'total_amount',
        'currency',
        'issue_date',
    ];

    protected $casts = [
        'total_amount' => 'float',
        'issue_date' => 'date',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'vendor_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(VendorBill::class, 'po_id');
    }
}
