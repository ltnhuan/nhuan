<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $table = 'courses';

    protected $fillable = [
        'tenant_id', 'code', 'title', 'slug', 'short_description', 'description', 'category_id',
        'academic_unit_id', 'level', 'course_type', 'status', 'visibility', 'language',
        'thumbnail_url', 'estimated_hours', 'owner_id', 'approved_by', 'approved_at',
        'published_at', 'settings',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
        'settings' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category()
    {
        return $this->belongsTo(CourseCategory::class, 'category_id');
    }

    public function academicUnit()
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    public function owner()
    {
        return $this->belongsTo(LmsUser::class, 'owner_id');
    }

    public function approver()
    {
        return $this->belongsTo(LmsUser::class, 'approved_by');
    }

    public function sections()
    {
        return $this->hasMany(CourseSection::class)->orderBy('sort_order');
    }

    public function rootSections()
    {
        return $this->hasMany(CourseSection::class)->whereNull('parent_id')->orderBy('sort_order');
    }

    public function components()
    {
        return $this->hasMany(CourseComponent::class);
    }

    public function versions()
    {
        return $this->hasMany(CourseVersion::class)->orderByDesc('version');
    }

    public function publishLogs()
    {
        return $this->hasMany(CoursePublishLog::class)->latest('created_at');
    }
}
