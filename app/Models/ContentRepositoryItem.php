<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentRepositoryItem extends Model
{
    use HasFactory;

    protected $table = 'content_repository_items';

    protected $fillable = ['tenant_id', 'parent_id', 'academic_unit_id', 'item_type', 'title', 'description', 'storage_path', 'mime_type', 'file_size', 'checksum', 'owner_id', 'visibility', 'status', 'metadata'];

    protected $casts = [
        'metadata' => 'array'
    ];
}
