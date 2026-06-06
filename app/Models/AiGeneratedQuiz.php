<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiGeneratedQuiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'course_id', 'document_id', 'quiz_type', 'title', 'questions', 'metadata',
    ];

    protected $casts = [
        'questions' => 'array',
        'metadata' => 'array',
    ];
}
