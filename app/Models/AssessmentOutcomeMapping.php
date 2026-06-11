<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AssessmentOutcomeMapping extends Model
{
    protected $fillable=['tenant_id','assessment_type','assessment_id','outcome_id','method','weight','max_score','rubric_criteria','status'];
    protected $casts=['rubric_criteria'=>'array'];
}
