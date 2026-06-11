<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiskAlert extends Model
{
    protected $fillable = ['tenant_id', 'user_id', 'course_id', 'risk_profile_id', 'alert_type', 'severity', 'status', 'message', 'recommended_actions', 'triggered_at', 'acknowledged_at', 'resolved_at', 'assigned_to'];

    protected $casts = [
        'recommended_actions' => 'array',
        'triggered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function learner() { return $this->belongsTo(LmsUser::class, 'user_id'); }
    public function course() { return $this->belongsTo(Course::class); }
    public function riskProfile() { return $this->belongsTo(LearnerRiskProfile::class, 'risk_profile_id'); }
}
