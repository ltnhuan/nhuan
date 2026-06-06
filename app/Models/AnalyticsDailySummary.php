<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsDailySummary extends Model
{
    protected $fillable = ['tenant_id', 'scope_type', 'scope_id', 'period_start', 'period_end', 'learner_count', 'avg_progress', 'avg_grade', 'avg_engagement', 'avg_risk_score', 'high_risk_count', 'critical_risk_count', 'completion_rate', 'chart_data'];

    protected $casts = ['period_start' => 'date', 'period_end' => 'date', 'avg_progress' => 'float', 'avg_grade' => 'float', 'avg_engagement' => 'float', 'avg_risk_score' => 'float', 'completion_rate' => 'float', 'chart_data' => 'array'];
}
