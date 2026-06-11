<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id','course_id','component_id','question_bank_id','blueprint_id','code','title','description','exam_type','delivery_mode','status','total_score','pass_score','duration_minutes','max_attempts','shuffle_questions','shuffle_options','show_result_mode','show_correct_answers','open_at','close_at','settings','created_by','approved_by','approved_at'];
    protected $casts = ['shuffle_questions'=>'boolean','shuffle_options'=>'boolean','show_correct_answers'=>'boolean','open_at'=>'datetime','close_at'=>'datetime','approved_at'=>'datetime','settings'=>'array'];

    public function course() { return $this->belongsTo(Course::class); }
    public function questionBank() { return $this->belongsTo(QuestionBank::class, 'question_bank_id'); }
    public function blueprint() { return $this->belongsTo(ExamBlueprint::class, 'blueprint_id'); }
    public function creator() { return $this->belongsTo(LmsUser::class, 'created_by'); }
    public function approver() { return $this->belongsTo(LmsUser::class, 'approved_by'); }
    public function sections() { return $this->hasMany(ExamSection::class)->orderBy('sort_order'); }
    public function questions() { return $this->hasMany(ExamQuestion::class)->orderBy('sort_order'); }
    public function attempts() { return $this->hasMany(ExamAttempt::class); }
}
