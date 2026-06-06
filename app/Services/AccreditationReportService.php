<?php

namespace App\Services;

use App\Models\AccreditationReport;

class AccreditationReportService
{
    public function __construct(private OutcomeMatrixService $matrix, private AchievementAnalyticsService $analytics) {}

    public function generate(int $tenantId, string $standard, string $format, array $filters, int $actorId): AccreditationReport
    {
        $metrics = ['coverage' => $this->matrix->coverage($tenantId, $filters['course_id'] ?? null), 'achievement' => $this->analytics->dashboard($tenantId)];
        $path = 'reports/accreditation/'.strtolower($standard).'-'.now()->format('YmdHis').'.'.$format;
        return AccreditationReport::query()->create(['tenant_id' => $tenantId, 'title' => $standard.' Outcome Report', 'standard' => $standard, 'report_type' => 'outcome_coverage', 'format' => $format, 'status' => 'generated', 'filters' => $filters, 'metrics' => $metrics, 'file_path' => $path, 'generated_by' => $actorId, 'generated_at' => now()]);
    }
}
