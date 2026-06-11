<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class ExamQuestion extends Model { use HasFactory; protected $fillable=['tenant_id','exam_id','section_id','question_id','score','sort_order','required','metadata']; protected $casts=['required'=>'boolean','metadata'=>'array']; public function question(){return $this->belongsTo(Question::class);} }
