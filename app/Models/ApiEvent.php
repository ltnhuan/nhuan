<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiEvent extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'event_key', 'source_system_id', 'target_system_id', 'entity_type', 'entity_id', 'idempotency_key', 'payload', 'status', 'attempts', 'error_message', 'next_retry_at', 'processed_at'];

    protected $casts = [
        'payload' => 'array',
        'next_retry_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
