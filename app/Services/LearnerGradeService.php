<?php

namespace App\Services;

use App\Models\LearnerGrade;

class LearnerGradeService
{
    public function __construct(private GradebookService $gradebooks, private GradeAuditService $audit, private GradeFormulaService $formula) {}

    public function override(LearnerGrade $grade, float $score, int $actorId, ?string $reason = null, bool $allowLockedOverride = false): LearnerGrade
    {
        $gradebook = $grade->item->gradebook;
        $this->gradebooks->ensureEditable($gradebook, $allowLockedOverride);
        if ($grade->source_status === 'locked' && ! $allowLockedOverride) {
            throw new \RuntimeException('Điểm đã khóa, cần quyền override đặc biệt.');
        }
        $before = clone $grade;
        $score = min(max($score, 0), (float) $grade->item->max_score);
        $grade->forceFill(['raw_score' => $grade->raw_score ?? $score, 'final_score' => $score, 'source_status' => 'overridden', 'updated_by' => $actorId])->save();
        $this->audit->logGradeChange($gradebook, $grade->user_id, $before, $grade->fresh(), $actorId, $reason ?: 'Manual override');
        $this->formula->calculateLearner($gradebook, $gradebook->items()->get(), $grade->user_id);
        return $grade->fresh();
    }
}
