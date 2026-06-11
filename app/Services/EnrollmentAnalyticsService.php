<?php

namespace App\Services;

use App\Models\Enrollment;
use Illuminate\Support\Facades\DB;

class EnrollmentAnalyticsService
{
    public function summary(int $tenantId, array $filters = []): array
    {
        $query = Enrollment::query()
            ->where('tenant_id', $tenantId)
            ->when($filters['course_id'] ?? null, fn ($q, $courseId) => $q->where('course_id', $courseId))
            ->when($filters['class_section_id'] ?? null, fn ($q, $sectionId) => $q->where('class_section_id', $sectionId))
            ->when($filters['cohort_id'] ?? null, fn ($q, $cohortId) => $q->where('cohort_id', $cohortId));

        $total = (clone $query)->count();
        $completed = (clone $query)->where('status', 'completed')->count();
        $dropped = (clone $query)->whereIn('status', ['withdrawn', 'expired'])->count();
        $atRisk = (clone $query)->where('risk_score', '>=', 70)->count();

        return [
            'total_learners' => $total,
            'completion_rate' => $total > 0 ? round($completed * 100 / $total, 2) : 0.0,
            'dropout_rate' => $total > 0 ? round($dropped * 100 / $total, 2) : 0.0,
            'risk_count' => $atRisk,
            'risk_rate' => $total > 0 ? round($atRisk * 100 / $total, 2) : 0.0,
            'by_status' => (clone $query)->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status'),
            'by_source' => (clone $query)->select('source', DB::raw('count(*) as total'))->groupBy('source')->pluck('total', 'source'),
        ];
    }
}
