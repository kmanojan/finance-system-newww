<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id', 'employee_code', 'first_name', 'last_name', 'full_name',
        'personal_email', 'mobile_phone', 'profile_picture_url',
        'status', 'user_type', 'job_position', 'role', 'synced_at',
    ];

    public function costAllocations()
    {
        return $this->hasMany(CostAllocation::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
