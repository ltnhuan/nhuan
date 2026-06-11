<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class CompetencyRecord extends Model
{
    use HasFactory;
    protected $fillable=['tenant_id','user_id','course_id','learning_outcome_id','outcome_type','code','title','score','attainment_status','evidence'];
    protected $casts=['evidence'=>'array'];
}
