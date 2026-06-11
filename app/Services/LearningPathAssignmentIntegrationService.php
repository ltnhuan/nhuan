<?php

namespace App\Services;

use App\Models\AssignmentGrade;
use App\Models\CourseComponent;

class LearningPathAssignmentIntegrationService
{
    public function __construct(private readonly CompletionEngineService $completion) {}

    public function syncGradeCompletion(AssignmentGrade $grade): void
    {
        $assignment = $grade->assignment;
        if (! $assignment->component_id) {
            return;
        }
        $component = CourseComponent::query()->find($assignment->component_id);
        if (! $component) {
            return;
        }
        $passScore = (float) ($assignment->pass_score ?? $assignment->max_score);
        if ((float) $grade->score < $passScore) {
            return;
        }
        $this->completion->markComponentCompleted($grade->tenant_id, $grade->user_id, $component, [
            'graded' => true,
            'score' => (float) $grade->score,
            'max_score' => (float) $grade->max_score,
            'progress_percent' => 100,
            'source' => 'assignment',
        ]);
    }
}
