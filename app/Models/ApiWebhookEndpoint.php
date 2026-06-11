<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiWebhookEndpoint extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'system_id', 'name', 'url', 'secret', 'subscribed_events', 'status', 'last_success_at', 'last_failure_at', 'settings'];

    protected $casts = [
        'subscribed_events' => 'array',
        'settings' => 'array',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
    ];

    public function deliveries()
    {
        return $this->hasMany(ApiWebhookDelivery::class, 'webhook_endpoint_id');
    }
}
