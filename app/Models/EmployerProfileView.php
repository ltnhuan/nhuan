<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EmployerProfileView extends Model
{
    public $timestamps=false;
    protected $fillable=['tenant_id','user_id','employer_name','viewer_email','action','metadata','created_at'];
    protected $casts=['metadata'=>'array','created_at'=>'datetime'];
}
