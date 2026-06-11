<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseSection extends Model
{
    use HasFactory;

    protected $table = 'course_sections';

    protected $fillable = [
        'tenant_id', 'course_id', 'parent_id', 'type', 'title', 'description', 'sort_order',
        'status', 'release_at', 'due_at', 'settings',
    ];

    protected $casts = [
        'release_at' => 'datetime',
        'due_at' => 'datetime',
        'settings' => 'array',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function components()
    {
        return $this->hasMany(CourseComponent::class, 'section_id')->orderBy('sort_order');
    }

    public function isUnit(): bool
    {
        return $this->type === 'unit';
    }
}
