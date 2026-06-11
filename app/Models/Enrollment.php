<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    protected $fillable = ['tenant_id', 'course_id', 'class_section_id', 'cohort_id', 'cohort_group_id', 'user_id', 'source', 'sis_enrollment_id', 'status', 'completion_percent', 'risk_score', 'invited_at', 'accepted_at', 'enrolled_at', 'activated_at', 'suspended_at', 'completed_at', 'withdrawn_at', 'expires_at', 'created_by', 'metadata'];

    protected $casts = [
        'completion_percent' => 'float',
        'risk_score' => 'float',
        'invited_at' => 'datetime',
        'accepted_at' => 'datetime',
        'enrolled_at' => 'datetime',
        'activated_at' => 'datetime',
        'suspended_at' => 'datetime',
        'completed_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function learner() { return $this->belongsTo(LmsUser::class, 'user_id'); }
    public function course() { return $this->belongsTo(Course::class); }
    public function classSection() { return $this->belongsTo(ClassSection::class); }
    public function cohort() { return $this->belongsTo(Cohort::class); }
    public function cohortGroup() { return $this->belongsTo(CohortGroup::class); }
    public function events() { return $this->hasMany(EnrollmentEvent::class); }
}
