<?php

namespace App\Services;

use App\Models\Rubric;
use App\Models\RubricCriterion;
use App\Models\RubricLevel;

class RubricService
{
    public function create(array $data): Rubric
    {
        return Rubric::query()->create($data + ['status' => 'draft']);
    }

    public function update(Rubric $rubric, array $data): Rubric
    {
        $rubric->fill($data)->save();
        return $rubric;
    }

    public function addCriterion(Rubric $rubric, array $data): RubricCriterion
    {
        return RubricCriterion::query()->create($data + ['tenant_id'=>$rubric->tenant_id,'rubric_id'=>$rubric->id,'sort_order'=>$rubric->criteria()->count() + 1]);
    }

    public function addLevel(RubricCriterion $criterion, array $data): RubricLevel
    {
        return RubricLevel::query()->create($data + ['tenant_id'=>$criterion->tenant_id,'criterion_id'=>$criterion->id,'sort_order'=>$criterion->levels()->count() + 1]);
    }

    public function calculateScore(Rubric $rubric, array $breakdown): float
    {
        $criteria = $rubric->criteria()->get()->keyBy('id');
        $score = 0;
        foreach ($breakdown as $criterionId => $value) {
            $criterion = $criteria->get((int) $criterionId);
            if (! $criterion) {
                continue;
            }
            $criterionScore = is_array($value) ? ($value['score'] ?? 0) : $value;
            $score += min((float) $criterion->max_score, max(0, (float) $criterionScore));
        }
        return round(min((float) $rubric->max_score, $score), 2);
    }
}
