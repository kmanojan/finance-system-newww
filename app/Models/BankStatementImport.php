<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementImport extends Model
{
    protected $fillable = [
        'bank_account_id',
        'statement_date',
        'reference_no',
        'amount',
        'type',
        'description',
        'is_matched',
        'matched_transaction_id',
    ];

    protected $casts = [
        'amount' => 'float',
        'statement_date' => 'date',
        'is_matched' => 'boolean',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function matchedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'matched_transaction_id');
    }
}
