<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyQuestion extends Model
{
    protected $fillable = ['tenant_id','survey_form_id','question_type','code','prompt','help_text','required','sort_order','options','matrix_rows','matrix_columns','scoring'];
    protected $casts = ['required' => 'boolean','options' => 'array','matrix_rows' => 'array','matrix_columns' => 'array','scoring' => 'array'];

    public function form(){return $this->belongsTo(SurveyForm::class, 'survey_form_id');}
}
