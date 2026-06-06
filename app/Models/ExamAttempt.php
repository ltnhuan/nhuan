<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class ExamAttempt extends Model { use HasFactory; protected $fillable=['tenant_id','exam_id','user_id','attempt_no','session_uuid','status','started_at','submitted_at','graded_at','time_spent_seconds','score','max_score','pass_status','suspicious_score','ip_address','user_agent','device_id','metadata']; protected $casts=['started_at'=>'datetime','submitted_at'=>'datetime','graded_at'=>'datetime','metadata'=>'array']; public function attemptQuestions(){return $this->hasMany(ExamAttemptQuestion::class,'attempt_id')->orderBy('display_order');} public function answers(){return $this->hasMany(ExamAnswer::class,'attempt_id');} public function events(){return $this->hasMany(ExamAttemptEvent::class,'attempt_id');} public function exam(){return $this->belongsTo(Exam::class);} public function user(){return $this->belongsTo(LmsUser::class,'user_id');} }
