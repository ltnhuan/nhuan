<?php

namespace App\Services;

use App\Models\LearningPathRule;

class LearningPathRuleService
{
    private const TARGET_TYPES = ['course_section', 'course_component', 'course'];
    private const RULE_TYPES = ['sequential', 'prerequisite', 'mastery', 'adaptive', 'date_lock', 'manual_approval'];
    private const REQUIREMENT_TYPES = [
        'component_completed',
        'section_completed',
        'course_completed',
        'quiz_score_min',
        'assignment_score_min',
        'video_watch_percent',
        'video_watch_minutes',
        'document_opened',
        'document_confirmed',
        'text_scrolled_to_end',
        'live_attended_minutes',
        'manual_approval',
        'date_after',
    ];

    public function createRule(array $data): LearningPathRule
    {
        $this->validateRuleEnvelope($data);
        $this->validateRuleConfig($data['config'] ?? []);
        return LearningPathRule::query()->create($data + ['is_active' => true]);
    }

    public function updateRule(LearningPathRule $rule, array $data): LearningPathRule
    {
        $this->validateRuleEnvelope($data, true);
        if (array_key_exists('config', $data)) {
            $this->validateRuleConfig($data['config']);
        }
        $rule->fill($data)->save();
        return $rule;
    }

    public function deleteRule(LearningPathRule $rule): void
    {
        $rule->delete();
    }

    public function getRulesForCourse(int $courseId)
    {
        return LearningPathRule::query()->where('course_id', $courseId)->where('is_active', true)->get();
    }

    public function validateRuleConfig(array $config): void
    {
        $behavior = $config['unlock_behavior'] ?? 'all_required';
        if (! in_array($behavior, ['all_required', 'any_required'], true)) {
            throw new \InvalidArgumentException('unlock_behavior chỉ hỗ trợ all_required hoặc any_required.');
        }

        foreach ($config['requires'] ?? [] as $requirement) {
            if (! isset($requirement['type'])) {
                throw new \InvalidArgumentException('Mỗi điều kiện cần có type.');
            }
            if (! in_array($requirement['type'], self::REQUIREMENT_TYPES, true)) {
                throw new \InvalidArgumentException("Điều kiện {$requirement['type']} chưa được hỗ trợ.");
            }
            $this->validateRequirement($requirement);
        }

        foreach ($config['adaptive_routes'] ?? [] as $route) {
            if (! isset($route['when'], $route['target_type'], $route['target_id'])) {
                throw new \InvalidArgumentException('Adaptive route cần có when, target_type và target_id.');
            }
            if (! in_array($route['target_type'], self::TARGET_TYPES, true)) {
                throw new \InvalidArgumentException('Adaptive route target_type không hợp lệ.');
            }
        }

        if ($this->hasCircularDependency($config)) {
            throw new \InvalidArgumentException('Rule có nguy cơ vòng lặp vì target đang yêu cầu chính nó.');
        }
    }

    public function cloneRulesFromCourse(int $sourceCourseId, int $targetCourseId, int $createdBy): int
    {
        return LearningPathRule::query()->where('course_id', $sourceCourseId)->get()->sum(function (LearningPathRule $rule) use ($targetCourseId, $createdBy) {
            $copy = $rule->replicate(['course_id', 'created_by']);
            $copy->course_id = $targetCourseId;
            $copy->created_by = $createdBy;
            $copy->save();
            return 1;
        });
    }

    public function previewRule(array $config): array
    {
        $this->validateRuleConfig($config);

        $requires = array_map(fn (array $requirement) => $this->describeRequirement($requirement), $config['requires'] ?? []);

        return [
            'behavior' => $config['unlock_behavior'] ?? 'all_required',
            'requirements' => $requires,
            'locked_message' => $config['message_locked'] ?? 'Bạn cần hoàn thành điều kiện tiên quyết để mở nội dung này.',
            'adaptive_routes' => $config['adaptive_routes'] ?? [],
        ];
    }

    private function validateRuleEnvelope(array $data, bool $partial = false): void
    {
        foreach (['tenant_id', 'course_id', 'target_type', 'rule_type', 'title'] as $field) {
            if (! $partial && ! array_key_exists($field, $data)) {
                throw new \InvalidArgumentException("Rule thiếu {$field}.");
            }
        }

        if (isset($data['target_type']) && ! in_array($data['target_type'], self::TARGET_TYPES, true)) {
            throw new \InvalidArgumentException('target_type không hợp lệ.');
        }

        if (isset($data['rule_type']) && ! in_array($data['rule_type'], self::RULE_TYPES, true)) {
            throw new \InvalidArgumentException('rule_type không hợp lệ.');
        }
    }

    private function validateRequirement(array $requirement): void
    {
        $type = $requirement['type'];
        $needsComponent = ['component_completed', 'quiz_score_min', 'assignment_score_min', 'video_watch_percent', 'video_watch_minutes'];
        if (in_array($type, $needsComponent, true) && empty($requirement['component_id'])) {
            throw new \InvalidArgumentException("Điều kiện {$type} cần component_id.");
        }

        if (in_array($type, ['quiz_score_min', 'assignment_score_min'], true) && ! isset($requirement['min_score'])) {
            throw new \InvalidArgumentException("Điều kiện {$type} cần min_score.");
        }

        if ($type === 'video_watch_percent' && ! isset($requirement['min_percent'])) {
            throw new \InvalidArgumentException('Điều kiện video_watch_percent cần min_percent.');
        }

        if (in_array($type, ['video_watch_minutes', 'live_attended_minutes'], true) && ! isset($requirement['min_minutes'])) {
            throw new \InvalidArgumentException("Điều kiện {$type} cần min_minutes.");
        }

        if ($type === 'date_after' && empty($requirement['unlock_at'])) {
            throw new \InvalidArgumentException('Điều kiện date_after cần unlock_at.');
        }
    }

    private function describeRequirement(array $requirement): string
    {
        return match ($requirement['type']) {
            'component_completed' => "Hoàn thành component #{$requirement['component_id']}",
            'quiz_score_min' => "Quiz #{$requirement['component_id']} đạt tối thiểu {$requirement['min_score']} điểm",
            'assignment_score_min' => "Assignment #{$requirement['component_id']} đạt tối thiểu {$requirement['min_score']} điểm",
            'video_watch_percent' => "Video #{$requirement['component_id']} xem tối thiểu {$requirement['min_percent']}%",
            'video_watch_minutes' => "Video #{$requirement['component_id']} xem tối thiểu {$requirement['min_minutes']} phút",
            'live_attended_minutes' => "Live session tham gia tối thiểu {$requirement['min_minutes']} phút",
            'manual_approval' => 'Cần giảng viên hoặc quản trị viên duyệt hoàn thành',
            'date_after' => "Mở sau {$requirement['unlock_at']}",
            default => $requirement['type'],
        };
    }

    private function hasCircularDependency(array $config): bool
    {
        $target = $config['target'] ?? null;
        if (! is_array($target)) {
            return false;
        }

        foreach ($config['requires'] ?? [] as $requirement) {
            if (($target['type'] ?? null) === 'course_component'
                && ($requirement['type'] ?? null) === 'component_completed'
                && (int) ($target['id'] ?? 0) === (int) ($requirement['component_id'] ?? 0)) {
                return true;
            }
        }

        return false;
    }
}
