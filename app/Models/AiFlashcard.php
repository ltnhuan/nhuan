<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiFlashcard extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'course_id', 'document_id', 'front', 'back', 'difficulty', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
