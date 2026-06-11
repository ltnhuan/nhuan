<?php

namespace App\Services;

use App\Models\ExamResult;
use App\Models\AssignmentGrade;
use App\Models\Gradebook;
use App\Models\GradeItem;
use App\Models\LearnerGrade;
use App\Models\LearnerEligibilitySummary;

class GradeImportService
{
    public function __construct(private GradeAuditService $audit, private GradeFormulaService $formula, private GradebookService $gradebooks) {}

    public function pullSources(Gradebook $gradebook, int $actorId): array
    {
        $this->gradebooks->ensureEditable($gradebook);
        $imported = 0;
        foreach ($gradebook->items()->whereIn('source_type', ['quiz','assignment','attendance'])->get() as $item) {
            $imported += $this->pullItem($gradebook, $item, $actorId);
        }
        $this->formula->recalculate($gradebook);
        return ['imported' => $imported];
    }

    public function pullItem(Gradebook $gradebook, GradeItem $item, int $actorId): int
    {
        if ($item->source_type === 'quiz') {
            return $this->pullQuiz($gradebook, $item, $actorId);
        }
        if ($item->source_type === 'assignment') {
            return $this->pullAssignment($gradebook, $item, $actorId);
        }
        if ($item->source_type === 'attendance') {
            return $this->pullAttendance($gradebook, $item, $actorId);
        }
        return 0;
    }

    private function pullAttendance(Gradebook $gradebook, GradeItem $item, int $actorId): int
    {
        $count = 0;
        $summaries = LearnerEligibilitySummary::query()->where('tenant_id', $gradebook->tenant_id)->where('course_id', $gradebook->course_id)->when($gradebook->class_id, fn ($q) => $q->where('class_id', $gradebook->class_id))->get();
        foreach ($summaries as $summary) {
            $score = ((float) $summary->attendance_percent / 100) * (float) $item->max_score;
            $grade = LearnerGrade::query()->where(['tenant_id' => $gradebook->tenant_id, 'grade_item_id' => $item->id, 'user_id' => $summary->user_id])->first();
            if ($grade?->source_status === 'locked') continue;
            $before = $grade ? clone $grade : null;
            $grade = LearnerGrade::query()->updateOrCreate(
                ['tenant_id' => $gradebook->tenant_id, 'grade_item_id' => $item->id, 'user_id' => $summary->user_id],
                ['gradebook_id' => $gradebook->id, 'raw_score' => round($score, 2), 'final_score' => round($score, 2), 'feedback' => $summary->reason, 'source_status' => 'final', 'updated_by' => $actorId]
            );
            $this->audit->logGradeChange($gradebook, $summary->user_id, $before, $grade, $actorId, 'Pull attendance');
            $count++;
        }
        return $count;
    }

    private function pullAssignment(Gradebook $gradebook, GradeItem $item, int $actorId): int
    {
        $count = 0;
        $query = AssignmentGrade::query()->where('tenant_id', $gradebook->tenant_id)->whereIn('grading_status', ['final', 'approved']);
        if ($item->source_id) $query->where('assignment_id', $item->source_id);
        foreach ($query->get() as $result) {
            $score = $result->max_score > 0 ? ((float) $result->score / (float) $result->max_score) * (float) $item->max_score : 0;
            $grade = LearnerGrade::query()->where(['tenant_id' => $gradebook->tenant_id, 'grade_item_id' => $item->id, 'user_id' => $result->user_id])->first();
            if ($grade?->source_status === 'locked') continue;
            $before = $grade ? clone $grade : null;
            $grade = LearnerGrade::query()->updateOrCreate(
                ['tenant_id' => $gradebook->tenant_id, 'grade_item_id' => $item->id, 'user_id' => $result->user_id],
                ['gradebook_id' => $gradebook->id, 'raw_score' => round($score, 2), 'final_score' => round($score, 2), 'feedback' => $result->feedback, 'source_status' => 'final', 'updated_by' => $actorId]
            );
            $this->audit->logGradeChange($gradebook, $result->user_id, $before, $grade, $actorId, 'Pull assignment');
            $count++;
        }
        return $count;
    }

    private function pullQuiz(Gradebook $gradebook, GradeItem $item, int $actorId): int
    {
        $count = 0;
        $query = ExamResult::query()->where('tenant_id', $gradebook->tenant_id);
        if ($item->source_id) $query->where('exam_id', $item->source_id);
        foreach ($query->get() as $result) {
            $score = $result->max_score > 0 ? ((float) $result->score / (float) $result->max_score) * (float) $item->max_score : 0;
            $grade = LearnerGrade::query()->where(['tenant_id' => $gradebook->tenant_id, 'grade_item_id' => $item->id, 'user_id' => $result->user_id])->first();
            if ($grade?->source_status === 'locked') continue;
            $before = $grade ? clone $grade : null;
            $grade = LearnerGrade::query()->updateOrCreate(
                ['tenant_id' => $gradebook->tenant_id, 'grade_item_id' => $item->id, 'user_id' => $result->user_id],
                ['gradebook_id' => $gradebook->id, 'raw_score' => round($score, 2), 'final_score' => round($score, 2), 'source_status' => 'final', 'updated_by' => $actorId]
            );
            $this->audit->logGradeChange($gradebook, $result->user_id, $before, $grade, $actorId, 'Pull '.$item->source_type);
            $count++;
        }
        return $count;
    }

    private function pullSyntheticSource(Gradebook $gradebook, GradeItem $item, int $actorId): int
    {
        $users = LearnerGrade::query()->where('gradebook_id', $gradebook->id)->distinct()->pluck('user_id');
        $count = 0;
        foreach ($users as $userId) {
            $seed = crc32($item->source_type.'-'.$item->id.'-'.$userId);
            $score = round((((int) $seed % 36) + 65) / 100 * (float) $item->max_score, 2);
            $grade = LearnerGrade::query()->where(['tenant_id' => $gradebook->tenant_id, 'grade_item_id' => $item->id, 'user_id' => $userId])->first();
            if ($grade?->source_status === 'locked') continue;
            $before = $grade ? clone $grade : null;
            $grade = LearnerGrade::query()->updateOrCreate(
                ['tenant_id' => $gradebook->tenant_id, 'grade_item_id' => $item->id, 'user_id' => $userId],
                ['gradebook_id' => $gradebook->id, 'raw_score' => $score, 'final_score' => $score, 'source_status' => 'final', 'updated_by' => $actorId]
            );
            $this->audit->logGradeChange($gradebook, (int) $userId, $before, $grade, $actorId, 'Pull '.$item->source_type);
            $count++;
        }
        return $count;
    }
}
