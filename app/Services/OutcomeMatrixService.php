<?php

namespace App\Services;

use App\Models\AssessmentOutcomeMapping;
use App\Models\LearningOutcome;
use App\Models\OutcomeMapping;

class OutcomeMatrixService
{
    public function matrix(int $tenantId, ?int $courseId = null): array
    {
        $plos = LearningOutcome::query()->where('tenant_id', $tenantId)->where('type', 'PLO')->where('status', 'active')->orderBy('code')->get();
        $clos = LearningOutcome::query()->where('tenant_id', $tenantId)->where('type', 'CLO')->where('status', 'active')->when($courseId, fn ($q) => $q->where(function ($qq) use ($courseId) { $qq->whereNull('course_id')->orWhere('course_id', $courseId); }))->orderBy('code')->get();
        $rows = [];
        foreach ($clos as $clo) {
            $row = ['clo' => $clo, 'plos' => []];
            foreach ($plos as $plo) {
                $weight = (float) OutcomeMapping::query()->where('tenant_id', $tenantId)->where('source_outcome_id', $clo->id)->where('target_outcome_id', $plo->id)->sum('weight');
                $row['plos'][] = ['plo' => $plo, 'weight' => $weight, 'heat' => $this->heat($weight)];
            }
            $rows[] = $row;
        }
        return ['plos' => $plos, 'clos' => $clos, 'rows' => $rows];
    }

    public function coverage(int $tenantId, ?int $courseId = null): array
    {
        $clos = LearningOutcome::query()->where('tenant_id', $tenantId)->where('type', 'CLO')->when($courseId, fn ($q) => $q->where(function ($qq) use ($courseId) { $qq->whereNull('course_id')->orWhere('course_id', $courseId); }))->get();
        $plos = LearningOutcome::query()->where('tenant_id', $tenantId)->where('type', 'PLO')->get();
        $unassessedClos = $clos->filter(fn ($clo) => AssessmentOutcomeMapping::query()->where('tenant_id', $tenantId)->where('outcome_id', $clo->id)->where('status', 'active')->count() === 0)->values();
        $weakPlos = $plos->filter(fn ($plo) => OutcomeMapping::query()->where('tenant_id', $tenantId)->where('target_outcome_id', $plo->id)->sum('weight') < 1)->values();
        $coursesMissingOutcome = $courseId ? (OutcomeMapping::query()->where('tenant_id', $tenantId)->where('source_type', 'course')->where('source_id', $courseId)->count() === 0 ? [$courseId] : []) : [];
        return ['unassessed_clos' => $unassessedClos, 'weak_plos' => $weakPlos, 'courses_missing_outcome' => $coursesMissingOutcome, 'summary' => ['clo_total' => $clos->count(), 'plo_total' => $plos->count(), 'unassessed_clo_count' => $unassessedClos->count(), 'weak_plo_count' => $weakPlos->count()]];
    }

    private function heat(float $weight): string
    {
        return $weight >= 0.75 ? 'strong' : ($weight > 0 ? 'partial' : 'missing');
    }
}
