<?php

namespace App\Jobs;

use App\Support\PerformanceQueues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IssueCertificateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $certificateIssueId)
    {
        $this->onQueue(PerformanceQueues::CERTIFICATE);
    }

    public function handle(): void
    {
        Log::info('certificate_issue_dispatched', ['certificate_issue_id' => $this->certificateIssueId]);
    }
}
