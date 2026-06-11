<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentRepositoryItem extends Model
{
    use HasFactory;

    protected $table = 'content_repository_items';

    protected $fillable = [
        'tenant_id', 'parent_id', 'academic_unit_id', 'item_type', 'title', 'description',
        'storage_path', 'mime_type', 'file_size', 'checksum', 'owner_id', 'visibility',
        'status', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('title');
    }

    public function owner()
    {
        return $this->belongsTo(LmsUser::class, 'owner_id');
    }

    public function academicUnit()
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    public function versions()
    {
        return $this->hasMany(ContentVersion::class, 'content_item_id')->orderByDesc('version');
    }

    public function components()
    {
        return $this->hasMany(CourseComponent::class, 'content_id');
    }

    public function isFolder(): bool
    {
        return $this->item_type === 'folder';
    }
}
