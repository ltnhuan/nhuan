<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiHealthSnapshot extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;
    public const CREATED_AT = null;

    protected $fillable = ['tenant_id', 'system_id', 'endpoint_id', 'status', 'latency_ms', 'success_rate', 'error_rate', 'checked_at', 'metadata'];

    protected $casts = [
        'checked_at' => 'datetime',
        'metadata' => 'array',
        'success_rate' => 'float',
        'error_rate' => 'float',
    ];
}
