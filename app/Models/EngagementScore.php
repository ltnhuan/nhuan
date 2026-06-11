<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EngagementScore extends Model
{
    protected $fillable = ['tenant_id', 'user_id', 'course_id', 'score_date', 'engagement_score', 'login_score', 'study_score', 'content_score', 'social_score', 'signals'];

    protected $casts = [
        'score_date' => 'date',
        'engagement_score' => 'float',
        'login_score' => 'float',
        'study_score' => 'float',
        'content_score' => 'float',
        'social_score' => 'float',
        'signals' => 'array',
    ];
}
