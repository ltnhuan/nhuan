<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\PerformanceQueues;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    public function __invoke()
    {
        $checks = [
            'app' => $this->check(fn () => app()->environment()),
            'db' => $this->check(fn () => DB::select('select 1')),
            'redis' => $this->check(function () {
                if (config('cache.default') !== 'redis' && config('queue.default') !== 'redis') {
                    return 'not_configured';
                }

                return Redis::connection(config('queue.connections.redis.connection', 'default'))->ping();
            }),
            'queue' => $this->check(fn () => [
                'connection' => config('queue.default'),
                'queues' => PerformanceQueues::all(),
                'size_default' => Queue::size(PerformanceQueues::DEFAULT),
            ]),
            'storage' => $this->check(fn () => [
                'disk' => config('filesystems.default'),
                'exists' => Storage::disk(config('filesystems.default'))->exists('.healthcheck') || true,
            ]),
            'sis_connection' => $this->check(fn () => [
                'adapter' => config('services.sis.adapter', 'mock'),
                'status' => config('services.sis.enabled', false) ? 'configured' : 'mock',
            ]),
            'cdn_config' => $this->check(fn () => [
                'enabled' => (bool) config('eralms.cdn.enabled'),
                'url' => config('eralms.cdn.url'),
                'asset_url' => config('eralms.cdn.asset_url'),
            ]),
            'cache' => $this->check(fn () => [
                'store' => config('cache.default'),
                'probe' => Cache::remember('eralms:health:cache', 5, fn () => 'ok'),
            ]),
        ];

        $healthy = collect($checks)->every(fn ($check) => $check['ok']);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'generated_at' => now()->toISOString(),
        ], $healthy ? 200 : 503);
    }

    private function check(callable $callback): array
    {
        try {
            return ['ok' => true, 'value' => $callback()];
        } catch (\Throwable $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        }
    }
}
