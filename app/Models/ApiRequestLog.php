<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiRequestLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'api_requests';

    protected $fillable = ['tenant_id', 'system_id', 'endpoint_id', 'request_uuid', 'direction', 'method', 'url', 'status_code', 'duration_ms', 'request_headers', 'request_body', 'response_headers', 'response_body', 'error_message', 'actor_id', 'ip_address', 'user_agent', 'created_at'];

    protected $casts = [
        'request_headers' => 'array',
        'request_body' => 'array',
        'response_headers' => 'array',
        'response_body' => 'array',
        'created_at' => 'datetime',
    ];

    public function endpoint()
    {
        return $this->belongsTo(ApiEndpoint::class, 'endpoint_id');
    }
}
