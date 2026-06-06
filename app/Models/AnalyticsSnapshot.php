<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsSnapshot extends Model
{
    protected $fillable = ['tenant_id', 'scope_type', 'scope_id', 'period_type', 'period_start', 'period_end', 'metrics'];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'metrics' => 'array',
    ];
}
