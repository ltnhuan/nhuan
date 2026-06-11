<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyEvidenceFile extends Model
{
    protected $fillable = ['tenant_id','survey_campaign_id','survey_improvement_id','evidence_type','title','file_path','mime_type','checksum','metadata','created_by'];
    protected $casts = ['metadata' => 'array'];
}
