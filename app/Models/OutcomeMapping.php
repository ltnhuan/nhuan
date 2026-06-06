<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OutcomeMapping extends Model
{
    protected $fillable=['tenant_id','source_outcome_id','target_outcome_id','competency_item_id','source_type','source_id','target_type','target_id','weight','evidence_level','metadata'];
    protected $casts=['metadata'=>'array'];
}
