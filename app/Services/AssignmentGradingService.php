<?php

namespace App\Services;

use App\Models\AssignmentEvent;
use App\Models\AssignmentGrade;
use App\Models\AssignmentSubmission;

class AssignmentGradingService
{
    public function __construct(private readonly RubricService $rubrics, private readonly AssignmentAIService $ai, private readonly LearningPathAssignmentIntegrationService $learningPath) {}

    public function gradeSubmission(AssignmentSubmission $submission, array $data, int $gradedBy): AssignmentGrade
    {
        $assignment = $submission->assignment()->with('rubric.criteria')->firstOrFail();
        $score = array_key_exists('rubric_breakdown', $data) && $assignment->rubric
            ? $this->rubrics->calculateScore($assignment->rubric, $data['rubric_breakdown'] ?? [])
            : (float) ($data['score'] ?? 0);
        $score = min((float) $assignment->max_score, max(0, $score));

        $grade = AssignmentGrade::query()->updateOrCreate(
            ['tenant_id'=>$submission->tenant_id,'submission_id'=>$submission->id],
            [
                'assignment_id' => $submission->assignment_id,
                'user_id' => $submission->user_id,
                'score' => $score,
                'max_score' => $assignment->max_score,
                'feedback' => $data['feedback'] ?? null,
                'rubric_breakdown' => $data['rubric_breakdown'] ?? null,
                'ai_suggested_score' => $data['ai_suggested_score'] ?? $this->ai->suggestScorePlaceholder($submission),
                'ai_feedback' => $data['ai_feedback'] ?? $this->ai->generateFeedbackPlaceholder($submission),
                'grading_status' => $data['grading_status'] ?? 'final',
                'graded_by' => $gradedBy,
            ]
        );

        $submission->forceFill(['status'=>'graded','total_score'=>$score,'feedback'=>$grade->feedback,'graded_by'=>$gradedBy,'graded_at'=>now()])->save();
        $this->event($submission, $gradedBy, 'graded');
        if ($grade->grading_status === 'final') {
            $this->learningPath->syncGradeCompletion($grade->fresh('assignment'));
        }
        return $grade->fresh();
    }

    public function returnForRevision(AssignmentSubmission $submission, string $feedback, int $teacherId): AssignmentSubmission
    {
        $submission->forceFill(['status'=>'returned','feedback'=>$feedback,'graded_by'=>$teacherId,'graded_at'=>now()])->save();
        $this->event($submission, $teacherId, 'returned', ['feedback' => $feedback]);
        return $submission;
    }

    public function approveGrade(AssignmentGrade $grade, int $approvedBy): AssignmentGrade
    {
        $grade->forceFill(['grading_status'=>'approved','approved_by'=>$approvedBy,'approved_at'=>now()])->save();
        $this->event($grade->submission, $approvedBy, 'approved');
        $this->learningPath->syncGradeCompletion($grade->fresh('assignment'));
        return $grade;
    }

    private function event(AssignmentSubmission $submission, int $userId, string $type, array $metadata = []): void
    {
        AssignmentEvent::query()->create(['tenant_id'=>$submission->tenant_id,'assignment_id'=>$submission->assignment_id,'submission_id'=>$submission->id,'user_id'=>$userId,'event_type'=>$type,'metadata'=>$metadata,'created_at'=>now()]);
    }
}
