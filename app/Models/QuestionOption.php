<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionOption extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'question_id', 'option_key', 'content', 'is_correct', 'score_weight', 'feedback', 'sort_order', 'media_url', 'metadata'];

    protected $casts = ['is_correct' => 'boolean', 'metadata' => 'array'];
}
