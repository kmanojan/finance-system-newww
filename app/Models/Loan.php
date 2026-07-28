<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    protected $fillable = [
        'party_id',
        'lender_name',
        'principal_amount',
        'currency',
        'purpose',
        'claimed_date',
        'term_months',
        'interest_method',
        'interest_amount',
        'interest_rate',
        'rate_basis',
        'total_interest',
        'due_day',
        'frequency',
        'guarantor',
        'collateral',
        'status',
    ];

    protected $casts = [
        'principal_amount' => 'float',
        'interest_amount' => 'float',
        'interest_rate' => 'float',
        'total_interest' => 'float',
        'claimed_date' => 'date',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function interestSchedules(): HasMany
    {
        return $this->hasMany(FiscalPeriod::class, 'loan_id'); // Or LoanInterestSchedule
    }
}
