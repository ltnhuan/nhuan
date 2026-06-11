<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsForecast extends Model
{
    protected $fillable = [
        'tenant_id',
        'forecast_key',
        'scope_type',
        'scope_id',
        'forecast_date',
        'horizon',
        'predicted_value',
        'confidence',
        'model_name',
        'features',
        'explanation',
    ];

    protected $casts = [
        'forecast_date' => 'date',
        'predicted_value' => 'float',
        'confidence' => 'float',
        'features' => 'array',
    ];
}
