<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class LearnerSkill extends Model
{
    use HasFactory;
    protected $fillable=['tenant_id','user_id','skill_definition_id','score','source','evidence','assessed_at','assessed_by'];
    protected $casts=['evidence'=>'array','assessed_at'=>'datetime'];
    public function definition(){return $this->belongsTo(SkillDefinition::class,'skill_definition_id');}
}
