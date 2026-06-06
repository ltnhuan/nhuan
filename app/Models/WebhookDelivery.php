<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class WebhookDelivery extends Model
{
    protected $fillable=['tenant_id','endpoint_id','event_id','payload','response_status','response_body','status','attempts','next_retry_at'];
    protected $casts=['payload'=>'array','next_retry_at'=>'datetime'];
}
