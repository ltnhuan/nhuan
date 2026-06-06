<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class EligibilityRule extends Model
{
    use HasFactory;
    protected $fillable=['tenant_id','course_id','class_id','rule_type','title','config','status','created_by'];
    protected $casts=['config'=>'array'];
}
