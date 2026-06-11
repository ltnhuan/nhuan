<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearnerRiskProfile extends Model
{
    protected $fillable = ['tenant_id', 'user_id', 'course_id', 'risk_score', 'risk_level', 'risk_factors', 'recommendations', 'last_calculated_at'];

    protected $casts = [
        'risk_score' => 'float',
        'risk_factors' => 'array',
        'recommendations' => 'array',
        'last_calculated_at' => 'datetime',
    ];

    public function learner() { return $this->belongsTo(LmsUser::class, 'user_id'); }
    public function course() { return $this->belongsTo(Course::class); }
    public function alerts() { return $this->hasMany(RiskAlert::class, 'risk_profile_id'); }
}
