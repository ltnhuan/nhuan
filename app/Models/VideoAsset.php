<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'repository_item_id', 'course_id', 'component_id', 'title', 'description',
        'original_filename', 'original_storage_path', 'hls_master_path', 'duration_seconds',
        'file_size', 'mime_type', 'processing_status', 'visibility', 'checksum', 'thumbnail_url',
        'subtitle_path', 'transcript_path', 'settings', 'uploaded_by', 'processed_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'processed_at' => 'datetime',
    ];

    public function component()
    {
        return $this->belongsTo(CourseComponent::class, 'component_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function renditions()
    {
        return $this->hasMany(VideoRendition::class);
    }
}
