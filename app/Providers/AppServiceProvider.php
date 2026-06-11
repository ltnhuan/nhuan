<?php

namespace App\Providers;

use App\Contracts\SISGradeSyncContract;
use App\Contracts\SISAttendanceSyncContract;
use App\Contracts\SISAdapterContract;
use App\Services\MockSISAdapter;
use App\Services\NullSISAttendanceSyncService;
use App\Services\NullSISGradeSyncService;
use App\Services\TenantContext;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->bind(SISAdapterContract::class, MockSISAdapter::class);
        $this->app->bind(SISAttendanceSyncContract::class, NullSISAttendanceSyncService::class);
        $this->app->bind(SISGradeSyncContract::class, NullSISGradeSyncService::class);
    }

    public function boot(): void
    {
        if (! (bool) config('eralms.performance.slow_query_enabled', false)) {
            return;
        }

        DB::listen(function (QueryExecuted $query): void {
            $threshold = (int) config('eralms.performance.slow_query_ms', 200);

            if ($query->time < $threshold) {
                return;
            }

            $payload = [
                'time_ms' => $query->time,
                'connection' => $query->connectionName,
            ];

            if ((bool) config('eralms.performance.slow_query_log_sql', false)) {
                $payload['sql'] = $query->sql;
            }

            Log::warning('slow_query', $payload);
        });
    }
}
