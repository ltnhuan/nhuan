<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoWatchSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'user_id', 'course_id', 'component_id', 'video_asset_id', 'session_uuid',
        'started_at', 'ended_at', 'last_position_seconds', 'max_position_seconds', 'watched_seconds',
        'watch_percent', 'playback_rate', 'status', 'ip_address', 'user_agent', 'device_id', 'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function asset()
    {
        return $this->belongsTo(VideoAsset::class, 'video_asset_id');
    }

    public function events()
    {
        return $this->hasMany(VideoWatchEvent::class, 'session_id');
    }
}
