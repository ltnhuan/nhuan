<?php

namespace App\Jobs;

use App\Support\PerformanceQueues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAssignmentSubmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $submissionId)
    {
        $this->onQueue(PerformanceQueues::ASSIGNMENT_PROCESSING);
    }

    public function handle(): void
    {
        Log::info('assignment_submission_processing_dispatched', ['submission_id' => $this->submissionId]);
    }
}
