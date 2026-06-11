<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyAnswer extends Model
{
    protected $fillable = ['tenant_id','survey_response_id','survey_question_id','question_type','text_answer','numeric_answer','json_answer','score'];
    protected $casts = ['numeric_answer' => 'float','json_answer' => 'array','score' => 'float'];
}
