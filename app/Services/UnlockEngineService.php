<?php

namespace App\Services;

use App\Models\ContentUnlock;
use App\Models\CourseComponent;
use App\Models\CourseSection;
use App\Models\LearningCompletion;
use App\Models\LearningPathRule;
use App\Models\LmsUser;
use Illuminate\Support\Facades\Cache;

class UnlockEngineService
{
    public function canAccessComponent(LmsUser $user, CourseComponent $component): array
    {
        return Cache::remember("eralms:access:{$user->tenant_id}:{$user->id}:component:{$component->id}", 60, fn () => $this->evaluateRules($user, 'course_component', $component->id, $component->course_id));
    }

    public function canAccessSection(LmsUser $user, CourseSection $section): array
    {
        return $this->evaluateRules($user, 'course_section', $section->id, $section->course_id);
    }

    public function evaluateRules(LmsUser $user, string $targetType, int $targetId, int $courseId): array
    {
        $rules = LearningPathRule::query()->where('course_id', $courseId)->where('target_type', $targetType)->where('target_id', $targetId)->where('is_active', true)->get();
        foreach ($rules as $rule) {
            foreach ($rule->config['requires'] ?? [] as $requirement) {
                if (! $this->requirementSatisfied($user, $courseId, $requirement)) {
                    return ['allowed' => false, 'message' => $rule->config['message_locked'] ?? 'Bạn cần hoàn thành điều kiện tiên quyết để mở nội dung này.', 'rule_id' => $rule->id];
                }
            }
        }
        return ['allowed' => true, 'message' => null, 'rule_id' => null];
    }

    public function unlockContent(LmsUser $user, string $targetType, int $targetId, int $courseId, string $reason): ContentUnlock
    {
        return ContentUnlock::query()->updateOrCreate(['user_id' => $user->id, 'target_type' => $targetType, 'target_id' => $targetId], [
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'course_id' => $courseId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'is_unlocked' => true,
            'reason' => $reason,
            'unlocked_at' => now(),
        ]);
    }

    public function lockContent(LmsUser $user, string $targetType, int $targetId, int $courseId, string $reason): ContentUnlock
    {
        return ContentUnlock::query()->updateOrCreate(['user_id' => $user->id, 'target_type' => $targetType, 'target_id' => $targetId], [
            'tenant_id' => $user->tenant_id,
            'course_id' => $courseId,
            'is_unlocked' => false,
            'reason' => $reason,
            'locked_message' => $reason,
        ]);
    }

    public function getLockedMessage(LmsUser $user, string $targetType, int $targetId): ?string
    {
        return ContentUnlock::query()->where('user_id', $user->id)->where('target_type', $targetType)->where('target_id', $targetId)->value('locked_message');
    }

    private function requirementSatisfied(LmsUser $user, int $courseId, array $requirement): bool
    {
        return match ($requirement['type']) {
            'component_completed' => LearningCompletion::query()->where('user_id', $user->id)->where('course_id', $courseId)->where('component_id', $requirement['component_id'])->where('status', 'completed')->exists(),
            'quiz_score_min', 'assignment_score_min' => LearningCompletion::query()->where('user_id', $user->id)->where('component_id', $requirement['component_id'])->where('score', '>=', $requirement['min_score'])->exists(),
            'video_watch_percent' => LearningCompletion::query()->where('user_id', $user->id)->where('component_id', $requirement['component_id'])->where('progress_percent', '>=', $requirement['min_percent'])->exists(),
            default => false,
        };
    }
}
