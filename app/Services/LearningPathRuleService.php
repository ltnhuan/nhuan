<?php

namespace App\Services;

use App\Models\LearningPathRule;

class LearningPathRuleService
{
    public function createRule(array $data): LearningPathRule
    {
        $this->validateRuleConfig($data['config'] ?? []);
        return LearningPathRule::query()->create($data + ['is_active' => true]);
    }

    public function updateRule(LearningPathRule $rule, array $data): LearningPathRule
    {
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
        foreach ($config['requires'] ?? [] as $requirement) {
            if (! isset($requirement['type'])) {
                throw new \InvalidArgumentException('Mỗi điều kiện cần có type.');
            }
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
}
