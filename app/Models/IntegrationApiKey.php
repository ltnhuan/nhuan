<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class IntegrationApiKey extends Model
{
    protected $fillable=['tenant_id','system_id','key_name','api_key_hash','scopes','status','last_used_at','expires_at'];
    protected $casts=['scopes'=>'array','last_used_at'=>'datetime','expires_at'=>'datetime'];
}
