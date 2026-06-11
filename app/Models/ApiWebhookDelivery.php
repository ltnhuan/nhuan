<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiWebhookDelivery extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'webhook_endpoint_id', 'api_event_id', 'payload', 'response_status', 'response_body', 'status', 'attempts', 'next_retry_at'];

    protected $casts = [
        'payload' => 'array',
        'next_retry_at' => 'datetime',
    ];

    public function endpoint()
    {
        return $this->belongsTo(ApiWebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
