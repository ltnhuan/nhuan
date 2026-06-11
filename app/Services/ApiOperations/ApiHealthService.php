<?php

namespace App\Services\ApiOperations;

use App\Models\ApiEndpoint;
use App\Models\ApiEvent;
use App\Models\ApiHealthSnapshot;
use App\Models\ApiRequestLog;
use App\Models\ApiSyncJob;
use App\Models\ApiSystem;
use App\Models\ApiWebhookDelivery;
use Illuminate\Support\Facades\DB;

class ApiHealthService
{
    public function pingSystem(ApiSystem $system): ApiHealthSnapshot
    {
        $latency = 20 + (crc32($system->code) % 600);
        $status = match ($system->status) {
            'inactive', 'maintenance' => 'down',
            'error' => 'degraded',
            default => $latency > 450 ? 'degraded' : 'healthy',
        };

        return $this->createHealthSnapshot($system->tenant_id, $system->id, null, $status, $latency, $status === 'healthy' ? 99.5 : 82, $status === 'healthy' ? 0.5 : 18);
    }

    public function checkEndpoint(ApiEndpoint $endpoint): ApiHealthSnapshot
    {
        $successRate = $this->calculateSuccessRate($endpoint);
        $errorRate = max(0, 100 - $successRate);
        $latency = $this->calculateLatency($endpoint) ?? (30 + (crc32($endpoint->code) % 750));
        $status = $endpoint->status === 'inactive' || $errorRate > 50 ? 'down' : ($endpoint->status === 'deprecated' || $latency > 500 || $errorRate > 10 ? 'degraded' : 'healthy');

        return $this->createHealthSnapshot($endpoint->tenant_id, $endpoint->system_id, $endpoint->id, $status, $latency, $successRate, $errorRate);
    }

    public function calculateSuccessRate(ApiEndpoint $endpoint): float
    {
        $query = ApiRequestLog::query()->where('endpoint_id', $endpoint->id)->latest()->limit(100);
        $total = (clone $query)->count();
        if ($total === 0) {
            return 100.0;
        }

        $success = (clone $query)->whereBetween('status_code', [200, 399])->count();

        return round(($success / $total) * 100, 2);
    }

    public function calculateLatency(ApiEndpoint $endpoint): ?int
    {
        $average = ApiRequestLog::query()->where('endpoint_id', $endpoint->id)->whereNotNull('duration_ms')->avg('duration_ms');

        return $average === null ? null : (int) round($average);
    }

    public function detectDegradedSystem(ApiSystem $system): bool
    {
        return ApiHealthSnapshot::query()
            ->where('tenant_id', $system->tenant_id)
            ->where('system_id', $system->id)
            ->where('status', '!=', 'healthy')
            ->where('checked_at', '>=', now()->subHour())
            ->exists();
    }

    public function createHealthSnapshot(int $tenantId, int $systemId, ?int $endpointId, string $status, ?int $latencyMs, ?float $successRate, ?float $errorRate, array $metadata = []): ApiHealthSnapshot
    {
        return ApiHealthSnapshot::query()->create([
            'tenant_id' => $tenantId,
            'system_id' => $systemId,
            'endpoint_id' => $endpointId,
            'status' => $status,
            'latency_ms' => $latencyMs,
            'success_rate' => $successRate,
            'error_rate' => $errorRate,
            'checked_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    public function buildSnapshots(?int $tenantId = null): array
    {
        $systemCount = 0;
        $endpointCount = 0;
        ApiSystem::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->get()
            ->each(function (ApiSystem $system) use (&$systemCount, &$endpointCount) {
                $this->pingSystem($system);
                $systemCount++;
                $system->endpoints()->get()->each(function (ApiEndpoint $endpoint) use (&$endpointCount) {
                    $this->checkEndpoint($endpoint);
                    $endpointCount++;
                });
            });

        return ['systems_checked' => $systemCount, 'endpoints_checked' => $endpointCount];
    }

    public function dashboard(int $tenantId): array
    {
        $today = now()->startOfDay();
        $latestHealth = ApiHealthSnapshot::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', function ($query) use ($tenantId) {
                $query->selectRaw('max(id)')
                    ->from('api_health_snapshots')
                    ->where('tenant_id', $tenantId)
                    ->groupBy('system_id', 'endpoint_id');
            });

        $requestTotal = ApiRequestLog::query()->where('tenant_id', $tenantId)->where('created_at', '>=', $today)->count();
        $requestErrors = ApiRequestLog::query()->where('tenant_id', $tenantId)->where('created_at', '>=', $today)->where('status_code', '>=', 400)->count();

        return [
            'cards' => [
                'systems_total' => ApiSystem::query()->where('tenant_id', $tenantId)->count(),
                'healthy' => (clone $latestHealth)->where('status', 'healthy')->count(),
                'degraded' => (clone $latestHealth)->where('status', 'degraded')->count(),
                'down' => (clone $latestHealth)->where('status', 'down')->count(),
                'requests_today' => $requestTotal,
                'error_rate' => $requestTotal > 0 ? round(($requestErrors / $requestTotal) * 100, 2) : 0,
                'sync_jobs_failed' => ApiSyncJob::query()->where('tenant_id', $tenantId)->where('status', 'failed')->count(),
                'webhook_failed' => ApiWebhookDelivery::query()->where('tenant_id', $tenantId)->where('status', 'failed')->count(),
                'event_pending' => ApiEvent::query()->where('tenant_id', $tenantId)->whereIn('status', ['pending', 'retrying'])->count(),
                'average_latency' => (int) ApiHealthSnapshot::query()->where('tenant_id', $tenantId)->avg('latency_ms'),
                'alert_critical' => (clone $latestHealth)->where('status', 'down')->count(),
            ],
            'charts' => [
                'success_fail_trend' => $this->requestTrend($tenantId),
                'latency_trend' => $this->latencyTrend($tenantId),
                'error_by_system' => $this->errorsBySystem($tenantId),
                'sync_job_status' => ApiSyncJob::query()->where('tenant_id', $tenantId)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
                'webhook_delivery_status' => ApiWebhookDelivery::query()->where('tenant_id', $tenantId)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            ],
        ];
    }

    public function cleanupLogs(int $tenantId, int $days = 30): int
    {
        return ApiRequestLog::query()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }

    private function requestTrend(int $tenantId): array
    {
        return ApiRequestLog::query()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw("date(created_at) as day, sum(case when status_code between 200 and 399 then 1 else 0 end) as success, sum(case when status_code >= 400 then 1 else 0 end) as failed")
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->toArray();
    }

    private function latencyTrend(int $tenantId): array
    {
        return ApiHealthSnapshot::query()
            ->where('tenant_id', $tenantId)
            ->where('checked_at', '>=', now()->subDays(7))
            ->selectRaw('date(checked_at) as day, avg(latency_ms) as latency_ms')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->toArray();
    }

    private function errorsBySystem(int $tenantId): array
    {
        return DB::table('api_requests')
            ->join('api_systems', 'api_requests.system_id', '=', 'api_systems.id')
            ->where('api_requests.tenant_id', $tenantId)
            ->where('api_requests.status_code', '>=', 400)
            ->selectRaw('api_systems.code as system, count(*) as total')
            ->groupBy('api_systems.code')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->toArray();
    }
}
