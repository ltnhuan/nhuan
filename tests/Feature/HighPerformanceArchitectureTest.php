<?php

namespace Tests\Feature;

use App\Jobs\GradeQuizAttempt;
use App\Jobs\IssueCertificateJob;
use App\Jobs\ProcessAssignmentSubmission;
use App\Jobs\ProcessEnrollmentImport;
use App\Jobs\ProcessVideoAsset;
use App\Jobs\RebuildAnalyticsSnapshot;
use App\Jobs\RunAiTask;
use App\Jobs\SendLearnerNotification;
use App\Support\PerformanceQueues;
use PHPUnit\Framework\TestCase;

class HighPerformanceArchitectureTest extends TestCase
{
    public function test_required_performance_queues_are_declared(): void
    {
        $this->assertSame([
            'default',
            'video-processing',
            'quiz-grading',
            'assignment-processing',
            'analytics',
            'notification',
            'certificate',
            'sync-sis',
            'ai',
        ], PerformanceQueues::all());
    }

    public function test_jobs_are_bound_to_dedicated_queues(): void
    {
        $this->assertSame(PerformanceQueues::VIDEO_PROCESSING, (new ProcessVideoAsset(1))->queue);
        $this->assertSame(PerformanceQueues::QUIZ_GRADING, (new GradeQuizAttempt(1))->queue);
        $this->assertSame(PerformanceQueues::ASSIGNMENT_PROCESSING, (new ProcessAssignmentSubmission(1))->queue);
        $this->assertSame(PerformanceQueues::ANALYTICS, (new RebuildAnalyticsSnapshot(1))->queue);
        $this->assertSame(PerformanceQueues::NOTIFICATION, (new SendLearnerNotification(1, 'course'))->queue);
        $this->assertSame(PerformanceQueues::CERTIFICATE, (new IssueCertificateJob(1))->queue);
        $this->assertSame(PerformanceQueues::SYNC_SIS, (new ProcessEnrollmentImport(1))->queue);
        $this->assertSame(PerformanceQueues::AI, (new RunAiTask('summary'))->queue);
    }
}
