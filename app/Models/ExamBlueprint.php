<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamBlueprint extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'course_id', 'question_bank_id', 'code', 'name', 'description', 'total_questions', 'total_score', 'duration_minutes', 'config', 'status', 'created_by'];

    protected $casts = ['config' => 'array'];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function questionBank()
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    public function creator()
    {
        return $this->belongsTo(LmsUser::class, 'created_by');
    }
}
