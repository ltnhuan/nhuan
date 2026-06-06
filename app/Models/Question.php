<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'question_bank_id', 'category_id', 'code', 'question_type', 'title', 'stem',
        'explanation', 'difficulty', 'bloom_level', 'default_score', 'penalty_score',
        'time_limit_seconds', 'status', 'owner_id', 'approved_by', 'approved_at', 'metadata',
    ];

    protected $casts = ['metadata' => 'array', 'approved_at' => 'datetime'];

    public function options()
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort_order');
    }

    public function matchingPairs()
    {
        return $this->hasMany(QuestionMatchingPair::class)->orderBy('sort_order');
    }

    public function fillBlankAnswers()
    {
        return $this->hasMany(QuestionFillBlankAnswer::class);
    }

    public function outcomes()
    {
        return $this->belongsToMany(LearningOutcome::class, 'question_outcome_map', 'question_id', 'outcome_id')->withPivot('weight');
    }

    public function versions()
    {
        return $this->hasMany(QuestionVersion::class);
    }
}
