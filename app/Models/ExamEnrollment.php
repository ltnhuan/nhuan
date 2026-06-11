<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamEnrollment extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'exam_id', 'user_id', 'course_id', 'class_id', 'status', 'assigned_by', 'available_from', 'available_until', 'metadata'];

    protected $casts = ['available_from' => 'datetime', 'available_until' => 'datetime', 'metadata' => 'array'];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }
}
