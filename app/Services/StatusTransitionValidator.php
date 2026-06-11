<?php

namespace App\Services;

class StatusTransitionValidator
{
    private const ALLOWED = [
        'course' => [
            'draft' => ['review'],
            'review' => ['approved', 'draft'],
            'approved' => ['published', 'archived'],
            'published' => ['archived'],
            'archived' => [],
        ],
        'exam' => [
            'draft' => ['approved'],
            'approved' => ['published'],
            'published' => ['open', 'archived'],
            'open' => ['closed'],
            'closed' => ['archived'],
            'archived' => [],
        ],
        'attempt' => [
            'not_started' => ['in_progress'],
            'in_progress' => ['submitted', 'auto_submitted'],
            'submitted' => ['graded'],
            'auto_submitted' => ['graded'],
            'graded' => ['published'],
            'published' => [],
        ],
        'assignment' => [
            'draft' => ['published'],
            'published' => ['closed'],
            'closed' => ['archived'],
            'archived' => [],
        ],
        'submission' => [
            'draft' => ['submitted'],
            'submitted' => ['returned', 'graded'],
            'returned' => ['submitted', 'graded'],
            'graded' => ['approved'],
            'approved' => [],
        ],
        'gradebook' => [
            'draft' => ['active'],
            'active' => ['pending_approval'],
            'pending_approval' => ['approved', 'active'],
            'approved' => ['locked'],
            'locked' => ['synced_to_sis'],
            'synced_to_sis' => [],
        ],
        'attendance' => [
            'draft' => ['open'],
            'open' => ['closed'],
            'closed' => ['locked'],
            'locked' => [],
        ],
        'certificate' => [
            'draft' => ['issued'],
            'issued' => ['revoked'],
            'revoked' => [],
        ],
        'sync_job' => [
            'pending' => ['running'],
            'running' => ['success', 'failed', 'retrying'],
            'retrying' => ['running', 'failed'],
            'success' => [],
            'failed' => ['retrying'],
        ],
    ];

    public function assertAllowed(string $entity, ?string $from, string $to): void
    {
        if ($from === null || $from === $to) {
            return;
        }

        $allowed = self::ALLOWED[$entity][$from] ?? null;
        if ($allowed === null || ! in_array($to, $allowed, true)) {
            throw new \InvalidArgumentException("Chuyển trạng thái {$entity} từ {$from} sang {$to} không hợp lệ.");
        }
    }

    public function allowedMap(): array
    {
        return self::ALLOWED;
    }
}
