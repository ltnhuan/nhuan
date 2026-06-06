<?php

namespace App\Services;

use App\Models\LearningCompletion;
use App\Models\ManualCompletionApproval;
use Illuminate\Support\Facades\Cache;

class ManualApprovalService
{
    public function __construct(private readonly ?CompletionEngineService $completionEngine = null)
    {
    }

    public function requestApproval(LearningCompletion $completion, int $requestedBy, ?string $note = null): ManualCompletionApproval
    {
        $completion->forceFill(['status' => 'pending_approval'])->save();
        return ManualCompletionApproval::query()->create(['tenant_id' => $completion->tenant_id, 'user_id' => $completion->user_id, 'course_id' => $completion->course_id, 'component_id' => $completion->component_id, 'section_id' => $completion->section_id, 'requested_by' => $requestedBy, 'status' => 'pending', 'note' => $note]);
    }

    public function approveCompletion(LearningCompletion $completion, int $approvedBy, ?string $note = null): ManualCompletionApproval
    {
        $completion->forceFill(['status' => 'completed', 'verified_by' => $approvedBy, 'completed_at' => now(), 'progress_percent' => 100])->save();
        $this->afterDecision($completion);

        return ManualCompletionApproval::query()->updateOrCreate(['tenant_id' => $completion->tenant_id, 'user_id' => $completion->user_id, 'course_id' => $completion->course_id, 'component_id' => $completion->component_id], ['approved_by' => $approvedBy, 'status' => 'approved', 'note' => $note, 'decided_at' => now()]);
    }

    public function rejectCompletion(LearningCompletion $completion, int $approvedBy, ?string $note = null): ManualCompletionApproval
    {
        $completion->forceFill(['status' => 'failed', 'verified_by' => $approvedBy])->save();
        $this->afterDecision($completion);

        return ManualCompletionApproval::query()->updateOrCreate(['tenant_id' => $completion->tenant_id, 'user_id' => $completion->user_id, 'course_id' => $completion->course_id, 'component_id' => $completion->component_id], ['approved_by' => $approvedBy, 'status' => 'rejected', 'note' => $note, 'decided_at' => now()]);
    }

    private function afterDecision(LearningCompletion $completion): void
    {
        if ($completion->component_id) {
            Cache::forget("eralms:access:{$completion->tenant_id}:{$completion->user_id}:course_component:{$completion->component_id}");
        }

        $this->completionEngine?->recalculateUserCourseProgress($completion->tenant_id, $completion->user_id, $completion->course_id);
    }
}
