<?php

namespace App\Jobs\ApiOperations;

use App\Services\ApiOperations\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverApiWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $webhookEndpointId,
        public readonly int $apiEventId
    ) {
        $this->onQueue('api-webhooks');
    }

    public function handle(WebhookService $webhooks): void
    {
        $webhooks->deliverWebhook($this->webhookEndpointId, $this->apiEventId);
    }
}
