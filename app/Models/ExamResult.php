<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class ExamResult extends Model { use HasFactory; protected $fillable=['tenant_id','exam_id','user_id','attempt_id','score','max_score','percent','pass_status','published','published_at','approved_by','metadata']; protected $casts=['published'=>'boolean','published_at'=>'datetime','metadata'=>'array']; public function user(){return $this->belongsTo(LmsUser::class,'user_id');} public function attempt(){return $this->belongsTo(ExamAttempt::class,'attempt_id');} public function approver(){return $this->belongsTo(LmsUser::class,'approved_by');} }
