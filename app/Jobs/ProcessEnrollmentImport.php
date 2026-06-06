<?php

namespace App\Jobs;

use App\Models\EnrollmentImportJob;
use App\Services\EnrollmentImportService;
use App\Support\PerformanceQueues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessEnrollmentImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $importJobId)
    {
        $this->onQueue(PerformanceQueues::SYNC_SIS);
    }

    public function handle(EnrollmentImportService $imports): void
    {
        $job = EnrollmentImportJob::query()->findOrFail($this->importJobId);
        $imports->process($job);
    }
}
