<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_no',
        'client_id',
        'project_id',
        'department_id',
        'template_id',
        'schedule_id',
        'tax_type_id',
        'tax_rate',
        'tax_amount',
        'amount',
        'subtotal',
        'advance_paid',
        'grand_total',
        'currency',
        'status',
        'signee_name',
        'signee_title',
        'signature_image',
        'template_snapshot',
        'due_date',
        'issue_date',
    ];

    protected $casts = [
        'amount' => 'float',
        'subtotal' => 'float',
        'advance_paid' => 'float',
        'grand_total' => 'float',
        'tax_rate' => 'float',
        'tax_amount' => 'float',
        'issue_date' => 'date',
        'due_date' => 'date',
        'template_snapshot' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'client_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function taxType(): BelongsTo
    {
        return $this->belongsTo(TaxType::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
