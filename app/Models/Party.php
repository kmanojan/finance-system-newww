<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Party extends Model
{
    protected $fillable = [
        'name',
        'types',
        'contact_person',
        'email',
        'phone',
        'address',
        'tax_id',
        'status',
    ];

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'party_id');
    }
}
