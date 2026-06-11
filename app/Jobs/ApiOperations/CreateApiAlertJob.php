<?php

namespace App\Jobs\ApiOperations;

use App\Services\ApiOperations\ApiAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateApiAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $tenantId)
    {
        $this->onQueue('api-alerts');
    }

    public function handle(ApiAlertService $alerts): void
    {
        $alerts->detectHighErrorRate($this->tenantId);
        $alerts->detectSlowEndpoint($this->tenantId);
        $alerts->detectRepeatedFailure($this->tenantId);
    }
}
