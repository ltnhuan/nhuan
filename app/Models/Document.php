<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'course_id', 'content_repository_item_id', 'source_type', 'title',
        'mime_type', 'storage_path', 'status', 'metadata', 'ingested_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'ingested_at' => 'datetime',
    ];

    public function chunks()
    {
        return $this->hasMany(DocumentChunk::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
