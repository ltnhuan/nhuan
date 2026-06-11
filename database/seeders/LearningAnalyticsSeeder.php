<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\LearnerRiskProfile;
use App\Models\LearningMetric;
use App\Models\LmsUser;
use App\Services\LearningAnalyticsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LearningAnalyticsSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 1;
        $service = app(LearningAnalyticsService::class);
        $courseIds = Course::query()->where('tenant_id', $tenantId)->limit(20)->pluck('id')->values();

        if ($courseIds->isEmpty()) {
            return;
        }

        $start = now()->subYear()->startOfDay();
        $dates = collect(range(0, 52))->map(fn ($week) => $start->copy()->addWeeks($week)->toDateString());
        $learners = LmsUser::query()->where('tenant_id', $tenantId)->where('user_type', 'student')->limit(5000)->get(['id']);

        LearningMetric::query()->where('tenant_id', $tenantId)->delete();
        DB::table('engagement_scores')->where('tenant_id', $tenantId)->delete();
        DB::table('learner_risk_profiles')->where('tenant_id', $tenantId)->delete();
        DB::table('risk_alerts')->where('tenant_id', $tenantId)->delete();
        DB::table('analytics_snapshots')->where('tenant_id', $tenantId)->delete();
        foreach (['daily', 'weekly', 'monthly'] as $period) {
            DB::table("analytics_{$period}_summaries")->where('tenant_id', $tenantId)->delete();
        }

        $now = now();
        $rows = [];
        foreach ($learners as $index => $learner) {
            $courseId = $courseIds[$index % $courseIds->count()];
            $persona = $index % 10;
            foreach ($dates as $date) {
                $season = Carbon::parse($date)->weekOfYear % 12;
                $base = match (true) {
                    $persona <= 1 => 35 + $season,
                    $persona <= 3 => 55 + $season,
                    $persona <= 7 => 70 + $season,
                    default => 84 + $season,
                };
                $rows[] = [
                    'tenant_id' => $tenantId,
                    'user_id' => $learner->id,
                    'course_id' => $courseId,
                    'class_section_id' => null,
                    'metric_date' => $date,
                    'login_frequency' => max(0, (int) round(($base / 20) + ($persona % 3) - 1)),
                    'study_time_minutes' => max(15, (int) round($base * 3.2)),
                    'video_completion' => min(100, max(0, $base + (($index + $season) % 9) - 4)),
                    'assignment_completion' => min(100, max(0, $base - 4 + (($index + $season) % 11) - 5)),
                    'quiz_score' => min(100, max(0, $base - 8 + (($index + $season) % 17) - 8)),
                    'attendance' => min(100, max(0, $base + 2 + (($index + $season) % 13) - 6)),
                    'forum_activity' => max(0, (int) round(($base - 30) / 20)),
                    'metadata' => json_encode(['source' => 'demo_weekly_history']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($rows) >= 2000) {
                    LearningMetric::query()->insert($rows);
                    $rows = [];
                }
            }
        }

        if ($rows) {
            LearningMetric::query()->insert($rows);
        }

        $learners->each(function (LmsUser $learner, int $index) use ($courseIds, $service, $tenantId) {
            $service->calculateRisk($tenantId, $learner->id, $courseIds[$index % $courseIds->count()], 365);
        });

        for ($day = 0; $day <= 364; $day++) {
            $date = $start->copy()->addDays($day);
            $service->buildSummary($tenantId, 'daily', $date);
        }

        for ($week = 0; $week <= 52; $week++) {
            $date = $start->copy()->addWeeks($week)->startOfWeek();
            $service->buildSummary($tenantId, 'weekly', $date);
        }

        for ($month = 0; $month <= 12; $month++) {
            $date = $start->copy()->addMonths($month)->startOfMonth();
            $service->buildSummary($tenantId, 'monthly', $date);
        }

        LearnerRiskProfile::query()->where('tenant_id', $tenantId)->where('risk_score', '<', 40)->limit(10)->update(['recommendations' => ['duy trì tiến độ', 'ôn tập']]);
    }
}
