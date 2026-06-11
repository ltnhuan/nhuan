<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class ApiPerformanceLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('eralms_started_at', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $startedAt = (float) $request->attributes->get('eralms_started_at', microtime(true));
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $memoryMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        if ($durationMs >= (int) config('eralms.performance.api_latency_warn_ms', 500)) {
            $payload = [
                'method' => $request->method(),
                'path' => $request->path(),
                'status' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
                'memory_mb' => $memoryMb,
                'tenant' => $request->header(config('eralms.tenant_header', 'X-Tenant-Code')),
            ];

            Log::channel(config('eralms.performance.log_channel', 'stack'))->warning('api_latency_warning', [
                ...$payload,
            ]);

            $this->insertLatencyLog($payload);
        }

        if ($memoryMb >= (int) config('eralms.performance.memory_warn_mb', 512)) {
            Log::warning('memory_warning', [
                'path' => $request->path(),
                'memory_mb' => $memoryMb,
            ]);
        }
    }

    private function insertLatencyLog(array $payload): void
    {
        try {
            if (! (bool) config('eralms.performance.api_latency_db_log', false)) {
                return;
            }

            if (! Schema::hasTable('api_latency_logs')) {
                return;
            }

            DB::table('api_latency_logs')->insert([
                'method' => $payload['method'],
                'path' => $payload['path'],
                'status_code' => $payload['status'],
                'duration_ms' => $payload['duration_ms'],
                'memory_mb' => $payload['memory_mb'],
                'metadata' => json_encode(['tenant_code' => $payload['tenant']]),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            //
        }
    }
}
