<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LearningProgressEvent extends Model
{
    use HasFactory;

    protected $table = 'learning_progress_events';

    protected $fillable = ['tenant_id', 'user_id', 'course_id', 'section_id', 'component_id', 'event_type', 'event_value', 'metadata', 'ip_address', 'user_agent', 'device_id'];

    protected $casts = [
        'metadata' => 'array'
    ];
}
