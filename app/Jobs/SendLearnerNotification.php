<?php

namespace App\Jobs;

use App\Support\PerformanceQueues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendLearnerNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $userId, public readonly string $type)
    {
        $this->onQueue(PerformanceQueues::NOTIFICATION);
    }

    public function handle(): void
    {
        Log::info('learner_notification_dispatched', [
            'user_id' => $this->userId,
            'type' => $this->type,
        ]);
    }
}
