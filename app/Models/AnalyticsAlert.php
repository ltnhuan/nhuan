<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsAlert extends Model
{
    protected $fillable = [
        'tenant_id',
        'alert_type',
        'severity',
        'scope_type',
        'scope_id',
        'title',
        'message',
        'recommended_action',
        'status',
        'assigned_to',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];
}
