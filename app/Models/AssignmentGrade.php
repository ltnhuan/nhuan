<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignmentGrade extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id','submission_id','assignment_id','user_id','score','max_score','feedback','rubric_breakdown','ai_suggested_score','ai_feedback','grading_status','graded_by','approved_by','approved_at'];
    protected $casts = ['rubric_breakdown'=>'array','approved_at'=>'datetime'];

    public function submission() { return $this->belongsTo(AssignmentSubmission::class, 'submission_id'); }
    public function assignment() { return $this->belongsTo(Assignment::class); }
}
