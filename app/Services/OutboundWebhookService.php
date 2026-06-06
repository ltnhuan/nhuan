<?php

namespace App\Services;

use App\Models\IntegrationEvent;
use App\Models\IntegrationSystem;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OutboundWebhookService
{
    public function createOutboundEvent(IntegrationSystem $system, string $eventKey, string $entityType, ?string $entityId, array $payload, ?string $idempotencyKey = null): IntegrationEvent
    {
        return IntegrationEvent::query()->firstOrCreate(
            ['system_id'=>$system->id,'event_key'=>$eventKey,'idempotency_key'=>$idempotencyKey ?: (string) Str::uuid()],
            ['tenant_id'=>$system->tenant_id,'direction'=>'outbound','entity_type'=>$entityType,'entity_id'=>$entityId,'payload'=>$payload,'status'=>'pending']
        );
    }

    public function deliver(IntegrationEvent $event, WebhookEndpoint $endpoint): WebhookDelivery
    {
        $delivery = WebhookDelivery::query()->create(['tenant_id'=>$event->tenant_id,'endpoint_id'=>$endpoint->id,'event_id'=>$event->id,'payload'=>$event->payload,'status'=>'pending']);
        return $this->attemptDelivery($delivery, $endpoint);
    }

    public function retryFailedDeliveries(): int
    {
        $count = 0;
        foreach (WebhookDelivery::query()->whereIn('status',['failed','retrying'])->where(fn($q)=>$q->whereNull('next_retry_at')->orWhere('next_retry_at','<=',now()))->get() as $delivery) {
            $endpoint = WebhookEndpoint::query()->find($delivery->endpoint_id);
            if ($endpoint) { $this->attemptDelivery($delivery, $endpoint); $count++; }
        }
        return $count;
    }

    public function signPayload(array $payload, string $secret): string
    {
        return hash_hmac('sha256', json_encode($payload), $secret);
    }

    private function attemptDelivery(WebhookDelivery $delivery, WebhookEndpoint $endpoint): WebhookDelivery
    {
        try {
            if ($endpoint->settings['mock_fail'] ?? false) throw new \RuntimeException('Mock webhook failure');
            $response = Http::timeout(5)->withHeaders(['X-EraLMS-Signature'=>$this->signPayload($delivery->payload ?? [], $endpoint->secret)])->post($endpoint->url, $delivery->payload ?? []);
            $ok = $response->successful();
            $delivery->forceFill(['status'=>$ok ? 'success' : 'retrying','response_status'=>$response->status(),'response_body'=>substr($response->body(),0,1000),'attempts'=>$delivery->attempts + 1,'next_retry_at'=>$ok ? null : now()->addMinutes(5)])->save();
            $endpoint->forceFill([$ok ? 'last_success_at' : 'last_failure_at' => now()])->save();
        } catch (\Throwable $e) {
            $delivery->forceFill(['status'=>$delivery->attempts >= 2 ? 'failed' : 'retrying','response_body'=>$e->getMessage(),'attempts'=>$delivery->attempts + 1,'next_retry_at'=>now()->addMinutes(5)])->save();
            $endpoint->forceFill(['last_failure_at'=>now()])->save();
        }
        return $delivery->fresh();
    }
}
