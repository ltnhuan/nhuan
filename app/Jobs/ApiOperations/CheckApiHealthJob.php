<?php

namespace App\Jobs\ApiOperations;

use App\Services\ApiOperations\ApiHealthService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckApiHealthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly ?int $tenantId = null)
    {
        $this->onQueue('api-health');
    }

    public function handle(ApiHealthService $health): void
    {
        $health->buildSnapshots($this->tenantId);
    }
}
