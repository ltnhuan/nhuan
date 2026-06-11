<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id','course_id','component_id','title','description','assignment_type','submission_type','status','open_at','due_at','allow_late','late_penalty_config','max_score','pass_score','max_submissions','rubric_id','settings','created_by'];
    protected $casts = ['open_at'=>'datetime','due_at'=>'datetime','allow_late'=>'boolean','late_penalty_config'=>'array','settings'=>'array'];

    public function rubric() { return $this->belongsTo(Rubric::class); }
    public function submissions() { return $this->hasMany(AssignmentSubmission::class); }
    public function grades() { return $this->hasMany(AssignmentGrade::class); }
    public function course() { return $this->belongsTo(Course::class); }
    public function component() { return $this->belongsTo(CourseComponent::class); }
}
