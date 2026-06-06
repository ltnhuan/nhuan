<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class ExamAnswer extends Model { use HasFactory; protected $fillable=['tenant_id','attempt_id','attempt_question_id','question_id','answer_data','is_correct','score','feedback','graded_by','graded_at','autosaved_at']; protected $casts=['answer_data'=>'array','is_correct'=>'boolean','graded_at'=>'datetime','autosaved_at'=>'datetime']; public function attemptQuestion(){return $this->belongsTo(ExamAttemptQuestion::class,'attempt_question_id');} }
