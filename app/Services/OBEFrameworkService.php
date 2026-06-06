<?php

namespace App\Services;

use App\Models\CompetencyFramework;
use App\Models\CompetencyFrameworkItem;
use App\Models\LearningOutcome;
use App\Models\OutcomeMapping;

class OBEFrameworkService
{
    public function createOutcome(array $data): LearningOutcome
    {
        return LearningOutcome::query()->create($data + ['status' => 'active']);
    }

    public function updateOutcome(LearningOutcome $outcome, array $data): LearningOutcome
    {
        $outcome->fill($data)->save();
        return $outcome->fresh();
    }

    public function createFramework(array $data): CompetencyFramework
    {
        return CompetencyFramework::query()->create($data + ['framework_type' => 'OBE', 'standard' => 'AUN-QA', 'status' => 'active', 'settings' => []]);
    }

    public function createFrameworkItem(CompetencyFramework $framework, array $data): CompetencyFrameworkItem
    {
        return CompetencyFrameworkItem::query()->create($data + ['tenant_id' => $framework->tenant_id, 'framework_id' => $framework->id, 'item_type' => 'competency', 'status' => 'active']);
    }

    public function mapOutcomePath(array $data): OutcomeMapping
    {
        return OutcomeMapping::query()->updateOrCreate(
            [
                'tenant_id' => $data['tenant_id'],
                'source_type' => $data['source_type'],
                'source_id' => $data['source_id'] ?? null,
                'target_type' => $data['target_type'],
                'target_id' => $data['target_id'] ?? null,
                'source_outcome_id' => $data['source_outcome_id'] ?? null,
                'target_outcome_id' => $data['target_outcome_id'] ?? null,
            ],
            collect($data)->only(['competency_item_id','weight','evidence_level','metadata'])->all() + ['weight' => 1, 'evidence_level' => 'introduced']
        );
    }
}
