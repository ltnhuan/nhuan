<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsBenchmark extends Model
{
    protected $fillable = [
        'tenant_id',
        'benchmark_key',
        'scope_type',
        'scope_id',
        'industry_value',
        'internal_target',
        'current_value',
        'status',
        'note',
    ];

    protected $casts = [
        'industry_value' => 'float',
        'internal_target' => 'float',
        'current_value' => 'float',
    ];
}
