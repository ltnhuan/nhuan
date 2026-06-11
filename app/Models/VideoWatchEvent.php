<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoWatchEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'user_id', 'course_id', 'component_id', 'video_asset_id', 'session_id',
        'event_type', 'position_seconds', 'watched_delta_seconds', 'playback_rate', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
