<?php

namespace App\Services\ApiOperations;

use App\Models\ApiEvent;
use App\Models\ApiWebhookDelivery;
use App\Models\ApiWebhookEndpoint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class WebhookService
{
    public function createWebhookEndpoint(array $data): ApiWebhookEndpoint
    {
        $secret = $data['secret'] ?? 'whsec_'.Str::random(40);
        $data['secret'] = Crypt::encryptString($secret);

        return ApiWebhookEndpoint::query()->create($data + [
            'subscribed_events' => [],
            'status' => 'active',
            'settings' => [],
        ]);
    }

    public function deliverWebhook(ApiWebhookEndpoint|int $endpoint, ApiEvent|int $event, ?array $payload = null): ApiWebhookDelivery
    {
        $endpoint = $endpoint instanceof ApiWebhookEndpoint ? $endpoint : ApiWebhookEndpoint::query()->findOrFail($endpoint);
        $event = $event instanceof ApiEvent ? $event : ApiEvent::query()->findOrFail($event);
        $payload ??= $event->payload ?? [];

        $delivery = ApiWebhookDelivery::query()->create([
            'tenant_id' => $endpoint->tenant_id,
            'webhook_endpoint_id' => $endpoint->id,
            'api_event_id' => $event->id,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 1,
        ]);

        $shouldFail = $endpoint->status !== 'active'
            || ($endpoint->settings['mock_fail'] ?? false)
            || str_contains($endpoint->url, 'example.invalid');

        if ($shouldFail) {
            $delivery->forceFill([
                'status' => 'failed',
                'response_status' => 503,
                'response_body' => 'Webhook delivery failed.',
                'next_retry_at' => now()->addMinute(),
            ])->save();
            $endpoint->forceFill(['last_failure_at' => now()])->save();

            return $delivery->fresh();
        }

        $signature = $this->signWebhookPayload($payload, $this->plainSecret($endpoint));
        $delivery->forceFill([
            'status' => 'success',
            'response_status' => 200,
            'response_body' => json_encode(['accepted' => true, 'signature' => $signature]),
            'next_retry_at' => null,
        ])->save();
        $endpoint->forceFill(['last_success_at' => now()])->save();

        return $delivery->fresh();
    }

    public function signWebhookPayload(array|string $payload, string $secret): string
    {
        $body = is_string($payload) ? $payload : json_encode($payload);

        return hash_hmac('sha256', $body, $secret);
    }

    public function verifySignature(array|string $payload, ?string $signature, string $secret): bool
    {
        return is_string($signature) && hash_equals($this->signWebhookPayload($payload, $secret), $signature);
    }

    public function retryFailedWebhook(?int $webhookEndpointId = null, int $limit = 100): int
    {
        $count = 0;
        ApiWebhookDelivery::query()
            ->whereIn('status', ['failed', 'retrying'])
            ->when($webhookEndpointId, fn ($query) => $query->where('webhook_endpoint_id', $webhookEndpointId))
            ->where(fn ($query) => $query->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now()))
            ->limit($limit)
            ->get()
            ->each(function (ApiWebhookDelivery $delivery) use (&$count) {
                $endpoint = ApiWebhookEndpoint::query()->find($delivery->webhook_endpoint_id);
                $event = ApiEvent::query()->find($delivery->api_event_id);
                if (! $endpoint || ! $event) {
                    return;
                }

                $delivery->forceFill(['status' => 'retrying', 'attempts' => $delivery->attempts + 1])->save();
                $retry = $this->deliverWebhook($endpoint, $event, $delivery->payload ?? []);
                $delivery->forceFill([
                    'status' => $retry->status,
                    'response_status' => $retry->response_status,
                    'response_body' => $retry->response_body,
                    'next_retry_at' => $retry->next_retry_at,
                ])->save();
                $retry->delete();
                $count++;
            });

        return $count;
    }

    public function disableUnhealthyWebhook(ApiWebhookEndpoint $endpoint): bool
    {
        $recentFailures = $endpoint->deliveries()->latest()->limit(5)->pluck('status')->filter(fn ($status) => $status !== 'success')->count();
        if ($recentFailures < 5) {
            return false;
        }

        $endpoint->forceFill(['status' => 'inactive'])->save();

        return true;
    }

    public function plainSecret(ApiWebhookEndpoint $endpoint): string
    {
        try {
            return Crypt::decryptString($endpoint->secret);
        } catch (\Throwable) {
            return $endpoint->secret;
        }
    }
}
