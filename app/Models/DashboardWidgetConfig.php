<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardWidgetConfig extends Model
{
    protected $fillable = [
        'tenant_id',
        'dashboard_key',
        'widget_key',
        'title',
        'widget_type',
        'data_source',
        'config',
        'permission_key',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
    ];
}
