<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GradeChangeLog extends Model
{
    public $timestamps=false;
    protected $fillable=['tenant_id','gradebook_id','grade_item_id','user_id','before','after','reason','actor_id','created_at'];
    protected $casts=['before'=>'array','after'=>'array','created_at'=>'datetime'];
}
