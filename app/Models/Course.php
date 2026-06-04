<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $table = 'courses';

    protected $fillable = ['tenant_id', 'code', 'title', 'slug', 'short_description', 'description', 'category_id', 'academic_unit_id', 'level', 'course_type', 'status', 'visibility', 'language', 'thumbnail_url', 'estimated_hours', 'owner_id', 'approved_by', 'approved_at', 'published_at', 'settings'];

    protected $casts = [
        'approved_at' => 'datetime',
        'published_at' => 'datetime',
        'settings' => 'array'
    ];

    public function sections()
    {
        return $this->hasMany(CourseSection::class);
    }
}
