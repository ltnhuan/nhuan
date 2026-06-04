<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseComponent extends Model
{
    use HasFactory;

    protected $table = 'course_components';

    protected $fillable = [
        'tenant_id', 'course_id', 'section_id', 'component_type', 'title', 'content_id',
        'config', 'sort_order', 'required', 'status',
    ];

    protected $casts = [
        'config' => 'array',
        'required' => 'boolean',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function section()
    {
        return $this->belongsTo(CourseSection::class, 'section_id');
    }

    public function contentItem()
    {
        return $this->belongsTo(ContentRepositoryItem::class, 'content_id');
    }

    public function activityType()
    {
        return $this->belongsTo(ActivityType::class, 'component_type', 'key');
    }
}
