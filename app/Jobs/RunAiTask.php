<?php

namespace App\Jobs;

use App\Support\PerformanceQueues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunAiTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $taskType, public readonly array $payload = [])
    {
        $this->onQueue(PerformanceQueues::AI);
    }

    public function handle(): void
    {
        Log::info('ai_task_dispatched', [
            'task_type' => $this->taskType,
            'payload_keys' => array_keys($this->payload),
        ]);
    }
}
