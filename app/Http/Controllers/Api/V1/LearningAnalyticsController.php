<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\LearnerRiskProfile;
use App\Models\LearningMetric;
use App\Models\RiskAlert;
use App\Services\LearningAnalyticsService;
use App\Services\TenantContext;
use App\Support\ApiPagination;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;

class LearningAnalyticsController extends Controller
{
    public function dashboard(Request $request, TenantContext $tenant, LearningAnalyticsService $service)
    {
        return $service->dashboard((int) $tenant->id(), $request->query('audience', 'executive'), $request->only(['course_id', 'from', 'to']));
    }

    public function metrics(Request $request, TenantContext $tenant)
    {
        return LearningMetric::query()
            ->where('tenant_id', $tenant->id())
            ->when($request->integer('user_id'), fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($request->integer('course_id'), fn ($q, $courseId) => $q->where('course_id', $courseId))
            ->when($request->date('from'), fn ($q, $from) => $q->whereDate('metric_date', '>=', $from->toDateString()))
            ->when($request->date('to'), fn ($q, $to) => $q->whereDate('metric_date', '<=', $to->toDateString()))
            ->select(['id', 'user_id', 'course_id', 'metric_date', 'login_frequency', 'study_time_minutes', 'video_completion', 'assignment_completion', 'quiz_score', 'attendance', 'forum_activity'])
            ->latest('metric_date')
            ->paginate(ApiPagination::perPage($request, 50));
    }

    public function calculateRisk(Request $request, TenantContext $tenant, LearningAnalyticsService $service)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'course_id' => ['nullable', 'integer'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        return $service->calculateRisk((int) $tenant->id(), (int) $data['user_id'], $data['course_id'] ?? null, $data['days'] ?? 30);
    }

    public function risks(Request $request, TenantContext $tenant)
    {
        return LearnerRiskProfile::query()
            ->where('tenant_id', $tenant->id())
            ->when($request->query('level'), fn ($q, $level) => $q->where('risk_level', $level))
            ->when($request->integer('course_id'), fn ($q, $courseId) => $q->where('course_id', $courseId))
            ->when($request->date('from'), fn ($q, $from) => $q->whereDate('last_calculated_at', '>=', $from->toDateString()))
            ->when($request->date('to'), fn ($q, $to) => $q->whereDate('last_calculated_at', '<=', $to->toDateString()))
            ->with(['learner:id,full_name,code,email', 'course:id,title,code'])
            ->orderByDesc('risk_score')
            ->paginate(ApiPagination::perPage($request, 50));
    }

    public function alerts(Request $request, TenantContext $tenant)
    {
        return RiskAlert::query()
            ->where('tenant_id', $tenant->id())
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('severity'), fn ($q, $severity) => $q->where('severity', $severity))
            ->when($request->date('from'), fn ($q, $from) => $q->whereDate('triggered_at', '>=', $from->toDateString()))
            ->when($request->date('to'), fn ($q, $to) => $q->whereDate('triggered_at', '<=', $to->toDateString()))
            ->with(['learner:id,full_name,code,email', 'course:id,title,code'])
            ->latest('triggered_at')
            ->paginate(ApiPagination::perPage($request, 50));
    }

    public function acknowledge(RiskAlert $alert)
    {
        $alert->forceFill(['status' => 'acknowledged', 'acknowledged_at' => now()])->save();

        return $alert->fresh();
    }

    public function resolve(RiskAlert $alert)
    {
        $alert->forceFill(['status' => 'resolved', 'resolved_at' => now()])->save();

        return $alert->fresh();
    }

    public function buildSummary(Request $request, TenantContext $tenant, LearningAnalyticsService $service)
    {
        $data = $request->validate([
            'period' => ['required', 'in:daily,weekly,monthly'],
            'period_start' => ['required', 'date'],
            'period_end' => ['nullable', 'date'],
            'scope_type' => ['nullable', 'in:tenant,course,faculty,class_section,teacher'],
            'scope_id' => ['nullable', 'integer'],
        ]);

        return $service->buildSummary(
            (int) $tenant->id(),
            $data['period'],
            Carbon::parse($data['period_start']),
            isset($data['period_end']) ? Carbon::parse($data['period_end']) : null,
            $data['scope_type'] ?? 'tenant',
            $data['scope_id'] ?? null
        );
    }
}
