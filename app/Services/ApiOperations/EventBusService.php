<?php

namespace App\Services\ApiOperations;

use App\Jobs\ApiOperations\DispatchApiEventJob;
use App\Models\ApiEvent;
use Illuminate\Support\Str;

class EventBusService
{
    public function publishEvent(int $tenantId, string $eventKey, string $entityType, array $payload = [], array $options = []): ApiEvent
    {
        $idempotencyKey = $options['idempotency_key'] ?? hash('sha256', $eventKey.'|'.$entityType.'|'.json_encode($payload));

        $event = ApiEvent::query()->firstOrCreate([
            'tenant_id' => $tenantId,
            'event_key' => $eventKey,
            'idempotency_key' => $idempotencyKey,
        ], [
            'source_system_id' => $options['source_system_id'] ?? null,
            'target_system_id' => $options['target_system_id'] ?? null,
            'entity_type' => $entityType,
            'entity_id' => $options['entity_id'] ?? null,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
        ]);

        if (($options['dispatch'] ?? false) === true && $event->wasRecentlyCreated) {
            $this->dispatchToQueue($event);
        }

        return $event;
    }

    public function consumeEvent(ApiEvent|int $event): ApiEvent
    {
        $event = $event instanceof ApiEvent ? $event : ApiEvent::query()->findOrFail($event);
        if (! in_array($event->status, ['pending', 'retrying', 'failed'], true)) {
            return $event;
        }

        $event->forceFill(['status' => 'processing', 'attempts' => $event->attempts + 1])->save();

        return $this->markSuccess($event);
    }

    public function checkIdempotency(int $tenantId, string $eventKey, string $idempotencyKey): bool
    {
        return ! ApiEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('event_key', $eventKey)
            ->where('idempotency_key', $idempotencyKey)
            ->exists();
    }

    public function dispatchToQueue(ApiEvent $event): void
    {
        DispatchApiEventJob::dispatch($event->id)->onQueue('api-events');
    }

    public function markSuccess(ApiEvent $event): ApiEvent
    {
        $event->forceFill([
            'status' => 'success',
            'error_message' => null,
            'next_retry_at' => null,
            'processed_at' => now(),
        ])->save();

        return $event->fresh();
    }

    public function markFailed(ApiEvent $event, string $message): ApiEvent
    {
        $event->forceFill([
            'status' => 'failed',
            'error_message' => $message,
            'processed_at' => now(),
        ])->save();

        return $event->fresh();
    }

    public function scheduleRetry(ApiEvent $event, int $delaySeconds = 60): ApiEvent
    {
        $event->forceFill([
            'status' => 'retrying',
            'attempts' => $event->attempts + 1,
            'next_retry_at' => now()->addSeconds($delaySeconds),
        ])->save();

        return $event->fresh();
    }

    public function retryFailed(int $tenantId, int $limit = 100): int
    {
        $count = 0;
        ApiEvent::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['failed', 'retrying'])
            ->where(fn ($query) => $query->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now()))
            ->limit($limit)
            ->get()
            ->each(function (ApiEvent $event) use (&$count) {
                $this->consumeEvent($event);
                $count++;
            });

        return $count;
    }

    public function nextIdempotencyKey(string $prefix = 'api-event'): string
    {
        return $prefix.'-'.Str::uuid();
    }
}
