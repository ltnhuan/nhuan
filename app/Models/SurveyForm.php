<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyForm extends Model
{
    protected $fillable = ['tenant_id','code','title','survey_type','description','status','settings','created_by'];
    protected $casts = ['settings' => 'array'];

    public function questions(){return $this->hasMany(SurveyQuestion::class)->orderBy('sort_order');}
    public function campaigns(){return $this->hasMany(SurveyCampaign::class);}
}
