<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'course_id', 'user_id', 'assistant_type', 'question', 'answer', 'citations', 'metadata',
    ];

    protected $casts = [
        'citations' => 'array',
        'metadata' => 'array',
    ];
}
