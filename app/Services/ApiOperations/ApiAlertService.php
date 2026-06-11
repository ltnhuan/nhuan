<?php

namespace App\Services\ApiOperations;

use App\Models\ApiAuditLog;
use App\Models\ApiEndpoint;
use App\Models\ApiHealthSnapshot;
use App\Models\ApiRequestLog;

class ApiAlertService
{
    public function detectHighErrorRate(int $tenantId, float $threshold = 10): array
    {
        $alerts = [];
        ApiEndpoint::query()->where('tenant_id', $tenantId)->get()->each(function (ApiEndpoint $endpoint) use (&$alerts, $threshold) {
            $total = ApiRequestLog::query()->where('endpoint_id', $endpoint->id)->where('created_at', '>=', now()->subHour())->count();
            if ($total === 0) {
                return;
            }
            $errors = ApiRequestLog::query()->where('endpoint_id', $endpoint->id)->where('created_at', '>=', now()->subHour())->where('status_code', '>=', 400)->count();
            $rate = ($errors / $total) * 100;
            if ($rate >= $threshold) {
                $alerts[] = $this->createActionItem($endpoint->tenant_id, 'critical', 'high_error_rate', "Endpoint {$endpoint->code} error rate {$rate}%");
            }
        });

        return $alerts;
    }

    public function detectSlowEndpoint(int $tenantId, int $thresholdMs = 500): array
    {
        return ApiHealthSnapshot::query()
            ->where('tenant_id', $tenantId)
            ->where('latency_ms', '>=', $thresholdMs)
            ->where('checked_at', '>=', now()->subHour())
            ->get()
            ->map(fn (ApiHealthSnapshot $snapshot) => $this->createActionItem($tenantId, 'high', 'slow_endpoint', "Endpoint {$snapshot->endpoint_id} latency {$snapshot->latency_ms}ms"))
            ->all();
    }

    public function detectRepeatedFailure(int $tenantId): array
    {
        return ApiRequestLog::query()
            ->where('tenant_id', $tenantId)
            ->where('status_code', '>=', 500)
            ->where('created_at', '>=', now()->subHour())
            ->selectRaw('endpoint_id, count(*) as total')
            ->groupBy('endpoint_id')
            ->having('total', '>=', 3)
            ->get()
            ->map(fn ($row) => $this->createActionItem($tenantId, 'high', 'repeated_failure', "Endpoint {$row->endpoint_id} has {$row->total} repeated failures."))
            ->all();
    }

    public function notifyAdmin(array $alert): array
    {
        return $alert + ['notified' => true, 'notified_at' => now()->toISOString()];
    }

    public function createActionItem(int $tenantId, string $severity, string $code, string $message): array
    {
        ApiAuditLog::query()->create([
            'tenant_id' => $tenantId,
            'action' => 'api_alert.created',
            'module' => 'alerts',
            'entity_type' => 'api_alert',
            'entity_id' => $code,
            'before' => null,
            'after' => compact('severity', 'code', 'message'),
            'created_at' => now(),
        ]);

        return compact('severity', 'code', 'message');
    }
}
