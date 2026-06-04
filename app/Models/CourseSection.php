<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseSection extends Model
{
    use HasFactory;

    protected $table = 'course_sections';

    protected $fillable = ['tenant_id', 'course_id', 'parent_id', 'type', 'title', 'description', 'sort_order', 'status', 'release_at', 'due_at', 'settings'];

    protected $casts = [
        'release_at' => 'datetime',
        'due_at' => 'datetime',
        'settings' => 'array'
    ];

    public function components()
    {
        return $this->hasMany(CourseComponent::class, 'section_id');
    }
}
