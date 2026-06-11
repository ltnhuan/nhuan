<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassSection extends Model
{
    protected $fillable = ['tenant_id', 'course_id', 'cohort_id', 'cohort_group_id', 'parent_id', 'sis_section_id', 'code', 'name', 'section_type', 'delivery_mode', 'status', 'capacity', 'starts_at', 'ends_at', 'schedule', 'metadata'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'schedule' => 'array', 'metadata' => 'array'];

    public function course() { return $this->belongsTo(Course::class); }
    public function cohort() { return $this->belongsTo(Cohort::class); }
    public function cohortGroup() { return $this->belongsTo(CohortGroup::class); }
    public function enrollments() { return $this->hasMany(Enrollment::class); }
    public function teacherAssignments() { return $this->hasMany(TeacherAssignment::class); }
}
