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
        return Cache::remember($this->cacheKey($user, 'course_component', $component->id), 60, fn () => $this->evaluateRules($user, 'course_component', $component->id, $component->course_id));
    }

    public function canAccessSection(LmsUser $user, CourseSection $section): array
    {
        return $this->evaluateRules($user, 'course_section', $section->id, $section->course_id);
    }

    public function evaluateRules(LmsUser $user, string $targetType, int $targetId, int $courseId): array
    {
        $override = ContentUnlock::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->latest('updated_at')
            ->first();

        if ($override?->is_unlocked) {
            return ['allowed' => true, 'message' => null, 'rule_id' => null, 'reason' => $override->reason];
        }

        if ($override && ! $override->is_unlocked) {
            return ['allowed' => false, 'message' => $override->locked_message ?? $override->reason, 'rule_id' => null, 'reason' => $override->reason];
        }

        $rules = LearningPathRule::query()->where('course_id', $courseId)->where('target_type', $targetType)->where('target_id', $targetId)->where('is_active', true)->get();
        foreach ($rules as $rule) {
            $requirements = $rule->config['requires'] ?? [];
            if ($requirements === []) {
                continue;
            }

            $results = array_map(fn (array $requirement) => $this->requirementSatisfied($user, $courseId, $requirement), $requirements);
            $allowed = ($rule->config['unlock_behavior'] ?? 'all_required') === 'any_required'
                ? in_array(true, $results, true)
                : ! in_array(false, $results, true);

            if (! $allowed) {
                return [
                    'allowed' => false,
                    'message' => $rule->config['message_locked'] ?? $this->defaultLockedMessage($requirements),
                    'rule_id' => $rule->id,
                    'missing_requirements' => array_values(array_filter($requirements, fn (array $requirement, int $index) => ! $results[$index], ARRAY_FILTER_USE_BOTH)),
                ];
            }
        }
        return ['allowed' => true, 'message' => null, 'rule_id' => null, 'missing_requirements' => []];
    }

    public function unlockContent(LmsUser $user, string $targetType, int $targetId, int $courseId, string $reason): ContentUnlock
    {
        Cache::forget($this->cacheKey($user, $targetType, $targetId));

        return ContentUnlock::query()->updateOrCreate(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'target_type' => $targetType, 'target_id' => $targetId], [
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
        Cache::forget($this->cacheKey($user, $targetType, $targetId));

        return ContentUnlock::query()->updateOrCreate(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'target_type' => $targetType, 'target_id' => $targetId], [
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'course_id' => $courseId,
            'is_unlocked' => false,
            'reason' => $reason,
            'locked_message' => $reason,
        ]);
    }

    public function getLockedMessage(LmsUser $user, string $targetType, int $targetId): ?string
    {
        return ContentUnlock::query()->where('tenant_id', $user->tenant_id)->where('user_id', $user->id)->where('target_type', $targetType)->where('target_id', $targetId)->value('locked_message');
    }

    private function requirementSatisfied(LmsUser $user, int $courseId, array $requirement): bool
    {
        return match ($requirement['type'] ?? null) {
            'component_completed' => $this->completedQuery($user, $courseId)->where('component_id', $requirement['component_id'])->where('status', 'completed')->exists(),
            'section_completed' => $this->completedQuery($user, $courseId)->where('section_id', $requirement['section_id'])->where('completion_type', 'section')->where('status', 'completed')->exists(),
            'course_completed' => $this->completedQuery($user, (int) ($requirement['course_id'] ?? $courseId))->where('completion_type', 'course')->where('status', 'completed')->exists(),
            'quiz_score_min', 'assignment_score_min' => $this->completedQuery($user, $courseId)->where('component_id', $requirement['component_id'])->where('score', '>=', $requirement['min_score'])->whereIn('status', ['completed', 'passed'])->exists(),
            'video_watch_percent' => $this->completedQuery($user, $courseId)->where('component_id', $requirement['component_id'])->where('progress_percent', '>=', $requirement['min_percent'])->where('status', '!=', 'failed')->exists(),
            'document_opened' => $this->completedQuery($user, $courseId)->where('component_id', $requirement['component_id'])->where('progress_percent', '>', 0)->exists(),
            'manual_approval' => $this->completedQuery($user, $courseId)->where('component_id', $requirement['component_id'] ?? null)->where('status', 'completed')->whereNotNull('verified_by')->exists(),
            'date_after' => now()->greaterThanOrEqualTo($requirement['unlock_at']),
            default => false,
        };
    }

    private function completedQuery(LmsUser $user, int $courseId)
    {
        return LearningCompletion::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('course_id', $courseId);
    }

    private function defaultLockedMessage(array $requirements): string
    {
        $first = $requirements[0] ?? [];

        return match ($first['type'] ?? null) {
            'component_completed' => 'Bạn cần hoàn thành bài học trước để mở nội dung này.',
            'quiz_score_min' => "Bạn cần đạt tối thiểu {$first['min_score']} điểm ở quiz điều kiện để mở nội dung này.",
            'video_watch_percent' => "Bạn cần xem tối thiểu {$first['min_percent']}% video điều kiện để mở nội dung này.",
            'manual_approval' => 'Bạn cần giảng viên hoặc quản trị viên duyệt hoàn thành để mở nội dung này.',
            'date_after' => "Nội dung sẽ mở sau {$first['unlock_at']}.",
            default => 'Bạn cần hoàn thành điều kiện tiên quyết để mở nội dung này.',
        };
    }

    private function cacheKey(LmsUser $user, string $targetType, int $targetId): string
    {
        return "eralms:access:{$user->tenant_id}:{$user->id}:{$targetType}:{$targetId}";
    }
}
