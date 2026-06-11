<?php

namespace App\Services;

class DashboardExportService
{
    public function __construct(private readonly DashboardDataService $dashboards)
    {
    }

    public function exportPdf(int $tenantId, string $dashboardKey, array $filters = []): array
    {
        return $this->exportSnapshot($tenantId, $dashboardKey, $filters, 'application/json', 'pdf');
    }

    public function exportExcel(int $tenantId, string $dashboardKey, array $filters = []): array
    {
        return $this->exportSnapshot($tenantId, $dashboardKey, $filters, 'text/csv', 'csv');
    }

    public function exportSnapshot(int $tenantId, string $dashboardKey, array $filters = [], string $mimeType = 'application/json', string $extension = 'json'): array
    {
        $payload = $this->dashboardPayload($tenantId, $dashboardKey, $filters);
        $content = $mimeType === 'text/csv'
            ? $this->csv($payload)
            : json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return [
            'filename' => "{$dashboardKey}-dashboard-".now()->format('Ymd-His').".{$extension}",
            'mime_type' => $mimeType,
            'content' => $content,
            'generated_at' => now()->toISOString(),
        ];
    }

    private function dashboardPayload(int $tenantId, string $dashboardKey, array $filters): array
    {
        return match ($dashboardKey) {
            'academic' => $this->dashboards->getAcademicDashboard($tenantId, $filters),
            'faculty' => $this->dashboards->getFacultyDashboard($tenantId, $filters),
            'teacher' => $this->dashboards->getTeacherDashboard($tenantId, null, $filters),
            'student' => $this->dashboards->getStudentDashboard($tenantId, null, $filters),
            'exam' => $this->dashboards->getExamDashboard($tenantId, $filters),
            'attendance' => $this->dashboards->getAttendanceDashboard($tenantId, $filters),
            'gradebook' => $this->dashboards->getGradebookDashboard($tenantId, $filters),
            'integration' => $this->dashboards->getSISIntegrationDashboard($tenantId, $filters),
            'content' => $this->dashboards->getContentDashboard($tenantId, $filters),
            'certificate' => $this->dashboards->getCertificateDashboard($tenantId, $filters),
            'ai' => $this->dashboards->getAiDashboard($tenantId, $filters),
            'risk' => $this->dashboards->getRiskDashboard($tenantId, $filters),
            default => $this->dashboards->getExecutiveDashboard($tenantId, $filters),
        };
    }

    private function csv(array $payload): string
    {
        $lines = ['metric,label,value,unit'];
        foreach ($payload['kpis'] ?? [] as $kpi) {
            $lines[] = implode(',', [
                $this->escape($kpi['key'] ?? ''),
                $this->escape($kpi['label'] ?? ''),
                $this->escape((string) ($kpi['value'] ?? '')),
                $this->escape($kpi['unit'] ?? ''),
            ]);
        }

        return implode("\n", $lines)."\n";
    }

    private function escape(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }
}
