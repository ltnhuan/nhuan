<?php

namespace App\Services;

use App\Models\AnalyticsAlert;
use App\Models\AnalyticsBenchmark;
use App\Models\AnalyticsForecast;
use App\Models\DashboardMetricSnapshot;
use App\Models\DashboardWidgetConfig;
use App\Models\LmsUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardDataService
{
    private const FILTER_COLUMNS = [
        'academic_year_id',
        'semester_id',
        'campus_id',
        'faculty_id',
        'program_id',
        'class_id',
        'course_id',
        'user_id',
    ];

    public function __construct(private readonly PerformanceCacheService $cacheService)
    {
    }

    public function getExecutiveDashboard(int $tenantId, array $filters = [], ?LmsUser $user = null): array
    {
        return $this->dashboard('executive', $tenantId, $filters, $user);
    }

    public function getAcademicDashboard(int $tenantId, array $filters = [], ?LmsUser $user = null): array
    {
        return $this->dashboard('academic', $tenantId, $filters, $user);
    }

    public function getFacultyDashboard(int $tenantId, array $filters = [], ?LmsUser $user = null): array
    {
        return $this->dashboard('faculty', $tenantId, $filters, $user);
    }

    public function getTeacherDashboard(int $tenantId, ?LmsUser $user, array $filters = []): array
    {
        return $this->dashboard('teacher', $tenantId, $filters, $user);
    }

    public function getStudentDashboard(int $tenantId, ?LmsUser $user, array $filters = []): array
    {
        return $this->dashboard('student', $tenantId, $filters, $user);
    }

    public function getExamDashboard(int $tenantId, array $filters = [], ?LmsUser $user = null): array
    {
        return $this->dashboard('exam', $tenantId, $filters, $user);
    }

    public function getAttendanceDashboard(int $tenantId, array $filters = [], ?LmsUser $user = null): array
    {
        return $this->dashboard('attendance', $tenantId, $filters, $user);
    }

    public function getGradebookDashboard(int $tenantId, array $filters = [], ?LmsUser $user = null): array
    {
        return $this->dashboard('gradebook', $tenantId, $filters, $user);
    }

    public function getSISIntegrationDashboard(int $tenantId, array $filters = [], ?LmsUser $user = null): array
    {
        return $this->dashboard('integration', $tenantId, $filters, $user);
    }

    public function getContentDashboard(int $tenantId, array $filters = [], ?LmsUser $user = null): array
    {
        return $this->dashboard('content', $tenantId, $filters, $user);
    }

    public function getCertificateDashboard(int $tenantId, array $filters = [], ?LmsUser $user = null): array
    {
        return $this->dashboard('certificate', $tenantId, $filters, $user);
    }

    public function getAiDashboard(int $tenantId, array $filters = [], ?LmsUser $user = null): array
    {
        return $this->dashboard('ai', $tenantId, $filters, $user);
    }

    public function getRiskDashboard(int $tenantId, array $filters = [], ?LmsUser $user = null): array
    {
        return $this->dashboard('risk', $tenantId, $filters, $user);
    }

    public function drilldown(int $tenantId, array $filters = [], ?LmsUser $user = null): array
    {
        $filters = $this->normalizeFilters($filters);
        $metricKey = (string) ($filters['metric_key'] ?? 'high_risk_learners');
        $scope = $this->resolveScope((string) ($filters['dashboard_key'] ?? 'executive'), $tenantId, $filters, $user);
        $date = $this->latestSnapshotDate($tenantId, $scope, $filters);

        if (! $date) {
            return [
                'metric_key' => $metricKey,
                'columns' => [],
                'rows' => [],
                'data_quality' => ['status' => 'missing', 'message' => 'Chưa có dữ liệu đủ để tính'],
            ];
        }

        return match (true) {
            str_contains($metricKey, 'risk') || $metricKey === 'high_risk_learners' => $this->learnerRiskDrilldown($tenantId, $date, $scope, $filters),
            str_contains($metricKey, 'attendance') => $this->classDrilldown($tenantId, $date, $scope, $filters, 'attendance_rate'),
            str_contains($metricKey, 'sis') || str_contains($metricKey, 'sync') => $this->snapshotTableDrilldown('sis_sync_analytics_snapshots', $tenantId, $date, $scope, $filters),
            default => $this->classDrilldown($tenantId, $date, $scope, $filters, $metricKey),
        };
    }

    public function benchmark(int $tenantId, array $filters = [], ?LmsUser $user = null): array
    {
        $filters = $this->normalizeFilters($filters);
        $scope = $this->resolveScope((string) ($filters['dashboard_key'] ?? 'executive'), $tenantId, $filters, $user);

        return [
            'scope' => $scope,
            'benchmarks' => $this->benchmarksFor($tenantId, $scope),
        ];
    }

    private function dashboard(string $dashboardKey, int $tenantId, array $filters, ?LmsUser $user): array
    {
        $definition = $this->definition($dashboardKey);
        $filters = $this->normalizeFilters($filters);
        $scope = $this->resolveScope($dashboardKey, $tenantId, $filters, $user);
        $cacheScope = sha1(json_encode([$dashboardKey, $filters, $scope, $user?->id]));

        return $this->cacheService->dashboardSummary($tenantId, $cacheScope, function () use ($tenantId, $dashboardKey, $definition, $filters, $scope, $user) {
            $latestRows = $this->latestMetricRows($tenantId, $scope, $filters, $definition['metric_keys']);
            $quality = $this->dataQuality($tenantId, $scope, $filters, $definition['kpis'], $latestRows);

            return [
                'dashboard_key' => $dashboardKey,
                'title' => $definition['title'],
                'generated_at' => now()->toISOString(),
                'filters' => $filters,
                'scope' => $scope,
                'data_quality' => $quality,
                'kpis' => $this->kpis($definition['kpis'], $latestRows),
                'charts' => $this->charts($tenantId, $scope, $filters, $definition['charts']),
                'alerts' => $this->alertsFor($tenantId, $scope),
                'forecasts' => $this->forecastsFor($tenantId, $scope, $definition['forecast_keys']),
                'benchmarks' => $this->benchmarksFor($tenantId, $scope),
                'drilldown' => $this->drilldown($tenantId, array_merge($filters, ['dashboard_key' => $dashboardKey, 'metric_key' => $definition['primary_drilldown']]), $user),
                'widgets' => $this->widgetsFor($tenantId, $dashboardKey, $definition),
            ];
        });
    }

    private function normalizeFilters(array $filters): array
    {
        $normalized = [];
        foreach (self::FILTER_COLUMNS as $column) {
            if (isset($filters[$column]) && $filters[$column] !== '') {
                $normalized[$column] = (int) $filters[$column];
            }
        }

        foreach (['teacher_id', 'metric_key', 'dashboard_key', 'level'] as $key) {
            if (isset($filters[$key]) && $filters[$key] !== '') {
                $normalized[$key] = (string) $filters[$key];
            }
        }

        $normalized['to'] = Carbon::parse($filters['to'] ?? now())->toDateString();
        $normalized['from'] = Carbon::parse($filters['from'] ?? Carbon::parse($normalized['to'])->subDays(89))->toDateString();

        return $normalized;
    }

    private function resolveScope(string $dashboardKey, int $tenantId, array $filters, ?LmsUser $user): array
    {
        if (($dashboardKey === 'student' || $user?->user_type === 'student') && $user) {
            return ['type' => 'student', 'column' => 'user_id', 'ids' => [(int) $user->id]];
        }

        if (($dashboardKey === 'teacher' || $user?->user_type === 'teacher') && $user) {
            $classIds = DB::table('teacher_assignments')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->pluck('class_section_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if (isset($filters['class_id'])) {
                $classIds = in_array((int) $filters['class_id'], $classIds, true) ? [(int) $filters['class_id']] : [];
            }

            return ['type' => 'class', 'column' => 'class_id', 'ids' => $classIds, 'restricted_by' => 'teacher_assignment'];
        }

        if ($dashboardKey === 'faculty' && ! isset($filters['faculty_id']) && $user) {
            $facultyId = $user->roleScopes()->whereNotNull('academic_unit_id')->value('academic_unit_id');
            if ($facultyId) {
                return ['type' => 'faculty', 'column' => 'faculty_id', 'ids' => [(int) $facultyId]];
            }
        }

        foreach ([
            'user_id' => 'student',
            'class_id' => 'class',
            'course_id' => 'course',
            'faculty_id' => 'faculty',
            'campus_id' => 'campus',
        ] as $column => $type) {
            if (isset($filters[$column])) {
                return ['type' => $type, 'column' => $column, 'ids' => [(int) $filters[$column]]];
            }
        }

        return ['type' => 'tenant', 'column' => null, 'ids' => [null]];
    }

    private function latestMetricRows(int $tenantId, array $scope, array $filters, array $metricKeys): Collection
    {
        $latestDate = $this->latestSnapshotDate($tenantId, $scope, $filters, $metricKeys);
        if (! $latestDate) {
            return collect();
        }

        $query = DashboardMetricSnapshot::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('snapshot_date', $latestDate)
            ->whereIn('metric_key', $metricKeys);

        $this->applyMetricScope($query, $scope, $filters);

        return $query->get();
    }

    private function latestSnapshotDate(int $tenantId, array $scope, array $filters, ?array $metricKeys = null): ?string
    {
        $query = DashboardMetricSnapshot::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('snapshot_date', '<=', $filters['to'] ?? now()->toDateString());

        if ($metricKeys) {
            $query->whereIn('metric_key', $metricKeys);
        }

        $this->applyMetricScope($query, $scope, $filters);

        $date = $query->max('snapshot_date');

        return $date ? Carbon::parse($date)->toDateString() : null;
    }

    private function applyMetricScope(Builder $query, array $scope, array $filters): void
    {
        if ($scope['type'] === 'tenant') {
            foreach (self::FILTER_COLUMNS as $column) {
                if (isset($filters[$column])) {
                    $query->where($column, $filters[$column]);
                } else {
                    $query->whereNull($column);
                }
            }
            return;
        }

        $column = $scope['column'];
        $ids = array_values(array_filter($scope['ids'] ?? [], fn ($id) => $id !== null));

        if (! $column || $ids === []) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn($column, $ids);

        foreach (self::FILTER_COLUMNS as $filterColumn) {
            if ($filterColumn !== $column && isset($filters[$filterColumn])) {
                $query->where($filterColumn, $filters[$filterColumn]);
            }
        }
    }

    private function kpis(array $kpiKeys, Collection $rows): array
    {
        return collect($kpiKeys)
            ->map(fn ($metricKey) => [
                'key' => $metricKey,
                'label' => $this->metricLabel($metricKey),
                'value' => $this->aggregateMetric($rows->where('metric_key', $metricKey), $metricKey),
                'unit' => $this->metricUnit($metricKey, $rows->where('metric_key', $metricKey)->first()?->metric_unit),
                'quality' => $rows->where('metric_key', $metricKey)->isEmpty() ? 'missing' : 'good',
                'drilldown_metric_key' => $metricKey,
            ])
            ->values()
            ->all();
    }

    private function charts(int $tenantId, array $scope, array $filters, array $charts): array
    {
        return collect($charts)
            ->map(function (array $chart) use ($tenantId, $scope, $filters) {
                $chart['data'] = match ($chart['type']) {
                    'line', 'timeline' => $this->timeSeries($tenantId, $scope, $filters, $chart['metrics'] ?? []),
                    'bar_by_faculty' => $this->barByFaculty($tenantId, $filters, $chart['metric']),
                    'pie', 'radar', 'funnel' => $this->snapshotSeries($tenantId, $scope, $filters, $chart['metrics'] ?? []),
                    'heatmap' => $this->classHeatmap($tenantId, $scope, $filters),
                    'table' => $this->summaryRows($chart['table'] ?? 'class_analytics_snapshots', $tenantId, $scope, $filters, 12),
                    default => [],
                };

                return $chart;
            })
            ->values()
            ->all();
    }

    private function timeSeries(int $tenantId, array $scope, array $filters, array $metricKeys): array
    {
        if ($metricKeys === []) {
            return [];
        }

        $query = DashboardMetricSnapshot::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('metric_key', $metricKeys)
            ->whereDate('snapshot_date', '>=', $filters['from'])
            ->whereDate('snapshot_date', '<=', $filters['to'])
            ->orderBy('snapshot_date');

        $this->applyMetricScope($query, $scope, $filters);

        return $query->get()
            ->groupBy(fn (DashboardMetricSnapshot $row) => $row->snapshot_date->toDateString())
            ->map(function (Collection $dateRows, string $date) use ($metricKeys) {
                $point = ['date' => $date];
                foreach ($metricKeys as $key) {
                    $point[$key] = $this->aggregateMetric($dateRows->where('metric_key', $key), $key);
                }
                return $point;
            })
            ->values()
            ->slice(-50)
            ->values()
            ->all();
    }

    private function barByFaculty(int $tenantId, array $filters, string $metricKey): array
    {
        $date = DashboardMetricSnapshot::query()
            ->where('tenant_id', $tenantId)
            ->where('metric_key', $metricKey)
            ->whereNotNull('faculty_id')
            ->whereDate('snapshot_date', '<=', $filters['to'])
            ->max('snapshot_date');

        if (! $date) {
            return [];
        }

        return DashboardMetricSnapshot::query()
            ->where('tenant_id', $tenantId)
            ->where('metric_key', $metricKey)
            ->whereDate('snapshot_date', Carbon::parse($date)->toDateString())
            ->whereNotNull('faculty_id')
            ->orderByDesc('metric_value')
            ->limit(20)
            ->get()
            ->map(fn (DashboardMetricSnapshot $row) => [
                'label' => $row->dimension['faculty_name'] ?? 'Khoa '.$row->faculty_id,
                'value' => round((float) $row->metric_value, 2),
                'faculty_id' => $row->faculty_id,
            ])
            ->values()
            ->all();
    }

    private function snapshotSeries(int $tenantId, array $scope, array $filters, array $metricKeys): array
    {
        $rows = $this->latestMetricRows($tenantId, $scope, $filters, $metricKeys);

        return collect($metricKeys)
            ->map(fn ($key) => [
                'label' => $this->metricLabel($key),
                'key' => $key,
                'value' => $this->aggregateMetric($rows->where('metric_key', $key), $key),
            ])
            ->values()
            ->all();
    }

    private function classHeatmap(int $tenantId, array $scope, array $filters): array
    {
        return collect($this->summaryRows('class_analytics_snapshots', $tenantId, $scope, $filters, 30))
            ->map(fn ($row) => [
                'label' => $row['dimensions']['class_name'] ?? 'Lớp '.$row['class_id'],
                'class_id' => $row['class_id'],
                'progress' => $row['metrics']['course_completion_rate'] ?? null,
                'attendance' => $row['metrics']['attendance_rate'] ?? null,
                'risk' => $row['metrics']['high_risk_learners'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function summaryRows(string $table, int $tenantId, array $scope, array $filters, int $limit): array
    {
        if (! Schema::hasTable($table)) {
            return [];
        }

        $query = DB::table($table)
            ->where('tenant_id', $tenantId)
            ->whereDate('snapshot_date', '<=', $filters['to'])
            ->orderByDesc('snapshot_date')
            ->limit($limit);

        $this->applyTableScope($query, $scope, $filters);

        return $query->get()
            ->map(fn ($row) => $this->decodeSnapshotRow($row))
            ->values()
            ->all();
    }

    private function applyTableScope($query, array $scope, array $filters): void
    {
        if ($scope['type'] !== 'tenant' && $scope['column']) {
            $ids = array_values(array_filter($scope['ids'] ?? [], fn ($id) => $id !== null));
            if ($ids === []) {
                $query->whereRaw('1 = 0');
                return;
            }
            $query->whereIn($scope['column'], $ids);
        }

        foreach (self::FILTER_COLUMNS as $column) {
            if (isset($filters[$column]) && ($scope['column'] ?? null) !== $column) {
                $query->where($column, $filters[$column]);
            }
        }
    }

    private function learnerRiskDrilldown(int $tenantId, string $date, array $scope, array $filters): array
    {
        $rows = $this->summaryRows('learner_analytics_snapshots', $tenantId, $scope, array_merge($filters, ['to' => $date]), 200);
        $riskRows = collect($rows)
            ->filter(fn ($row) => in_array($row['metrics']['risk_level'] ?? null, ['high', 'critical'], true) || (float) ($row['metrics']['risk_score'] ?? 0) >= 70)
            ->sortByDesc(fn ($row) => (float) ($row['metrics']['risk_score'] ?? 0))
            ->take(50)
            ->map(fn ($row) => [
                'student_id' => $row['user_id'],
                'student' => $row['dimensions']['student_name'] ?? 'Học viên '.$row['user_id'],
                'class' => $row['dimensions']['class_name'] ?? '-',
                'course' => $row['dimensions']['course_title'] ?? '-',
                'risk_score' => $row['metrics']['risk_score'] ?? null,
                'risk_level' => $row['metrics']['risk_level'] ?? null,
                'reason' => $row['metrics']['risk_reason'] ?? null,
                'recommended_action' => $row['metrics']['recommended_action'] ?? null,
            ])
            ->values()
            ->all();

        return [
            'metric_key' => 'high_risk_learners',
            'columns' => ['student', 'class', 'course', 'risk_score', 'risk_level', 'reason', 'recommended_action'],
            'rows' => $riskRows,
            'data_quality' => ['status' => $riskRows === [] ? 'missing' : 'good', 'message' => $riskRows === [] ? 'Chưa có dữ liệu đủ để tính' : 'Dữ liệu từ learner_analytics_snapshots'],
        ];
    }

    private function classDrilldown(int $tenantId, string $date, array $scope, array $filters, string $metricKey): array
    {
        $rows = collect($this->summaryRows('class_analytics_snapshots', $tenantId, $scope, array_merge($filters, ['to' => $date]), 100))
            ->map(fn ($row) => [
                'class_id' => $row['class_id'],
                'class' => $row['dimensions']['class_name'] ?? 'Lớp '.$row['class_id'],
                'course' => $row['dimensions']['course_title'] ?? '-',
                'faculty' => $row['dimensions']['faculty_name'] ?? '-',
                'value' => $row['metrics'][$metricKey] ?? null,
                'completion_rate' => $row['metrics']['course_completion_rate'] ?? null,
                'attendance_rate' => $row['metrics']['attendance_rate'] ?? null,
                'high_risk_learners' => $row['metrics']['high_risk_learners'] ?? null,
            ])
            ->values()
            ->all();

        return [
            'metric_key' => $metricKey,
            'columns' => ['class', 'course', 'faculty', 'value', 'completion_rate', 'attendance_rate', 'high_risk_learners'],
            'rows' => $rows,
            'data_quality' => ['status' => $rows === [] ? 'missing' : 'good', 'message' => $rows === [] ? 'Chưa có dữ liệu đủ để tính' : 'Dữ liệu từ class_analytics_snapshots'],
        ];
    }

    private function snapshotTableDrilldown(string $table, int $tenantId, string $date, array $scope, array $filters): array
    {
        $rows = collect($this->summaryRows($table, $tenantId, $scope, array_merge($filters, ['to' => $date]), 100))
            ->map(fn ($row) => [
                'scope' => $row['dimensions']['label'] ?? $row['dimensions']['system_name'] ?? $row['course_id'] ?? $row['class_id'] ?? 'Tenant',
                'metrics' => $row['metrics'],
                'data_quality' => $row['data_quality'],
            ])
            ->values()
            ->all();

        return [
            'metric_key' => 'snapshot_table',
            'columns' => ['scope', 'metrics', 'data_quality'],
            'rows' => $rows,
            'data_quality' => ['status' => $rows === [] ? 'missing' : 'good', 'message' => $rows === [] ? 'Chưa có dữ liệu đủ để tính' : "Dữ liệu từ {$table}"],
        ];
    }

    private function alertsFor(int $tenantId, array $scope): array
    {
        $query = AnalyticsAlert::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open', 'acknowledged'])
            ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->latest();

        $this->applyAnalyticScope($query, $scope);

        return $query->limit(12)->get()->toArray();
    }

    private function forecastsFor(int $tenantId, array $scope, array $forecastKeys): array
    {
        $query = AnalyticsForecast::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('forecast_key', $forecastKeys)
            ->latest('forecast_date');

        $this->applyAnalyticScope($query, $scope);

        return $query->limit(8)->get()->toArray();
    }

    private function benchmarksFor(int $tenantId, array $scope): array
    {
        $query = AnalyticsBenchmark::query()
            ->where('tenant_id', $tenantId)
            ->orderByRaw("CASE status WHEN 'below' THEN 1 WHEN 'normal' THEN 2 WHEN 'good' THEN 3 ELSE 4 END");

        $this->applyAnalyticScope($query, $scope);

        return $query->limit(10)->get()->toArray();
    }

    private function applyAnalyticScope(Builder $query, array $scope): void
    {
        if ($scope['type'] === 'tenant') {
            return;
        }

        $ids = array_values(array_filter($scope['ids'] ?? [], fn ($id) => $id !== null));
        if ($ids === []) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where($query->getModel()->getTable().'.scope_type', $scope['type'])
            ->whereIn($query->getModel()->getTable().'.scope_id', $ids);
    }

    private function widgetsFor(int $tenantId, string $dashboardKey, array $definition): array
    {
        $widgets = DashboardWidgetConfig::query()
            ->where('tenant_id', $tenantId)
            ->where('dashboard_key', $dashboardKey)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($widgets->isNotEmpty()) {
            return $widgets->toArray();
        }

        return collect($definition['charts'])
            ->map(fn ($chart, $index) => [
                'dashboard_key' => $dashboardKey,
                'widget_key' => $chart['key'],
                'title' => $chart['title'],
                'widget_type' => $chart['type'] === 'bar_by_faculty' ? 'bar' : $chart['type'],
                'data_source' => 'dashboard_metric_snapshots',
                'config' => $chart,
                'sort_order' => ($index + 1) * 10,
                'is_active' => true,
            ])
            ->values()
            ->all();
    }

    private function dataQuality(int $tenantId, array $scope, array $filters, array $expectedKpis, Collection $latestRows): array
    {
        $latestDate = $this->latestSnapshotDate($tenantId, $scope, $filters);
        if (! $latestDate) {
            return [
                'status' => 'missing',
                'message' => 'Chưa có dữ liệu đủ để tính',
                'latest_snapshot_date' => null,
                'rebuild_available' => true,
            ];
        }

        $missing = collect($expectedKpis)->diff($latestRows->pluck('metric_key')->unique());
        $ageDays = Carbon::parse($latestDate)->diffInDays(now());
        $status = match (true) {
            $ageDays > 2 => 'stale',
            $missing->isNotEmpty() => 'warning',
            default => 'good',
        };

        return [
            'status' => $status,
            'message' => match ($status) {
                'stale' => 'Snapshot mới nhất đã cũ, nên chạy rebuild snapshot',
                'warning' => 'Một số metric chưa có dữ liệu summary',
                default => 'Dữ liệu summary sẵn sàng',
            },
            'latest_snapshot_date' => $latestDate,
            'missing_metrics' => $missing->values()->all(),
            'rebuild_available' => true,
        ];
    }

    private function aggregateMetric(Collection $rows, string $metricKey): ?float
    {
        if ($rows->isEmpty()) {
            return null;
        }

        $values = $rows->pluck('metric_value')->map(fn ($value) => (float) $value);

        return round(match ($this->aggregation($metricKey)) {
            'sum' => $values->sum(),
            'max' => $values->max(),
            default => $values->avg(),
        }, 2);
    }

    private function aggregation(string $metricKey): string
    {
        if (str_contains($metricKey, '_rate') || str_contains($metricKey, '_score') || str_contains($metricKey, '_index') || str_contains($metricKey, '_time') || str_contains($metricKey, '_latency') || str_contains($metricKey, '_coverage')) {
            return 'avg';
        }

        return 'sum';
    }

    private function decodeSnapshotRow(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'tenant_id' => (int) $row->tenant_id,
            'snapshot_date' => (string) $row->snapshot_date,
            'faculty_id' => $row->faculty_id ? (int) $row->faculty_id : null,
            'class_id' => $row->class_id ? (int) $row->class_id : null,
            'course_id' => $row->course_id ? (int) $row->course_id : null,
            'user_id' => $row->user_id ? (int) $row->user_id : null,
            'metrics' => $this->decodeJson($row->metrics),
            'dimensions' => $this->decodeJson($row->dimensions),
            'data_quality' => $row->data_quality,
        ];
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function definition(string $dashboardKey): array
    {
        $definitions = $this->definitions();

        $definition = $definitions[$dashboardKey] ?? $definitions['executive'];
        $chartMetricKeys = collect($definition['charts'])
            ->flatMap(fn ($chart) => array_merge($chart['metrics'] ?? [], isset($chart['metric']) ? [$chart['metric']] : []))
            ->all();
        $definition['metric_keys'] = collect(array_merge($definition['kpis'], $chartMetricKeys))->unique()->values()->all();

        return $definition;
    }

    private function definitions(): array
    {
        return [
            'executive' => [
                'title' => 'Executive Dashboard / BGH',
                'kpis' => ['active_learners', 'course_completion_rate', 'high_risk_learners', 'attendance_rate', 'pass_rate', 'sis_sync_success_rate'],
                'charts' => [
                    ['key' => 'completion_trend', 'title' => 'Completion trend theo tuần', 'type' => 'line', 'metrics' => ['course_completion_rate', 'attendance_rate']],
                    ['key' => 'risk_distribution', 'title' => 'Risk distribution', 'type' => 'pie', 'metrics' => ['risk_low', 'risk_medium', 'risk_high', 'risk_critical']],
                    ['key' => 'pass_fail_by_faculty', 'title' => 'Pass/fail by faculty', 'type' => 'bar_by_faculty', 'metric' => 'pass_rate'],
                    ['key' => 'attendance_by_faculty', 'title' => 'Attendance by faculty', 'type' => 'bar_by_faculty', 'metric' => 'attendance_rate'],
                    ['key' => 'sis_sync_health', 'title' => 'SIS sync health', 'type' => 'funnel', 'metrics' => ['sis_sync_success_rate', 'sis_failed_sync_jobs', 'pending_outbound_events']],
                ],
                'forecast_keys' => ['course_completion_forecast', 'risk_forecast', 'exam_load_forecast'],
                'primary_drilldown' => 'high_risk_learners',
            ],
            'academic' => [
                'title' => 'Academic Command Center / Phòng đào tạo',
                'kpis' => ['published_courses', 'courses_missing_content', 'class_behind_schedule', 'pending_approval_gradebooks', 'manual_grading_backlog', 'course_completion_rate'],
                'charts' => [
                    ['key' => 'course_readiness_funnel', 'title' => 'Course readiness funnel', 'type' => 'funnel', 'metrics' => ['published_courses', 'courses_pending_approval', 'courses_missing_content']],
                    ['key' => 'class_progress_heatmap', 'title' => 'Class progress heatmap', 'type' => 'heatmap'],
                    ['key' => 'pending_actions', 'title' => 'Pending actions table', 'type' => 'table', 'table' => 'course_operation_snapshots'],
                    ['key' => 'behind_forecast', 'title' => 'Forecast lớp chậm tiến độ', 'type' => 'line', 'metrics' => ['class_behind_schedule']],
                ],
                'forecast_keys' => ['class_progress_forecast', 'grading_backlog_forecast'],
                'primary_drilldown' => 'class_behind_schedule',
            ],
            'faculty' => [
                'title' => 'Faculty Dashboard / Khoa',
                'kpis' => ['active_learners', 'course_completion_rate', 'teacher_workload', 'manual_grading_backlog', 'pending_approval_gradebooks', 'high_risk_learners'],
                'charts' => [
                    ['key' => 'course_completion_by_class', 'title' => 'Course completion by class', 'type' => 'heatmap'],
                    ['key' => 'teacher_workload', 'title' => 'Teacher workload', 'type' => 'bar_by_faculty', 'metric' => 'teacher_workload'],
                    ['key' => 'assignment_backlog', 'title' => 'Assignment backlog', 'type' => 'line', 'metrics' => ['manual_grading_backlog']],
                    ['key' => 'grade_approval_status', 'title' => 'Grade approval status', 'type' => 'funnel', 'metrics' => ['pending_approval_gradebooks', 'locked_gradebooks']],
                ],
                'forecast_keys' => ['risk_forecast', 'grading_backlog_forecast'],
                'primary_drilldown' => 'high_risk_learners',
            ],
            'teacher' => [
                'title' => 'Teacher Dashboard / Giảng viên',
                'kpis' => ['active_learners', 'course_completion_rate', 'high_risk_learners', 'manual_grading_backlog', 'average_quiz_score', 'attendance_rate'],
                'charts' => [
                    ['key' => 'my_classes_progress', 'title' => 'My classes progress', 'type' => 'heatmap'],
                    ['key' => 'at_risk_learners', 'title' => 'At-risk learners', 'type' => 'pie', 'metrics' => ['risk_low', 'risk_medium', 'risk_high', 'risk_critical']],
                    ['key' => 'grading_queue', 'title' => 'Assignment grading queue', 'type' => 'line', 'metrics' => ['manual_grading_backlog']],
                    ['key' => 'quiz_score_distribution', 'title' => 'Quiz score distribution', 'type' => 'funnel', 'metrics' => ['grade_distribution_low', 'grade_distribution_mid', 'grade_distribution_good', 'grade_distribution_excellent']],
                ],
                'forecast_keys' => ['risk_forecast', 'grading_backlog_forecast'],
                'primary_drilldown' => 'high_risk_learners',
            ],
            'student' => [
                'title' => 'Student Dashboard / Học viên',
                'kpis' => ['course_completion_rate', 'lesson_completion_rate', 'average_study_time', 'average_quiz_score', 'attendance_rate', 'risk_score'],
                'charts' => [
                    ['key' => 'progress_timeline', 'title' => 'My progress timeline', 'type' => 'timeline', 'metrics' => ['course_completion_rate', 'average_quiz_score']],
                    ['key' => 'grade_trend', 'title' => 'Grade trend', 'type' => 'line', 'metrics' => ['average_quiz_score']],
                    ['key' => 'skill_radar', 'title' => 'Skill radar', 'type' => 'radar', 'metrics' => ['lesson_completion_rate', 'assignment_completion_rate', 'attendance_rate', 'video_watch_rate']],
                    ['key' => 'ai_recommendations', 'title' => 'AI learning recommendations', 'type' => 'table', 'table' => 'ai_usage_snapshots'],
                ],
                'forecast_keys' => ['course_completion_forecast', 'risk_forecast'],
                'primary_drilldown' => 'risk_score',
            ],
            'exam' => [
                'title' => 'Exam Dashboard / Khảo thí',
                'kpis' => ['quiz_participation_rate', 'average_quiz_score', 'pass_rate', 'fail_rate', 'exam_suspicious_rate', 'manual_grading_backlog'],
                'charts' => [
                    ['key' => 'attempt_status_live', 'title' => 'Attempt status live', 'type' => 'funnel', 'metrics' => ['quiz_participation_rate', 'pass_rate', 'fail_rate']],
                    ['key' => 'score_distribution', 'title' => 'Score distribution', 'type' => 'funnel', 'metrics' => ['grade_distribution_low', 'grade_distribution_mid', 'grade_distribution_good', 'grade_distribution_excellent']],
                    ['key' => 'suspicious_events', 'title' => 'Suspicious events', 'type' => 'line', 'metrics' => ['exam_suspicious_rate']],
                    ['key' => 'manual_grading_backlog', 'title' => 'Manual grading backlog', 'type' => 'line', 'metrics' => ['manual_grading_backlog']],
                ],
                'forecast_keys' => ['exam_load_forecast', 'grading_backlog_forecast'],
                'primary_drilldown' => 'exam_suspicious_rate',
            ],
            'attendance' => [
                'title' => 'Attendance Dashboard / Chuyên cần',
                'kpis' => ['attendance_rate', 'absence_rate', 'late_rate', 'eligibility_rate', 'classes_below_attendance_threshold', 'low_attendance_learners'],
                'charts' => [
                    ['key' => 'attendance_trend', 'title' => 'Attendance trend', 'type' => 'line', 'metrics' => ['attendance_rate', 'absence_rate']],
                    ['key' => 'low_attendance_classes', 'title' => 'Low attendance classes', 'type' => 'heatmap'],
                    ['key' => 'eligibility_rate', 'title' => 'Eligibility rate', 'type' => 'funnel', 'metrics' => ['eligibility_rate', 'classes_below_attendance_threshold']],
                ],
                'forecast_keys' => ['risk_forecast'],
                'primary_drilldown' => 'low_attendance_learners',
            ],
            'gradebook' => [
                'title' => 'Gradebook Dashboard / Điểm',
                'kpis' => ['average_quiz_score', 'pass_rate', 'fail_rate', 'pending_approval_gradebooks', 'locked_gradebooks', 'grade_sync_success_rate'],
                'charts' => [
                    ['key' => 'grade_distribution', 'title' => 'Grade distribution', 'type' => 'funnel', 'metrics' => ['grade_distribution_low', 'grade_distribution_mid', 'grade_distribution_good', 'grade_distribution_excellent']],
                    ['key' => 'approval_workflow', 'title' => 'Approval workflow', 'type' => 'funnel', 'metrics' => ['pending_approval_gradebooks', 'locked_gradebooks']],
                    ['key' => 'sync_sis_status', 'title' => 'Sync SIS status', 'type' => 'line', 'metrics' => ['grade_sync_success_rate']],
                    ['key' => 'grade_anomalies', 'title' => 'Grade anomalies', 'type' => 'line', 'metrics' => ['grade_change_after_lock_alerts']],
                ],
                'forecast_keys' => ['grading_backlog_forecast'],
                'primary_drilldown' => 'pending_approval_gradebooks',
            ],
            'integration' => [
                'title' => 'SIS Integration Dashboard',
                'kpis' => ['sis_sync_success_rate', 'sis_failed_sync_jobs', 'mapping_conflicts', 'pending_outbound_events', 'average_sync_latency', 'grade_push_success_rate'],
                'charts' => [
                    ['key' => 'sync_success_fail_trend', 'title' => 'Sync success/fail trend', 'type' => 'line', 'metrics' => ['sis_sync_success_rate', 'sis_failed_sync_jobs']],
                    ['key' => 'mapping_conflicts', 'title' => 'Mapping conflicts', 'type' => 'line', 'metrics' => ['mapping_conflicts']],
                    ['key' => 'event_retry_queue', 'title' => 'Event retry queue', 'type' => 'funnel', 'metrics' => ['pending_outbound_events', 'sis_failed_sync_jobs']],
                    ['key' => 'health_check', 'title' => 'Health check', 'type' => 'table', 'table' => 'sis_sync_analytics_snapshots'],
                ],
                'forecast_keys' => ['sis_sync_forecast'],
                'primary_drilldown' => 'sis_failed_sync_jobs',
            ],
            'content' => [
                'title' => 'Content Repository Dashboard',
                'kpis' => ['courses_missing_content', 'courses_pending_approval', 'published_courses', 'repository_usage', 'video_processing_failures', 'clo_plo_coverage_rate'],
                'charts' => [
                    ['key' => 'course_readiness', 'title' => 'Course readiness', 'type' => 'funnel', 'metrics' => ['published_courses', 'courses_pending_approval', 'courses_missing_content']],
                    ['key' => 'repository_usage', 'title' => 'Repository usage', 'type' => 'line', 'metrics' => ['repository_usage']],
                    ['key' => 'content_quality', 'title' => 'Content quality', 'type' => 'radar', 'metrics' => ['content_reuse_rate', 'clo_plo_coverage_rate', 'video_watch_rate']],
                ],
                'forecast_keys' => ['course_completion_forecast'],
                'primary_drilldown' => 'courses_missing_content',
            ],
            'risk' => [
                'title' => 'Risk / Early Warning Dashboard',
                'kpis' => ['high_risk_learners', 'no_login_7_days', 'learners_behind_schedule', 'low_quiz_score_learners', 'low_attendance_learners', 'intervention_success_rate'],
                'charts' => [
                    ['key' => 'risk_score_distribution', 'title' => 'Risk score distribution', 'type' => 'pie', 'metrics' => ['risk_low', 'risk_medium', 'risk_high', 'risk_critical']],
                    ['key' => 'risk_trend', 'title' => 'Risk trend', 'type' => 'line', 'metrics' => ['high_risk_learners', 'risk_score']],
                    ['key' => 'risk_drilldown', 'title' => 'High-risk learners', 'type' => 'table', 'table' => 'learner_analytics_snapshots'],
                ],
                'forecast_keys' => ['risk_forecast'],
                'primary_drilldown' => 'high_risk_learners',
            ],
            'certificate' => [
                'title' => 'Certificate Dashboard',
                'kpis' => ['certificates_issued', 'badges_issued', 'verify_count', 'revoked_certificates', 'credential_completion_rate'],
                'charts' => [
                    ['key' => 'credential_pathway', 'title' => 'Credential completion pathway', 'type' => 'funnel', 'metrics' => ['credential_completion_rate', 'certificates_issued', 'badges_issued']],
                    ['key' => 'certificate_trend', 'title' => 'Certificate trend', 'type' => 'line', 'metrics' => ['certificates_issued', 'badges_issued']],
                ],
                'forecast_keys' => ['course_completion_forecast'],
                'primary_drilldown' => 'certificates_issued',
            ],
            'ai' => [
                'title' => 'AI Tutor Usage Dashboard',
                'kpis' => ['ai_tutor_usage', 'ai_generated_quiz_count', 'ai_generated_flashcards', 'ai_unresolved_questions', 'ai_usage_per_student'],
                'charts' => [
                    ['key' => 'ai_usage_trend', 'title' => 'AI tutor usage', 'type' => 'line', 'metrics' => ['ai_tutor_usage', 'ai_unresolved_questions']],
                    ['key' => 'ai_assets', 'title' => 'AI generated learning assets', 'type' => 'funnel', 'metrics' => ['ai_generated_quiz_count', 'ai_generated_flashcards']],
                    ['key' => 'top_asked_courses', 'title' => 'Top asked courses', 'type' => 'table', 'table' => 'ai_usage_snapshots'],
                ],
                'forecast_keys' => ['risk_forecast'],
                'primary_drilldown' => 'ai_unresolved_questions',
            ],
        ];
    }

    private function metricLabel(string $key): string
    {
        return [
            'active_learners' => 'Active learners',
            'course_completion_rate' => 'Course completion rate',
            'lesson_completion_rate' => 'Lesson completion rate',
            'video_watch_rate' => 'Video watch rate',
            'average_study_time' => 'Average study time',
            'learning_path_completion_rate' => 'Learning path completion',
            'on_time_progress_rate' => 'On-time progress rate',
            'drop_off_point_count' => 'Drop-off point',
            'quiz_participation_rate' => 'Quiz participation rate',
            'average_quiz_score' => 'Average quiz score',
            'pass_rate' => 'Pass rate',
            'fail_rate' => 'Fail rate',
            'retake_rate' => 'Retake rate',
            'question_difficulty_index' => 'Question difficulty index',
            'question_discrimination_index' => 'Question discrimination index',
            'exam_suspicious_rate' => 'Exam suspicious rate',
            'manual_grading_backlog' => 'Manual grading backlog',
            'submission_rate' => 'Submission rate',
            'on_time_submission_rate' => 'On-time submission rate',
            'late_submission_rate' => 'Late submission rate',
            'grading_turnaround_time' => 'Grading turnaround time',
            'resubmission_rate' => 'Resubmission rate',
            'rubric_achievement_rate' => 'Rubric achievement',
            'attendance_rate' => 'Attendance rate',
            'absence_rate' => 'Absence rate',
            'late_rate' => 'Late rate',
            'eligibility_rate' => 'Eligibility rate',
            'classes_below_attendance_threshold' => 'Classes below threshold',
            'grade_distribution_low' => '0-49',
            'grade_distribution_mid' => '50-64',
            'grade_distribution_good' => '65-79',
            'grade_distribution_excellent' => '80-100',
            'pending_approval_gradebooks' => 'Pending approval gradebooks',
            'locked_gradebooks' => 'Locked gradebooks',
            'grade_sync_success_rate' => 'Grade sync success rate',
            'grade_change_after_lock_alerts' => 'Grade change after lock',
            'courses_missing_content' => 'Courses missing content',
            'courses_pending_approval' => 'Courses pending approval',
            'published_courses' => 'Published courses',
            'repository_usage' => 'Repository usage',
            'video_processing_failures' => 'Video processing failures',
            'content_reuse_rate' => 'Content reuse rate',
            'clo_plo_coverage_rate' => 'CLO/PLO coverage rate',
            'risk_low' => 'Low risk',
            'risk_medium' => 'Medium risk',
            'risk_high' => 'High risk',
            'risk_critical' => 'Critical risk',
            'risk_score' => 'Risk score',
            'high_risk_learners' => 'High-risk learners',
            'no_login_7_days' => 'No login 7 days',
            'learners_behind_schedule' => 'Behind schedule',
            'class_behind_schedule' => 'Classes behind schedule',
            'low_quiz_score_learners' => 'Low quiz score',
            'low_attendance_learners' => 'Low attendance',
            'intervention_success_rate' => 'Intervention success',
            'sis_sync_success_rate' => 'Sync success rate',
            'sis_failed_sync_jobs' => 'Failed sync jobs',
            'mapping_conflicts' => 'Mapping conflicts',
            'pending_outbound_events' => 'Pending outbound events',
            'average_sync_latency' => 'Average sync latency',
            'grade_push_success_rate' => 'Grade push status',
            'attendance_push_success_rate' => 'Attendance push status',
            'certificates_issued' => 'Certificates issued',
            'badges_issued' => 'Badges issued',
            'verify_count' => 'Verify count',
            'revoked_certificates' => 'Revoked certificates',
            'credential_completion_rate' => 'Credential completion',
            'ai_tutor_usage' => 'AI tutor usage',
            'ai_generated_quiz_count' => 'AI generated quiz count',
            'ai_generated_flashcards' => 'AI generated flashcards',
            'ai_unresolved_questions' => 'AI unresolved questions',
            'ai_usage_per_student' => 'AI usage per student',
            'assignment_completion_rate' => 'Assignment completion',
            'teacher_workload' => 'Teacher workload',
        ][$key] ?? str_replace('_', ' ', $key);
    }

    private function metricUnit(string $key, ?string $storedUnit = null): ?string
    {
        if ($storedUnit) {
            return $storedUnit;
        }

        if (str_contains($key, '_rate') || str_contains($key, '_coverage') || str_contains($key, '_completion')) {
            return '%';
        }

        if (str_contains($key, '_score') || str_contains($key, '_index')) {
            return 'điểm';
        }

        if (str_contains($key, '_time') || str_contains($key, '_latency')) {
            return 'phút';
        }

        return null;
    }
}
