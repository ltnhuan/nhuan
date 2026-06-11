<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CompetencyFrameworkItem extends Model
{
    protected $fillable=['tenant_id','framework_id','parent_id','code','title','item_type','level','description','rubric','sort_order','status'];
    protected $casts=['rubric'=>'array'];
}
