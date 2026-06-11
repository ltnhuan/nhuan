<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CompetencyFramework extends Model
{
    protected $fillable=['tenant_id','code','title','framework_type','standard','description','status','settings','created_by'];
    protected $casts=['settings'=>'array'];
    public function items(){return $this->hasMany(CompetencyFrameworkItem::class,'framework_id')->orderBy('sort_order');}
}
