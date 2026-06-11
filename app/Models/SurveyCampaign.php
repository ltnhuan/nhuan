<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyCampaign extends Model
{
    protected $fillable = ['tenant_id','survey_form_id','code','title','target_scope','course_id','class_section_id','academic_unit_id','teacher_id','is_anonymous','allow_identified','status','starts_at','ends_at','channels','settings','created_by'];
    protected $casts = ['is_anonymous' => 'boolean','allow_identified' => 'boolean','starts_at' => 'datetime','ends_at' => 'datetime','channels' => 'array','settings' => 'array'];

    public function form(){return $this->belongsTo(SurveyForm::class, 'survey_form_id');}
    public function responses(){return $this->hasMany(SurveyResponse::class);}
    public function improvements(){return $this->hasMany(SurveyImprovement::class);}
    public function evidence(){return $this->hasMany(SurveyEvidenceFile::class);}
}
