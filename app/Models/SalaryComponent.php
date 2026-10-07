<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryComponent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'type',             // 'earning', 'deduction', 'employer_contribution'
        'calculation_type', // 'fixed_amount', 'percentage'
        'calculation_base', // 'on_basic', 'on_epf_base', 'on_gross', 'custom'
        'default_value',
        'is_epf_eligible',
        'is_taxable',
        'is_statutory',
        'is_active',
        'sort_order',
        'description',
    ];

    protected $casts = [
        'default_value'    => 'decimal:2',
        'is_epf_eligible'  => 'boolean',
        'is_taxable'       => 'boolean',
        'is_statutory'     => 'boolean',
        'is_active'        => 'boolean',
        'sort_order'       => 'integer',
    ];

    public function employeeComponents()
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeEarnings($query)
    {
        return $query->where('type', 'earning');
    }

    public function scopeDeductions($query)
    {
        return $query->where('type', 'deduction');
    }

    public function scopeEmployerContributions($query)
    {
        return $query->where('type', 'employer_contribution');
    }

    /**
     * Compute component value for given basic salary and epf base / gross.
     */
    public function calculateAmount(float $value, float $basicSalary, float $epfBase, float $grossEarnings): float
    {
        if ($this->calculation_type === 'fixed_amount') {
            return round($value, 2);
        }

        // Percentage based calculation
        $base = match ($this->calculation_base) {
            'on_basic'    => $basicSalary,
            'on_epf_base' => $epfBase,
            'on_gross'    => $grossEarnings,
            default       => $basicSalary,
        };

        return round($base * ($value / 100), 2);
    }
}
