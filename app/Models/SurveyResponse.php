<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyResponse extends Model
{
    protected $fillable = ['tenant_id','survey_campaign_id','survey_form_id','respondent_id','respondent_hash','is_anonymous','status','average_score','nps_score','submitted_at','metadata'];
    protected $casts = ['is_anonymous' => 'boolean','average_score' => 'float','submitted_at' => 'datetime','metadata' => 'array'];

    public function campaign(){return $this->belongsTo(SurveyCampaign::class, 'survey_campaign_id');}
    public function answers(){return $this->hasMany(SurveyAnswer::class);}
}
