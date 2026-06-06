<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class IntegrationEvent extends Model
{
    protected $fillable=['tenant_id','system_id','event_key','direction','entity_type','entity_id','idempotency_key','payload','status','attempts','error_message','processed_at'];
    protected $casts=['payload'=>'array','processed_at'=>'datetime'];
}
