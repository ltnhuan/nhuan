<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningCompletion extends Model
{
    use HasFactory;

    protected $table = 'learning_completions';

    protected $fillable = ['tenant_id', 'user_id', 'course_id', 'section_id', 'component_id', 'completion_type', 'status', 'progress_percent', 'score', 'completed_at', 'verified_by', 'source', 'metadata'];

    protected $casts = [
        'completed_at' => 'datetime',
        'metadata' => 'array'
    ];
}
