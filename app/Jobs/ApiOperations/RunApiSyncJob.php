<?php

namespace App\Jobs\ApiOperations;

use App\Services\ApiOperations\SyncJobService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunApiSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $syncJobId)
    {
        $this->onQueue('api-sync');
    }

    public function handle(SyncJobService $syncJobs): void
    {
        $syncJobs->runSyncJob($this->syncJobId);
    }
}
