<?php

namespace App\Jobs\ApiOperations;

use App\Services\ApiOperations\EventBusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchApiEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $apiEventId)
    {
        $this->onQueue('api-events');
    }

    public function handle(EventBusService $events): void
    {
        $events->consumeEvent($this->apiEventId);
    }
}
