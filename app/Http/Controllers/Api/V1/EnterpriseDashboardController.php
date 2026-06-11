<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AnalyticsAlert;
use App\Models\LmsUser;
use App\Models\RiskAlert;
use App\Services\AlertEngineService;
use App\Services\AnalyticsSnapshotService;
use App\Services\BenchmarkService;
use App\Services\DashboardDataService;
use App\Services\DashboardExportService;
use App\Services\ForecastService;
use App\Services\TenantContext;
use App\Support\ApiPagination;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EnterpriseDashboardController extends Controller
{
    public function executive(Request $request, TenantContext $tenant, DashboardDataService $service)
    {
        return ApiResponse::success($service->getExecutiveDashboard((int) $tenant->id(), $this->filters($request), $this->user($request, (int) $tenant->id())));
    }

    public function academic(Request $request, TenantContext $tenant, DashboardDataService $service)
    {
        return ApiResponse::success($service->getAcademicDashboard((int) $tenant->id(), $this->filters($request), $this->user($request, (int) $tenant->id())));
    }

    public function faculty(Request $request, TenantContext $tenant, DashboardDataService $service)
    {
        return ApiResponse::success($service->getFacultyDashboard((int) $tenant->id(), $this->filters($request), $this->user($request, (int) $tenant->id())));
    }

    public function teacher(Request $request, TenantContext $tenant, DashboardDataService $service)
    {
        return ApiResponse::success($service->getTeacherDashboard((int) $tenant->id(), $this->user($request, (int) $tenant->id()), $this->filters($request)));
    }

    public function student(Request $request, TenantContext $tenant, DashboardDataService $service)
    {
        return ApiResponse::success($service->getStudentDashboard((int) $tenant->id(), $this->user($request, (int) $tenant->id()), $this->filters($request)));
    }

    public function exam(Request $request, TenantContext $tenant, DashboardDataService $service)
    {
        return ApiResponse::success($service->getExamDashboard((int) $tenant->id(), $this->filters($request), $this->user($request, (int) $tenant->id())));
    }

    public function attendance(Request $request, TenantContext $tenant, DashboardDataService $service)
    {
        return ApiResponse::success($service->getAttendanceDashboard((int) $tenant->id(), $this->filters($request), $this->user($request, (int) $tenant->id())));
    }

    public function gradebook(Request $request, TenantContext $tenant, DashboardDataService $service)
    {
        return ApiResponse::success($service->getGradebookDashboard((int) $tenant->id(), $this->filters($request), $this->user($request, (int) $tenant->id())));
    }

    public function integration(Request $request, TenantContext $tenant, DashboardDataService $service)
    {
        return ApiResponse::success($service->getSISIntegrationDashboard((int) $tenant->id(), $this->filters($request), $this->user($request, (int) $tenant->id())));
    }

    public function content(Request $request, TenantContext $tenant, DashboardDataService $service)
    {
        return ApiResponse::success($service->getContentDashboard((int) $tenant->id(), $this->filters($request), $this->user($request, (int) $tenant->id())));
    }

    public function certificate(Request $request, TenantContext $tenant, DashboardDataService $service)
    {
        return ApiResponse::success($service->getCertificateDashboard((int) $tenant->id(), $this->filters($request), $this->user($request, (int) $tenant->id())));
    }

    public function ai(Request $request, TenantContext $tenant, DashboardDataService $service)
    {
        return ApiResponse::success($service->getAiDashboard((int) $tenant->id(), $this->filters($request), $this->user($request, (int) $tenant->id())));
    }

    public function risk(Request $request, TenantContext $tenant, DashboardDataService $service)
    {
        return ApiResponse::success($service->getRiskDashboard((int) $tenant->id(), $this->filters($request), $this->user($request, (int) $tenant->id())));
    }

    public function alerts(Request $request, TenantContext $tenant)
    {
        $perPage = ApiPagination::perPage($request, 50);
        $analytics = collect(AnalyticsAlert::query()
            ->where('tenant_id', $tenant->id())
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('severity'), fn ($q, $severity) => $q->where('severity', $severity))
            ->when($request->date('from'), fn ($q, $from) => $q->whereDate('created_at', '>=', $from->toDateString()))
            ->when($request->date('to'), fn ($q, $to) => $q->whereDate('created_at', '<=', $to->toDateString()))
            ->when($request->integer('course_id'), fn ($q, $courseId) => $q->where('scope_type', 'course')->where('scope_id', $courseId))
            ->latest()
            ->limit($perPage)
            ->get()
            ->map(fn (AnalyticsAlert $alert) => [
                'id' => $alert->id,
                'source' => 'analytics_alerts',
                'alert_type' => $alert->alert_type,
                'severity' => $alert->severity,
                'status' => $alert->status,
                'scope_type' => $alert->scope_type,
                'scope_id' => $alert->scope_id,
                'title' => $alert->title,
                'message' => $alert->message,
                'recommended_action' => $alert->recommended_action,
                'created_at' => $alert->created_at?->toISOString(),
            ])->all());

        $legacy = collect(RiskAlert::query()
            ->where('tenant_id', $tenant->id())
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('severity'), fn ($q, $severity) => $q->where('severity', $severity))
            ->when($request->integer('course_id'), fn ($q, $courseId) => $q->where('course_id', $courseId))
            ->when($request->date('from'), fn ($q, $from) => $q->whereDate('triggered_at', '>=', $from->toDateString()))
            ->when($request->date('to'), fn ($q, $to) => $q->whereDate('triggered_at', '<=', $to->toDateString()))
            ->latest('triggered_at')
            ->limit($perPage)
            ->get()
            ->map(fn (RiskAlert $alert) => [
                'id' => $alert->id,
                'source' => 'risk_alerts',
                'alert_type' => $alert->alert_type,
                'severity' => $alert->severity,
                'status' => $alert->status,
                'scope_type' => 'student',
                'scope_id' => $alert->user_id,
                'title' => $alert->message,
                'message' => $alert->message,
                'recommended_action' => is_array($alert->recommended_actions) ? implode(', ', $alert->recommended_actions) : null,
                'learner' => $alert->learner,
                'course' => $alert->course,
                'created_at' => $alert->triggered_at?->toISOString(),
            ])->all());

        $items = $analytics->merge($legacy)
            ->sortByDesc('created_at')
            ->take($perPage)
            ->values();

        return response()->json([
            'data' => $items,
            'current_page' => 1,
            'per_page' => $perPage,
            'total' => $items->count(),
        ]);
    }

    public function acknowledgeAlert(int $id)
    {
        if ($alert = AnalyticsAlert::query()->find($id)) {
            $alert->forceFill(['status' => 'acknowledged'])->save();
            return $alert->fresh();
        }

        $alert = RiskAlert::query()->findOrFail($id);
        $alert->forceFill(['status' => 'acknowledged', 'acknowledged_at' => now()])->save();

        return $alert->fresh();
    }

    public function resolveAlert(int $id, AlertEngineService $engine)
    {
        if ($alert = AnalyticsAlert::query()->find($id)) {
            return $engine->resolveAlert($alert);
        }

        $alert = RiskAlert::query()->findOrFail($id);
        $alert->forceFill(['status' => 'resolved', 'resolved_at' => now()])->save();

        return $alert->fresh();
    }

    public function forecasts(Request $request, TenantContext $tenant)
    {
        $items = \App\Models\AnalyticsForecast::query()
            ->where('tenant_id', $tenant->id())
            ->when($request->query('forecast_key'), fn ($q, $key) => $q->where('forecast_key', $key))
            ->latest('forecast_date')
            ->paginate(ApiPagination::perPage($request, 50));

        return $items;
    }

    public function rebuildSnapshots(Request $request, TenantContext $tenant, AnalyticsSnapshotService $snapshots, ForecastService $forecasts, AlertEngineService $alerts, BenchmarkService $benchmarks)
    {
        $data = $request->validate([
            'scope' => ['nullable', 'in:tenant,faculty,class,course,student'],
        ]);

        $result = $snapshots->rebuildByScope($data['scope'] ?? 'tenant', (int) $tenant->id());
        $forecastResult = array_merge(
            $forecasts->calculateCourseCompletionForecast((int) $tenant->id()),
            $forecasts->calculateRiskForecast((int) $tenant->id()),
            $forecasts->calculateExamLoadForecast((int) $tenant->id()),
            $forecasts->calculateGradingBacklogForecast((int) $tenant->id())
        );
        $alertResult = array_merge(
            $alerts->detectHighRiskLearners((int) $tenant->id()),
            $alerts->detectClassBehindSchedule((int) $tenant->id()),
            $alerts->detectLowAttendance((int) $tenant->id()),
            $alerts->detectPendingGradeApproval((int) $tenant->id()),
            $alerts->detectSyncFailures((int) $tenant->id())
        );

        $benchmarks->compareWithInternalTarget((int) $tenant->id(), 'course_completion_rate', 'tenant', null, 75, 80);
        $benchmarks->compareWithInternalTarget((int) $tenant->id(), 'attendance_rate', 'tenant', null, 82, 85);

        return ApiResponse::success([
            'snapshots' => $result,
            'forecasts' => count($forecastResult),
            'alerts' => count($alertResult),
        ], 'Dashboard analytics snapshots rebuilt.');
    }

    public function drilldown(Request $request, TenantContext $tenant, DashboardDataService $service)
    {
        return ApiResponse::success($service->drilldown((int) $tenant->id(), $this->filters($request), $this->user($request, (int) $tenant->id())));
    }

    public function benchmark(Request $request, TenantContext $tenant, DashboardDataService $service)
    {
        return ApiResponse::success($service->benchmark((int) $tenant->id(), $this->filters($request), $this->user($request, (int) $tenant->id())));
    }

    public function export(Request $request, TenantContext $tenant, DashboardExportService $export)
    {
        $data = $request->validate([
            'dashboard_key' => ['required', 'string'],
            'format' => ['nullable', 'in:pdf,excel,json'],
            'filters' => ['nullable', 'array'],
        ]);

        $result = match ($data['format'] ?? 'json') {
            'pdf' => $export->exportPdf((int) $tenant->id(), $data['dashboard_key'], $data['filters'] ?? []),
            'excel' => $export->exportExcel((int) $tenant->id(), $data['dashboard_key'], $data['filters'] ?? []),
            default => $export->exportSnapshot((int) $tenant->id(), $data['dashboard_key'], $data['filters'] ?? []),
        };

        return ApiResponse::success($result, 'Dashboard exported.');
    }

    private function filters(Request $request): array
    {
        return $request->only([
            'academic_year_id',
            'semester_id',
            'campus_id',
            'faculty_id',
            'program_id',
            'class_id',
            'course_id',
            'user_id',
            'teacher_id',
            'from',
            'to',
            'metric_key',
            'dashboard_key',
            'level',
        ]);
    }

    private function user(Request $request, int $tenantId): ?LmsUser
    {
        $email = $request->header('X-Demo-User-Email');
        if (! $email) {
            return null;
        }

        return LmsUser::query()
            ->where('tenant_id', $tenantId)
            ->where('email', $email)
            ->select(['id', 'tenant_id', 'code', 'full_name', 'email', 'user_type', 'status', 'metadata'])
            ->first();
    }
}
