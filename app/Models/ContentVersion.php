<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentVersion extends Model
{
    use HasFactory;

    protected $table = 'content_versions';

    protected $fillable = ['tenant_id', 'content_item_id', 'version', 'storage_path', 'checksum', 'file_size', 'change_note', 'created_by'];

    protected $casts = [
        
    ];
}
