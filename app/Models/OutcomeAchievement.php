<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OutcomeAchievement extends Model
{
    protected $table = 'outcome_achievement';

    protected $fillable = ['tenant_id', 'user_id', 'course_id', 'learning_outcome_id', 'achievement_date', 'achievement_percent', 'achievement_level', 'evidence'];

    protected $casts = [
        'achievement_date' => 'date',
        'achievement_percent' => 'float',
        'evidence' => 'array',
    ];
}
