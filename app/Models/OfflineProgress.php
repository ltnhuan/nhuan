<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfflineProgress extends Model
{
    protected $table = 'offline_progress';

    protected $fillable = [
        'tenant_id', 'user_id', 'course_id', 'component_id', 'device_id',
        'progress_percent', 'status', 'metadata', 'client_updated_at', 'synced_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'client_updated_at' => 'datetime',
        'synced_at' => 'datetime',
    ];
}
