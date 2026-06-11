<?php

namespace App\Services;

use App\Models\AnalyticsForecast;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ForecastService
{
    public function calculateCourseCompletionForecast(int $tenantId, ?Carbon $date = null): array
    {
        $date ??= now();
        $snapshots = $this->latestRows('course_operation_snapshots', $tenantId, $date, 200);

        return $snapshots->map(function ($row) use ($tenantId, $date) {
            $metrics = $this->decode($row->metrics);
            $current = (float) ($metrics['course_completion_rate'] ?? 0);
            $trend = min(100, $current + max(2, (100 - $current) * 0.18));

            return $this->store($tenantId, 'course_completion_forecast', 'course', $row->course_id, $date, $trend, 0.72, [
                'current_completion' => $current,
                'remaining_lessons' => max(0, 100 - $current),
                'study_frequency' => $metrics['average_study_time'] ?? null,
            ], $this->explainForecast('course_completion_forecast', $trend));
        })->values()->all();
    }

    public function calculateRiskForecast(int $tenantId, ?Carbon $date = null): array
    {
        $date ??= now();
        $snapshots = $this->latestRows('learner_analytics_snapshots', $tenantId, $date, 500);

        return $snapshots->map(function ($row) use ($tenantId, $date) {
            $metrics = $this->decode($row->metrics);
            $current = (float) ($metrics['risk_score'] ?? 0);
            $predicted = min(100, max(0, $current + (($metrics['course_completion_rate'] ?? 0) < 50 ? 8 : -3)));

            return $this->store($tenantId, 'risk_forecast', 'student', $row->user_id, $date, $predicted, 0.76, [
                'progress' => $metrics['course_completion_rate'] ?? null,
                'quiz_score' => $metrics['average_quiz_score'] ?? null,
                'attendance' => $metrics['attendance_rate'] ?? null,
                'assignment_submission' => $metrics['assignment_completion_rate'] ?? null,
                'days_without_login' => ($metrics['login_frequency'] ?? 1) <= 0 ? 7 : 0,
            ], $this->explainForecast('risk_forecast', $predicted));
        })->values()->all();
    }

    public function calculateExamLoadForecast(int $tenantId, ?Carbon $date = null): array
    {
        $date ??= now();
        $attempts = Schema::hasTable('exam_attempts')
            ? DB::table('exam_attempts')->where('tenant_id', $tenantId)->where('created_at', '>=', $date->copy()->subDays(14))->count()
            : 0;
        $predicted = round($attempts * 1.25, 2);

        return [$this->store($tenantId, 'exam_load_forecast', 'tenant', null, $date, $predicted, 0.68, [
            'recent_attempts' => $attempts,
            'video_sessions' => Schema::hasTable('video_watch_sessions') ? DB::table('video_watch_sessions')->where('tenant_id', $tenantId)->count() : null,
            'concurrent_users' => null,
        ], $this->explainForecast('exam_load_forecast', $predicted))];
    }

    public function calculateGradingBacklogForecast(int $tenantId, ?Carbon $date = null): array
    {
        $date ??= now();
        $pending = Schema::hasTable('assignment_submissions')
            ? DB::table('assignment_submissions')->where('tenant_id', $tenantId)->where('status', 'submitted')->count()
            : 0;
        $predicted = round($pending * 1.15, 2);

        return [$this->store($tenantId, 'grading_backlog_forecast', 'tenant', null, $date, $predicted, 0.70, [
            'submissions_pending' => $pending,
            'teacher_workload' => Schema::hasTable('teacher_assignments') ? DB::table('teacher_assignments')->where('tenant_id', $tenantId)->where('status', 'active')->count() : null,
            'average_grading_time' => 24,
        ], $this->explainForecast('grading_backlog_forecast', $predicted))];
    }

    public function explainForecast(string $forecastKey, float $predictedValue): string
    {
        return match ($forecastKey) {
            'risk_forecast' => "Dự báo nguy cơ dựa trên tiến độ, điểm quiz, chuyên cần, bài nộp và tín hiệu đăng nhập. Giá trị dự báo: {$predictedValue}.",
            'course_completion_forecast' => "Dự báo hoàn thành dựa trên xu hướng hoàn thành, khối lượng bài còn lại và tần suất học. Giá trị dự báo: {$predictedValue}.",
            'exam_load_forecast' => "Dự báo tải thi dựa trên số lượt làm bài gần đây, phiên video và người dùng đồng thời. Giá trị dự báo: {$predictedValue}.",
            'grading_backlog_forecast' => "Dự báo tồn đọng chấm bài dựa trên bài nộp đang chờ, tải giảng viên và thời gian chấm trung bình. Giá trị dự báo: {$predictedValue}.",
            default => "Dự báo baseline từ snapshot analytics. Giá trị dự báo: {$predictedValue}.",
        };
    }

    private function store(int $tenantId, string $key, string $scopeType, ?int $scopeId, Carbon $date, float $value, float $confidence, array $features, string $explanation): array
    {
        return AnalyticsForecast::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'forecast_key' => $key,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'forecast_date' => $date->toDateString(),
                'horizon' => $key === 'course_completion_forecast' ? 'semester' : '30d',
            ],
            [
                'predicted_value' => $value,
                'confidence' => $confidence,
                'model_name' => 'baseline_snapshot_v1',
                'features' => $features,
                'explanation' => $explanation,
            ]
        )->toArray();
    }

    private function latestRows(string $table, int $tenantId, Carbon $date, int $limit)
    {
        if (! Schema::hasTable($table)) {
            return collect();
        }

        $latest = DB::table($table)
            ->where('tenant_id', $tenantId)
            ->whereDate('snapshot_date', '<=', $date->toDateString())
            ->max('snapshot_date');

        if (! $latest) {
            return collect();
        }

        return DB::table($table)
            ->where('tenant_id', $tenantId)
            ->whereDate('snapshot_date', Carbon::parse($latest)->toDateString())
            ->limit($limit)
            ->get();
    }

    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
