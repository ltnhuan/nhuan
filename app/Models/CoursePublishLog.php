<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoursePublishLog extends Model
{
    use HasFactory;

    protected $table = 'course_publish_logs';

    protected $fillable = ['tenant_id', 'course_id', 'version_id', 'action', 'actor_id', 'note'];

    protected $casts = [
        
    ];
}
