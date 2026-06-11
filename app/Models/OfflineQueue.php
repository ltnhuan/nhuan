<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfflineQueue extends Model
{
    protected $table = 'offline_queue';

    protected $fillable = [
        'tenant_id', 'user_id', 'client_uuid', 'device_id', 'operation', 'status',
        'attempts', 'payload', 'result', 'conflict', 'available_at', 'synced_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'conflict' => 'array',
        'available_at' => 'datetime',
        'synced_at' => 'datetime',
    ];
}
