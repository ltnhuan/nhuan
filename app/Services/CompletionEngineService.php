<?php

namespace App\Services;

use App\Models\CourseComponent;
use App\Models\LearningCompletion;
use App\Models\UserCourseProgress;
use Illuminate\Support\Facades\Cache;

class CompletionEngineService
{
    public function __construct(private readonly LearningEventService $events)
    {
    }

    public function markComponentStarted(int $tenantId, int $userId, CourseComponent $component): LearningCompletion
    {
        $completion = $this->upsertCompletion($tenantId, $userId, $component, ['status' => 'in_progress', 'progress_percent' => 0]);
        $this->events->appendEvent(['tenant_id' => $tenantId, 'user_id' => $userId, 'course_id' => $component->course_id, 'section_id' => $component->section_id, 'component_id' => $component->id, 'event_type' => 'lesson_opened']);
        $this->touchUserCourseProgress($tenantId, $userId, $component);
        return $completion;
    }

    public function markComponentProgress(int $tenantId, int $userId, CourseComponent $component, float $percent, array $metadata = []): LearningCompletion
    {
        $event = [
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'course_id' => $component->course_id,
            'section_id' => $component->section_id,
            'component_id' => $component->id,
            'event_type' => $component->component_type === 'video' ? 'video_progress' : 'content_viewed',
            'event_value' => $percent,
            'metadata' => $metadata,
        ];
        $suspicious = $this->events->detectFakeProgress($event);
        $this->events->appendEvent($event);

        $safePercent = $suspicious ? (float) ($metadata['previous_percent'] ?? 0) : min(100, max(0, $percent));
        $completion = $this->upsertCompletion($tenantId, $userId, $component, [
            'status' => 'in_progress',
            'progress_percent' => $safePercent,
            'metadata' => ['suspicious' => $suspicious] + $metadata,
        ]);
        $this->touchUserCourseProgress($tenantId, $userId, $component);

        return $completion;
    }

    public function markComponentCompleted(int $tenantId, int $userId, CourseComponent $component, array $evidence = []): LearningCompletion
    {
        $evidence['suspicious'] = (bool) ($evidence['suspicious'] ?? false);
        $verified = $this->verifyCompletionByRules($component, $evidence);
        $pendingApproval = (bool) (($component->config ?? [])['manual_approval'] ?? false) || ($evidence['manual_approval_required'] ?? false);
        $completion = $this->upsertCompletion($tenantId, $userId, $component, [
            'status' => $verified ? 'completed' : ($pendingApproval ? 'pending_approval' : $this->failedStatus($component, $evidence)),
            'progress_percent' => $verified ? 100 : ($evidence['progress_percent'] ?? 0),
            'score' => $evidence['score'] ?? null,
            'completed_at' => $verified ? now() : null,
            'metadata' => $evidence,
        ]);

        if ($verified) {
            $this->events->appendEvent(['tenant_id' => $tenantId, 'user_id' => $userId, 'course_id' => $component->course_id, 'section_id' => $component->section_id, 'component_id' => $component->id, 'event_type' => 'component_completed', 'event_value' => 100, 'metadata' => $evidence]);
            Cache::forget("eralms:access:{$tenantId}:{$userId}:course_component:{$component->id}");
        }

        $this->recalculateUserCourseProgress($tenantId, $userId, $component->course_id);
        return $completion;
    }

    public function calculateSectionCompletion(int $tenantId, int $userId, int $courseId, int $sectionId): float
    {
        $total = CourseComponent::query()->where('section_id', $sectionId)->where('required', true)->count();
        if ($total === 0) {
            return 0;
        }
        $completed = LearningCompletion::query()->where('tenant_id', $tenantId)->where('user_id', $userId)->where('course_id', $courseId)->where('section_id', $sectionId)->where('status', 'completed')->count();
        return round($completed / $total * 100, 2);
    }

    public function calculateCourseCompletion(int $tenantId, int $userId, int $courseId): float
    {
        return $this->recalculateUserCourseProgress($tenantId, $userId, $courseId)->progress_percent;
    }

    public function recalculateUserCourseProgress(int $tenantId, int $userId, int $courseId): UserCourseProgress
    {
        $total = CourseComponent::query()->where('course_id', $courseId)->where('required', true)->count();
        $completed = LearningCompletion::query()->where('tenant_id', $tenantId)->where('user_id', $userId)->where('course_id', $courseId)->where('status', 'completed')->count();
        $percent = $total > 0 ? round($completed / $total * 100, 2) : 0;
        return UserCourseProgress::query()->updateOrCreate(['tenant_id' => $tenantId, 'user_id' => $userId, 'course_id' => $courseId], [
            'status' => $percent >= 100 ? 'completed' : ($completed > 0 ? 'in_progress' : 'not_started'),
            'progress_percent' => $percent,
            'completed_components_count' => $completed,
            'total_components_count' => CourseComponent::query()->where('course_id', $courseId)->count(),
            'completed_required_count' => $completed,
            'total_required_count' => $total,
            'completed_at' => $percent >= 100 ? now() : null,
            'last_accessed_at' => now(),
            'risk_level' => $this->riskLevel($percent),
        ]);
    }

    public function verifyCompletionByRules(CourseComponent $component, array $evidence): bool
    {
        $config = $component->config ?? [];
        return match ($component->component_type) {
            'video' => $this->validVideoCompletion($config, $evidence),
            'pdf', 'file', 'document' => ($evidence['confirmed'] ?? false) && ($evidence['read_seconds'] ?? 0) >= ($config['min_read_seconds'] ?? 0),
            'text' => ($evidence['scrolled_to_end'] ?? false) || ($evidence['confirmed'] ?? false),
            'quiz' => ($evidence['score'] ?? 0) >= ($config['min_score'] ?? 70) && ($evidence['attempts'] ?? 1) <= ($config['max_attempts'] ?? PHP_INT_MAX),
            'assignment' => ($evidence['graded'] ?? false) && ($evidence['score'] ?? 0) >= ($config['min_score'] ?? 0),
            'live_session' => ($evidence['attended_minutes'] ?? 0) >= ($config['min_attended_minutes'] ?? 1),
            default => (bool) ($evidence['confirmed'] ?? false),
        };
    }

    private function upsertCompletion(int $tenantId, int $userId, CourseComponent $component, array $data): LearningCompletion
    {
        return LearningCompletion::query()->updateOrCreate(['tenant_id' => $tenantId, 'user_id' => $userId, 'course_id' => $component->course_id, 'component_id' => $component->id], $data + ['section_id' => $component->section_id, 'completion_type' => 'component', 'source' => 'system']);
    }

    private function validVideoCompletion(array $config, array $evidence): bool
    {
        if ($evidence['suspicious'] ?? false) {
            return false;
        }

        $watchPercent = (float) ($evidence['watch_percent'] ?? $evidence['progress_percent'] ?? 0);
        $watchSeconds = (int) ($evidence['watch_seconds'] ?? 0);
        $minPercent = (float) ($config['min_watch_percent'] ?? 90);
        $minSeconds = (int) ($config['min_watch_seconds'] ?? 0);

        return $watchPercent >= $minPercent && $watchSeconds >= $minSeconds;
    }

    private function failedStatus(CourseComponent $component, array $evidence): string
    {
        if ($component->component_type === 'quiz' && ($evidence['allow_retry'] ?? true)) {
            return 'in_progress';
        }

        return 'failed';
    }

    private function riskLevel(float $percent): string
    {
        return match (true) {
            $percent >= 80 => 'low',
            $percent >= 50 => 'medium',
            $percent >= 20 => 'high',
            default => 'critical',
        };
    }

    private function touchUserCourseProgress(int $tenantId, int $userId, CourseComponent $component): void
    {
        UserCourseProgress::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $userId, 'course_id' => $component->course_id],
            [
                'status' => 'in_progress',
                'last_component_id' => $component->id,
                'last_accessed_at' => now(),
            ]
        );
    }
}
