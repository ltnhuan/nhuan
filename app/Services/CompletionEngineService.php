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
        return $completion;
    }

    public function markComponentProgress(int $tenantId, int $userId, CourseComponent $component, float $percent, array $metadata = []): LearningCompletion
    {
        $this->events->appendEvent(['tenant_id' => $tenantId, 'user_id' => $userId, 'course_id' => $component->course_id, 'section_id' => $component->section_id, 'component_id' => $component->id, 'event_type' => $component->component_type === 'video' ? 'video_progress' : 'content_viewed', 'event_value' => $percent, 'metadata' => $metadata]);
        return $this->upsertCompletion($tenantId, $userId, $component, ['status' => 'in_progress', 'progress_percent' => min(100, $percent)]);
    }

    public function markComponentCompleted(int $tenantId, int $userId, CourseComponent $component, array $evidence = []): LearningCompletion
    {
        $verified = $this->verifyCompletionByRules($component, $evidence);
        $completion = $this->upsertCompletion($tenantId, $userId, $component, [
            'status' => $verified ? 'completed' : ($component->config['manual_approval'] ?? false ? 'pending_approval' : 'failed'),
            'progress_percent' => $verified ? 100 : ($evidence['progress_percent'] ?? 0),
            'score' => $evidence['score'] ?? null,
            'completed_at' => $verified ? now() : null,
            'metadata' => $evidence,
        ]);

        if ($verified) {
            $this->events->appendEvent(['tenant_id' => $tenantId, 'user_id' => $userId, 'course_id' => $component->course_id, 'section_id' => $component->section_id, 'component_id' => $component->id, 'event_type' => 'component_completed', 'event_value' => 100, 'metadata' => $evidence]);
            Cache::forget("eralms:access:{$tenantId}:{$userId}:component:{$component->id}");
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
        ]);
    }

    public function verifyCompletionByRules(CourseComponent $component, array $evidence): bool
    {
        $config = $component->config ?? [];
        return match ($component->component_type) {
            'video' => ($evidence['watch_percent'] ?? 0) >= ($config['min_watch_percent'] ?? 90) && ! ($evidence['suspicious'] ?? false),
            'pdf', 'file' => ($evidence['confirmed'] ?? false) && ($evidence['read_seconds'] ?? 0) >= ($config['min_read_seconds'] ?? 0),
            'text' => ($evidence['scrolled_to_end'] ?? false) || ($evidence['confirmed'] ?? false),
            'quiz' => ($evidence['score'] ?? 0) >= ($config['min_score'] ?? 70),
            'assignment' => ($evidence['graded'] ?? false) && ($evidence['score'] ?? 0) >= ($config['min_score'] ?? 0),
            'live_session' => ($evidence['attended_minutes'] ?? 0) >= ($config['min_attended_minutes'] ?? 1),
            default => (bool) ($evidence['confirmed'] ?? true),
        };
    }

    private function upsertCompletion(int $tenantId, int $userId, CourseComponent $component, array $data): LearningCompletion
    {
        return LearningCompletion::query()->updateOrCreate(['tenant_id' => $tenantId, 'user_id' => $userId, 'course_id' => $component->course_id, 'component_id' => $component->id], $data + ['section_id' => $component->section_id, 'completion_type' => 'component', 'source' => 'system']);
    }
}
