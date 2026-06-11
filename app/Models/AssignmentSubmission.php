<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignmentSubmission extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id','assignment_id','user_id','group_id','submission_no','status','submitted_at','content_text','content_url','total_score','feedback','graded_by','graded_at','metadata'];
    protected $casts = ['submitted_at'=>'datetime','graded_at'=>'datetime','metadata'=>'array'];

    public function assignment() { return $this->belongsTo(Assignment::class); }
    public function files() { return $this->hasMany(AssignmentSubmissionFile::class, 'submission_id'); }
    public function grade() { return $this->hasOne(AssignmentGrade::class, 'submission_id')->latestOfMany(); }
    public function user() { return $this->belongsTo(LmsUser::class); }
}
