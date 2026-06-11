<?php

namespace App\Services;

use App\Models\GradeChangeLog;
use App\Models\Gradebook;
use App\Models\LearnerGrade;

class GradeAuditService
{
    public function logGradeChange(Gradebook $gradebook, int $userId, ?LearnerGrade $before, ?LearnerGrade $after, int $actorId, ?string $reason = null): GradeChangeLog
    {
        return GradeChangeLog::query()->create([
            'tenant_id' => $gradebook->tenant_id,
            'gradebook_id' => $gradebook->id,
            'grade_item_id' => $after?->grade_item_id ?? $before?->grade_item_id,
            'user_id' => $userId,
            'before' => $before?->only(['raw_score','final_score','letter_grade','pass_status','source_status','feedback']),
            'after' => $after?->only(['raw_score','final_score','letter_grade','pass_status','source_status','feedback']),
            'reason' => $reason,
            'actor_id' => $actorId,
            'created_at' => now(),
        ]);
    }
}
