<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Party extends Model
{
    // Salary records must not be exposed when a general party is serialised.
    protected $hidden = ['employee'];

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

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'party_id');
    }
}
