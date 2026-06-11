<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class IntegrationMapping extends Model
{
    protected $fillable=['tenant_id','system_id','entity_type','local_id','external_id','external_code','mapping_status','metadata'];
    protected $casts=['metadata'=>'array'];
}
