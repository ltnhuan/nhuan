<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyImprovement extends Model
{
    protected $fillable = ['tenant_id','survey_campaign_id','course_id','class_section_id','academic_unit_id','teacher_id','issue_title','issue_description','improvement_action','result','status','priority','owner_id','due_date','completed_at','metrics'];
    protected $casts = ['due_date' => 'date','completed_at' => 'datetime','metrics' => 'array'];
}
