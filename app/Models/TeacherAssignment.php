<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherAssignment extends Model
{
    protected $fillable = ['tenant_id', 'course_id', 'class_section_id', 'user_id', 'role', 'status', 'assigned_at', 'ended_at', 'metadata'];

    protected $casts = ['assigned_at' => 'datetime', 'ended_at' => 'datetime', 'metadata' => 'array'];

    public function user() { return $this->belongsTo(LmsUser::class); }
    public function classSection() { return $this->belongsTo(ClassSection::class); }
}
