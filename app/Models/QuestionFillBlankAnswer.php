<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionFillBlankAnswer extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'question_id', 'blank_key', 'accepted_answer', 'case_sensitive', 'score_weight', 'feedback'];

    protected $casts = ['case_sensitive' => 'boolean'];
}
