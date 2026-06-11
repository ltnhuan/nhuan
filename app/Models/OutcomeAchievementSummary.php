<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OutcomeAchievementSummary extends Model
{
    public $timestamps=false;
    protected $fillable=['tenant_id','outcome_id','competency_item_id','course_id','class_id','user_id','achievement_percent','assessed_count','evidence_count','attainment_status','metadata','updated_at'];
    protected $casts=['metadata'=>'array','updated_at'=>'datetime'];
}
