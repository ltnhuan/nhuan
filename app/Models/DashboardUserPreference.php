<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardUserPreference extends Model
{
    protected $fillable = ['tenant_id', 'user_id', 'dashboard_key', 'layout', 'filters', 'pinned_widgets'];

    protected $casts = [
        'layout' => 'array',
        'filters' => 'array',
        'pinned_widgets' => 'array',
    ];
}
