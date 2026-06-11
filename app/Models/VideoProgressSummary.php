<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoProgressSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'user_id', 'course_id', 'component_id', 'video_asset_id',
        'total_duration_seconds', 'watched_seconds', 'max_position_seconds', 'watch_percent',
        'is_completed', 'completed_at', 'suspicious_score', 'last_watched_at', 'metadata',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'last_watched_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function asset()
    {
        return $this->belongsTo(VideoAsset::class, 'video_asset_id');
    }
}
