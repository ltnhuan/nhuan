<?php

namespace App\Services;

use App\Models\AssignmentSubmission;

class AssignmentAIService
{
    public function generateFeedbackPlaceholder(AssignmentSubmission $submission): string
    {
        $assignment = $submission->assignment;
        return "Gợi ý AI: Bài nộp bám yêu cầu '{$assignment->title}'. Giảng viên cần kiểm tra mức độ đầy đủ, tính chính xác và minh chứng trước khi duyệt nhận xét cuối.";
    }

    public function suggestScorePlaceholder(AssignmentSubmission $submission): float
    {
        $assignment = $submission->assignment;
        $hasContent = trim((string) $submission->content_text) !== '' || trim((string) $submission->content_url) !== '' || $submission->files()->exists();
        return $hasContent ? round((float) $assignment->max_score * 0.72, 2) : 0.0;
    }
}
