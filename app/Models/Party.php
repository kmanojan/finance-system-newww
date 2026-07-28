<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
