<?php

namespace App\Jobs;

use App\Support\PerformanceQueues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RebuildAnalyticsSnapshot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly string $periodType = 'daily'
    ) {
        $this->onQueue(PerformanceQueues::ANALYTICS);
    }

    public function handle(): void
    {
        Log::info('analytics_snapshot_rebuild_dispatched', [
            'tenant_id' => $this->tenantId,
            'period_type' => $this->periodType,
        ]);
    }
}
