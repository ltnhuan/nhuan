<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiEndpoint extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'system_id', 'code', 'name', 'method', 'path', 'full_url', 'module', 'purpose', 'request_schema', 'response_schema', 'timeout_ms', 'retry_policy', 'rate_limit', 'permission_key', 'status'];

    protected $casts = [
        'request_schema' => 'array',
        'response_schema' => 'array',
        'retry_policy' => 'array',
        'rate_limit' => 'array',
    ];

    public function system()
    {
        return $this->belongsTo(ApiSystem::class, 'system_id');
    }
}
