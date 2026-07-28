<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiIntegration extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'url', 'method', 'bearer_token', 'response_path', 'last_synced_at', 'last_sync_status', 'last_sync_error'];

    protected $casts = [
        'bearer_token' => 'encrypted',
        'last_synced_at' => 'datetime',
    ];
}
