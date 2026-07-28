<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CostAllocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id', 'type', 'employee_id', 'server_id',
        'cost_center_name', 'period_start', 'period_end',
        'amount', 'currency', 'source', 'notes', 'created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'amount'       => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
