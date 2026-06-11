<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearningMetric extends Model
{
    protected $fillable = ['tenant_id', 'user_id', 'course_id', 'class_section_id', 'metric_date', 'login_frequency', 'study_time_minutes', 'video_completion', 'assignment_completion', 'quiz_score', 'attendance', 'forum_activity', 'metadata'];

    protected $casts = [
        'metric_date' => 'date',
        'video_completion' => 'float',
        'assignment_completion' => 'float',
        'quiz_score' => 'float',
        'attendance' => 'float',
        'metadata' => 'array',
    ];

    public function learner() { return $this->belongsTo(LmsUser::class, 'user_id'); }
    public function course() { return $this->belongsTo(Course::class); }
}
