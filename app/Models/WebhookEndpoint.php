<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WebhookEndpoint extends Model
{
    protected $fillable=['tenant_id','system_id','name','url','secret','subscribed_events','status','last_success_at','last_failure_at','settings'];
    protected $casts=['subscribed_events'=>'array','last_success_at'=>'datetime','last_failure_at'=>'datetime','settings'=>'array'];
}
