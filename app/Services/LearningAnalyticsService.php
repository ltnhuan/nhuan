<?php

namespace App\Services;

use App\Models\AnalyticsDailySummary;
use App\Models\AnalyticsMonthlySummary;
use App\Models\AnalyticsSnapshot;
use App\Models\AnalyticsWeeklySummary;
use App\Models\EngagementScore;
use App\Models\LearnerRiskProfile;
use App\Models\LearningMetric;
use App\Models\RiskAlert;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LearningAnalyticsService
{
    public function calculateRisk(int $tenantId, int $userId, ?int $courseId = null, int $days = 30): LearnerRiskProfile
    {
        $metrics = LearningMetric::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->when($courseId, fn ($q) => $q->where('course_id', $courseId))
            ->where('metric_date', '>=', now()->subDays($days)->toDateString())
            ->get();

        $signals = $this->aggregateSignals($metrics);
        $score = $this->riskScore($signals);
        $level = $this->riskLevel($score);
        $factors = $this->riskFactors($signals);
        $recommendations = $this->recommendations($signals, $level);

        $profile = LearnerRiskProfile::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $userId, 'course_id' => $courseId],
            [
                'risk_score' => $score,
                'risk_level' => $level,
                'risk_factors' => $factors,
                'recommendations' => $recommendations,
                'last_calculated_at' => now(),
            ]
        );

        $this->syncEngagementScore($tenantId, $userId, $courseId, $signals);
        $this->generateAlerts($profile, $signals);

        return $profile->fresh();
    }

    public function dashboard(int $tenantId, string $audience = 'executive', array $filters = []): array
    {
        $courseId = $filters['course_id'] ?? null;
        $from = Carbon::parse($filters['from'] ?? now()->subDays(89)->toDateString())->toDateString();
        $to = Carbon::parse($filters['to'] ?? now()->toDateString())->toDateString();

        $profiles = LearnerRiskProfile::query()
            ->where('tenant_id', $tenantId)
            ->when($courseId, fn ($q) => $q->where('course_id', $courseId));

        $metrics = LearningMetric::query()
            ->where('tenant_id', $tenantId)
            ->when($courseId, fn ($q) => $q->where('course_id', $courseId))
            ->whereDate('metric_date', '>=', $from)
            ->whereDate('metric_date', '<=', $to);

        $latestSummary = AnalyticsDailySummary::query()
            ->where('tenant_id', $tenantId)
            ->when($courseId, fn ($q) => $q->where('scope_type', 'course')->where('scope_id', $courseId))
            ->latest('period_start')
            ->first();

        $riskByLevel = (clone $profiles)
            ->select('risk_level', DB::raw('count(*) as total'))
            ->groupBy('risk_level')
            ->pluck('total', 'risk_level');

        return [
            'audience' => $audience,
            'kpis' => [
                'learners' => (clone $metrics)->distinct('user_id')->count('user_id'),
                'avg_progress' => round((float) ((clone $metrics)->avg(DB::raw('(video_completion + assignment_completion) / 2')) ?? 0), 2),
                'avg_grade' => round((float) ((clone $metrics)->avg('quiz_score') ?? 0), 2),
                'avg_engagement' => round((float) (EngagementScore::query()->where('tenant_id', $tenantId)->when($courseId, fn ($q) => $q->where('course_id', $courseId))->avg('engagement_score') ?? 0), 2),
                'avg_risk_score' => round((float) ((clone $profiles)->avg('risk_score') ?? 0), 2),
                'open_alerts' => RiskAlert::query()->where('tenant_id', $tenantId)->where('status', 'open')->when($courseId, fn ($q) => $q->where('course_id', $courseId))->count(),
            ],
            'risk_distribution' => [
                'low' => (int) ($riskByLevel['low'] ?? 0),
                'medium' => (int) ($riskByLevel['medium'] ?? 0),
                'high' => (int) ($riskByLevel['high'] ?? 0),
                'critical' => (int) ($riskByLevel['critical'] ?? 0),
            ],
            'progress_trend' => $this->trend($tenantId, $from, $to, $courseId),
            'grade_distribution' => $this->gradeDistribution($metrics),
            'heatmap' => $this->heatmap($tenantId, $from, $to, $courseId),
            'completion_funnel' => $this->completionFunnel($metrics),
            'alerts' => RiskAlert::query()->where('tenant_id', $tenantId)->where('status', 'open')->when($courseId, fn ($q) => $q->where('course_id', $courseId))->with(['learner:id,full_name,code,email', 'course:id,title,code'])->latest('triggered_at')->limit($audience === 'student' ? 5 : 20)->get(),
            'latest_summary' => $latestSummary,
        ];
    }

    public function buildSummary(int $tenantId, string $period, CarbonInterface $start, ?CarbonInterface $end = null, string $scopeType = 'tenant', ?int $scopeId = null): array
    {
        $end ??= match ($period) {
            'weekly' => $start->copy()->endOfWeek(),
            'monthly' => $start->copy()->endOfMonth(),
            default => $start,
        };

        $metrics = LearningMetric::query()
            ->where('tenant_id', $tenantId)
            ->when($scopeType === 'course', fn ($q) => $q->where('course_id', $scopeId))
            ->whereDate('metric_date', '>=', $start->toDateString())
            ->whereDate('metric_date', '<=', $end->toDateString());

        $profiles = LearnerRiskProfile::query()
            ->where('tenant_id', $tenantId)
            ->when($scopeType === 'course', fn ($q) => $q->where('course_id', $scopeId));

        $payload = [
            'tenant_id' => $tenantId,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'learner_count' => (clone $metrics)->distinct('user_id')->count('user_id'),
            'avg_progress' => round((float) ((clone $metrics)->avg(DB::raw('(video_completion + assignment_completion) / 2')) ?? 0), 2),
            'avg_grade' => round((float) ((clone $metrics)->avg('quiz_score') ?? 0), 2),
            'avg_engagement' => round((float) (EngagementScore::query()->where('tenant_id', $tenantId)->when($scopeType === 'course', fn ($q) => $q->where('course_id', $scopeId))->whereDate('score_date', '>=', $start->toDateString())->whereDate('score_date', '<=', $end->toDateString())->avg('engagement_score') ?? 0), 2),
            'avg_risk_score' => round((float) ((clone $profiles)->avg('risk_score') ?? 0), 2),
            'high_risk_count' => (clone $profiles)->where('risk_level', 'high')->count(),
            'critical_risk_count' => (clone $profiles)->where('risk_level', 'critical')->count(),
            'completion_rate' => round((float) ((clone $metrics)->where('video_completion', '>=', 80)->where('assignment_completion', '>=', 80)->count() * 100 / max((clone $metrics)->count(), 1)), 2),
            'chart_data' => ['trend' => $this->trend($tenantId, $start->toDateString(), $end->toDateString(), $scopeType === 'course' ? $scopeId : null)],
        ];

        $model = match ($period) {
            'weekly' => AnalyticsWeeklySummary::class,
            'monthly' => AnalyticsMonthlySummary::class,
            default => AnalyticsDailySummary::class,
        };

        $summary = $model::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'scope_type' => $scopeType, 'scope_id' => $scopeId, 'period_start' => $start->toDateString()],
            $payload
        );

        AnalyticsSnapshot::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'scope_type' => $scopeType, 'scope_id' => $scopeId, 'period_type' => $period, 'period_start' => $start->toDateString()],
            ['period_end' => $end->toDateString(), 'metrics' => $payload]
        );

        return $summary->toArray();
    }

    public function riskScore(array $signals): float
    {
        $risk =
            max(0, 100 - min($signals['login_frequency'] * 20, 100)) * 0.12 +
            max(0, 100 - min($signals['study_time_minutes'] / 6, 100)) * 0.18 +
            max(0, 100 - $signals['video_completion']) * 0.16 +
            max(0, 100 - $signals['assignment_completion']) * 0.18 +
            max(0, 100 - $signals['quiz_score']) * 0.16 +
            max(0, 100 - $signals['attendance']) * 0.14 +
            max(0, 100 - min($signals['forum_activity'] * 25, 100)) * 0.06;

        return round(min(100, max(0, $risk)), 2);
    }

    public function riskLevel(float $score): string
    {
        return match (true) {
            $score >= 85 => 'critical',
            $score >= 70 => 'high',
            $score >= 40 => 'medium',
            default => 'low',
        };
    }

    private function aggregateSignals(Collection $metrics): array
    {
        return [
            'login_frequency' => round((float) $metrics->avg('login_frequency'), 2),
            'study_time_minutes' => round((float) $metrics->avg('study_time_minutes'), 2),
            'video_completion' => round((float) $metrics->avg('video_completion'), 2),
            'assignment_completion' => round((float) $metrics->avg('assignment_completion'), 2),
            'quiz_score' => round((float) $metrics->avg('quiz_score'), 2),
            'attendance' => round((float) $metrics->avg('attendance'), 2),
            'forum_activity' => round((float) $metrics->avg('forum_activity'), 2),
        ];
    }

    private function riskFactors(array $signals): array
    {
        $factors = [];
        if ($signals['login_frequency'] < 2) $factors[] = 'login_frequency_low';
        if ($signals['study_time_minutes'] < 120) $factors[] = 'study_time_low';
        if ($signals['video_completion'] < 60 || $signals['assignment_completion'] < 60) $factors[] = 'behind_progress';
        if ($signals['quiz_score'] < 50) $factors[] = 'failing_grade';
        if ($signals['attendance'] < 70) $factors[] = 'dropout_risk';
        if ($signals['forum_activity'] < 1) $factors[] = 'low_forum_activity';
        return $factors;
    }

    private function recommendations(array $signals, string $level): array
    {
        $items = [];
        if ($signals['video_completion'] < 70 || $signals['assignment_completion'] < 70) $items[] = 'học bù';
        if ($signals['quiz_score'] < 50 || $level === 'critical') $items[] = 'học lại';
        if ($signals['quiz_score'] < 70) $items[] = 'ôn tập';
        if ($signals['study_time_minutes'] < 120) $items[] = 'tăng thời lượng tự học';
        return array_values(array_unique($items ?: ['duy trì tiến độ']));
    }

    private function syncEngagementScore(int $tenantId, int $userId, ?int $courseId, array $signals): void
    {
        $login = min($signals['login_frequency'] * 20, 100);
        $study = min($signals['study_time_minutes'] / 6, 100);
        $content = ($signals['video_completion'] + $signals['assignment_completion'] + $signals['quiz_score'] + $signals['attendance']) / 4;
        $social = min($signals['forum_activity'] * 25, 100);

        EngagementScore::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $userId, 'course_id' => $courseId, 'score_date' => now()->toDateString()],
            [
                'engagement_score' => round(($login * 0.2) + ($study * 0.25) + ($content * 0.45) + ($social * 0.1), 2),
                'login_score' => round($login, 2),
                'study_score' => round($study, 2),
                'content_score' => round($content, 2),
                'social_score' => round($social, 2),
                'signals' => $signals,
            ]
        );
    }

    private function generateAlerts(LearnerRiskProfile $profile, array $signals): void
    {
        $alerts = [];
        if ($signals['video_completion'] < 60 || $signals['assignment_completion'] < 60) $alerts['behind_progress'] = 'Chậm tiến độ học tập';
        if ($signals['quiz_score'] < 50) $alerts['failing_course'] = 'Nguy cơ rớt môn';
        if ($signals['attendance'] < 65 || ($profile->risk_level === 'critical' && $signals['login_frequency'] < 1)) $alerts['dropout_risk'] = 'Nguy cơ nghỉ học';

        foreach ($alerts as $type => $message) {
            RiskAlert::query()->updateOrCreate(
                ['tenant_id' => $profile->tenant_id, 'user_id' => $profile->user_id, 'course_id' => $profile->course_id, 'alert_type' => $type, 'status' => 'open'],
                [
                    'risk_profile_id' => $profile->id,
                    'severity' => $profile->risk_level,
                    'message' => $message,
                    'recommended_actions' => $profile->recommendations,
                    'triggered_at' => now(),
                ]
            );
        }
    }

    private function trend(int $tenantId, string $from, string $to, ?int $courseId): array
    {
        return LearningMetric::query()
            ->where('tenant_id', $tenantId)
            ->when($courseId, fn ($q) => $q->where('course_id', $courseId))
            ->whereDate('metric_date', '>=', $from)
            ->whereDate('metric_date', '<=', $to)
            ->select('metric_date', DB::raw('avg((video_completion + assignment_completion) / 2) as progress'), DB::raw('avg(quiz_score) as grade'))
            ->groupBy('metric_date')
            ->orderBy('metric_date')
            ->get()
            ->map(fn ($row) => ['date' => Carbon::parse($row->metric_date)->toDateString(), 'progress' => round((float) $row->progress, 2), 'grade' => round((float) $row->grade, 2)])
            ->values()
            ->all();
    }

    private function gradeDistribution($metrics): array
    {
        $scores = (clone $metrics)->pluck('quiz_score');
        return [
            '0-49' => $scores->filter(fn ($score) => $score < 50)->count(),
            '50-64' => $scores->filter(fn ($score) => $score >= 50 && $score < 65)->count(),
            '65-79' => $scores->filter(fn ($score) => $score >= 65 && $score < 80)->count(),
            '80-100' => $scores->filter(fn ($score) => $score >= 80)->count(),
        ];
    }

    private function heatmap(int $tenantId, string $from, string $to, ?int $courseId): array
    {
        return LearningMetric::query()
            ->where('tenant_id', $tenantId)
            ->when($courseId, fn ($q) => $q->where('course_id', $courseId))
            ->whereDate('metric_date', '>=', $from)
            ->whereDate('metric_date', '<=', $to)
            ->get(['metric_date', 'login_frequency', 'study_time_minutes', 'forum_activity'])
            ->groupBy(fn (LearningMetric $metric) => Carbon::parse($metric->metric_date)->dayOfWeek)
            ->map(fn (Collection $items, int $weekday) => [
                'weekday' => $weekday,
                'intensity' => round($items->avg(fn (LearningMetric $metric) => $metric->login_frequency + ($metric->study_time_minutes / 60) + $metric->forum_activity), 2),
            ])
            ->sortBy('weekday')
            ->values()
            ->all();
    }

    private function completionFunnel($metrics): array
    {
        $total = max((clone $metrics)->count(), 1);
        return [
            ['stage' => 'Đăng nhập', 'value' => (clone $metrics)->where('login_frequency', '>', 0)->count(), 'rate' => round((clone $metrics)->where('login_frequency', '>', 0)->count() * 100 / $total, 2)],
            ['stage' => 'Xem video', 'value' => (clone $metrics)->where('video_completion', '>=', 50)->count(), 'rate' => round((clone $metrics)->where('video_completion', '>=', 50)->count() * 100 / $total, 2)],
            ['stage' => 'Nộp bài', 'value' => (clone $metrics)->where('assignment_completion', '>=', 50)->count(), 'rate' => round((clone $metrics)->where('assignment_completion', '>=', 50)->count() * 100 / $total, 2)],
            ['stage' => 'Hoàn thành', 'value' => (clone $metrics)->where('video_completion', '>=', 80)->where('assignment_completion', '>=', 80)->count(), 'rate' => round((clone $metrics)->where('video_completion', '>=', 80)->where('assignment_completion', '>=', 80)->count() * 100 / $total, 2)],
        ];
    }
}
