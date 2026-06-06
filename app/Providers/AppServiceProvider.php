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
        DB::listen(function (QueryExecuted $query): void {
            $threshold = (int) env('ERALMS_SLOW_QUERY_MS', 200);

            if ($query->time < $threshold) {
                return;
            }

            Log::warning('slow_query', [
                'time_ms' => $query->time,
                'connection' => $query->connectionName,
                'sql' => $query->sql,
            ]);
        });
    }
}
