<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentUnlock extends Model
{
    use HasFactory;

    protected $table = 'content_unlocks';

    protected $fillable = ['tenant_id', 'user_id', 'course_id', 'target_type', 'target_id', 'is_unlocked', 'reason', 'unlocked_at', 'locked_message', 'metadata'];

    protected $casts = [
        'is_unlocked' => 'boolean',
        'unlocked_at' => 'datetime',
        'metadata' => 'array'
    ];
}
