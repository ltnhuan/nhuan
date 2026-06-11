<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LearnerEligibilitySummary extends Model
{
    public $timestamps=false;
    protected $fillable=['tenant_id','user_id','course_id','class_id','attendance_percent','absent_count','late_count','eligible_for_exam','reason','updated_at'];
    protected $casts=['eligible_for_exam'=>'boolean','updated_at'=>'datetime'];
}
