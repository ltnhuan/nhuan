<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class ExamSection extends Model { use HasFactory; protected $fillable=['tenant_id','exam_id','title','description','sort_order','question_count','score','config']; protected $casts=['config'=>'array']; }
