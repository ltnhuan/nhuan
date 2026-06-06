<?php

namespace App\Jobs;

use App\Support\PerformanceQueues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GradeQuizAttempt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $attemptId)
    {
        $this->onQueue(PerformanceQueues::QUIZ_GRADING);
    }

    public function handle(): void
    {
        Log::info('quiz_attempt_grading_dispatched', ['attempt_id' => $this->attemptId]);
    }
}
