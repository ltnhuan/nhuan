<?php

namespace App\Services;

use App\Models\AssessmentOutcomeMapping;

class AssessmentMappingService
{
    public function mapAssessment(int $tenantId, string $type, int $assessmentId, array $outcomes): array
    {
        $mapped = [];
        foreach ($outcomes as $outcome) {
            $mapped[] = AssessmentOutcomeMapping::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'assessment_type' => $type, 'assessment_id' => $assessmentId, 'outcome_id' => $outcome['outcome_id']],
                ['method' => $outcome['method'] ?? 'direct', 'weight' => $outcome['weight'] ?? 1, 'max_score' => $outcome['max_score'] ?? null, 'rubric_criteria' => $outcome['rubric_criteria'] ?? [], 'status' => 'active']
            );
        }
        return $mapped;
    }
}
