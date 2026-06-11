<?php

namespace App\Services;

use App\Models\CourseComponent;
use App\Models\VideoProgressSummary;

class LearningPathIntegrationService
{
    public function __construct(private readonly CompletionEngineService $completionEngine)
    {
    }

    public function completeVideoComponentIfEligible(VideoProgressSummary $summary): void
    {
        if (! $summary->is_completed) {
            return;
        }

        $component = CourseComponent::query()->find($summary->component_id);
        if (! $component) {
            return;
        }

        $this->completionEngine->markComponentCompleted($summary->tenant_id, $summary->user_id, $component, [
            'watch_percent' => (float) $summary->watch_percent,
            'watch_seconds' => (float) $summary->watched_seconds,
            'progress_percent' => (float) $summary->watch_percent,
            'suspicious' => (float) $summary->suspicious_score >= config('eralms.video.max_suspicious_score_for_completion', 50),
        ]);
    }
}
