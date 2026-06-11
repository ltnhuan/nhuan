<?php

namespace App\Jobs\ApiOperations;

use App\Services\ApiOperations\EventBusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryApiEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $limit = 100
    ) {
        $this->onQueue('api-retry');
    }

    public function handle(EventBusService $events): void
    {
        $events->retryFailed($this->tenantId, $this->limit);
    }
}
