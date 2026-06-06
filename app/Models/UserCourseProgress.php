<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCourseProgress extends Model
{
    use HasFactory;

    protected $table = 'user_course_progress';

    protected $fillable = ['tenant_id', 'user_id', 'course_id', 'status', 'progress_percent', 'completed_components_count', 'total_components_count', 'completed_required_count', 'total_required_count', 'last_component_id', 'last_accessed_at', 'completed_at', 'risk_level', 'metadata'];

    protected $casts = [
        'last_accessed_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array'
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
