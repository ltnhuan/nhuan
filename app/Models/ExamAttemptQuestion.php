<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class ExamAttemptQuestion extends Model { use HasFactory; protected $fillable=['tenant_id','attempt_id','question_id','section_id','display_order','score','question_snapshot','options_snapshot','is_answered','is_marked_review','metadata']; protected $casts=['question_snapshot'=>'array','options_snapshot'=>'array','is_answered'=>'boolean','is_marked_review'=>'boolean','metadata'=>'array']; }
