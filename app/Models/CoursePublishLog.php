<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoursePublishLog extends Model
{
    use HasFactory;

    protected $table = 'course_publish_logs';

    protected $fillable = ['tenant_id', 'course_id', 'version_id', 'action', 'actor_id', 'note'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function version()
    {
        return $this->belongsTo(CourseVersion::class, 'version_id');
    }

    public function actor()
    {
        return $this->belongsTo(LmsUser::class, 'actor_id');
    }
}
