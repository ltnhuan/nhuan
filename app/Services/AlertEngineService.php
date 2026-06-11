<?php

namespace App\Services;

use App\Models\AnalyticsAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AlertEngineService
{
    public function detectHighRiskLearners(int $tenantId, ?Carbon $date = null): array
    {
        $date ??= now();
        $rows = $this->latestRows('learner_analytics_snapshots', $tenantId, $date, 1000);
        $created = [];

        foreach ($rows as $row) {
            $metrics = $this->decode($row->metrics);
            if (($metrics['risk_level'] ?? null) === 'high' || ($metrics['risk_level'] ?? null) === 'critical' || (float) ($metrics['risk_score'] ?? 0) >= 70) {
                $created[] = $this->createOrUpdateAlert($tenantId, 'high_risk_learner', $metrics['risk_level'] === 'critical' ? 'critical' : 'high', 'student', (int) $row->user_id, 'Học viên nguy cơ cao', $metrics['risk_reason'] ?? 'Nguy cơ học tập cao', $metrics['recommended_action'] ?? 'Cố vấn học tập liên hệ trong 24 giờ')->toArray();
            }
        }

        return $created;
    }

    public function detectClassBehindSchedule(int $tenantId, ?Carbon $date = null): array
    {
        $date ??= now();
        $rows = $this->latestRows('class_analytics_snapshots', $tenantId, $date, 500);
        $created = [];

        foreach ($rows as $row) {
            $metrics = $this->decode($row->metrics);
            if ((float) ($metrics['course_completion_rate'] ?? 100) < 55 || (int) ($metrics['class_behind_schedule'] ?? 0) > 0) {
                $created[] = $this->createOrUpdateAlert($tenantId, 'class_behind_schedule', 'high', 'class', (int) $row->class_id, 'Lớp chậm tiến độ', 'Tỷ lệ hoàn thành thấp hơn ngưỡng vận hành.', 'Rà soát kế hoạch học kỳ và bổ sung buổi học bù.')->toArray();
            }
        }

        return $created;
    }

    public function detectLowAttendance(int $tenantId, ?Carbon $date = null): array
    {
        $date ??= now();
        $rows = $this->latestRows('class_analytics_snapshots', $tenantId, $date, 500);
        $created = [];

        foreach ($rows as $row) {
            $metrics = $this->decode($row->metrics);
            if ((float) ($metrics['attendance_rate'] ?? 100) < 80) {
                $created[] = $this->createOrUpdateAlert($tenantId, 'low_attendance', 'medium', 'class', (int) $row->class_id, 'Chuyên cần thấp', 'Tỷ lệ chuyên cần lớp dưới 80%.', 'Giảng viên chủ nhiệm xác minh danh sách vắng và cảnh báo điều kiện dự thi.')->toArray();
            }
        }

        return $created;
    }

    public function detectPendingGradeApproval(int $tenantId): array
    {
        if (! Schema::hasTable('grade_approval_batches')) {
            return [];
        }

        $pending = DB::table('grade_approval_batches')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['submitted', 'pending'])
            ->count();

        if ($pending === 0) {
            return [];
        }

        return [$this->createOrUpdateAlert($tenantId, 'pending_grade_approval', 'medium', 'tenant', null, 'Tồn đọng duyệt điểm', "{$pending} bảng điểm đang chờ duyệt.", 'Phòng đào tạo kiểm tra và duyệt trước hạn đồng bộ SIS.')->toArray()];
    }

    public function detectSyncFailures(int $tenantId): array
    {
        if (! Schema::hasTable('sync_jobs')) {
            return [];
        }

        $failed = DB::table('sync_jobs')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['failed', 'error'])
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($failed === 0) {
            return [];
        }

        return [$this->createOrUpdateAlert($tenantId, 'sis_sync_failure', $failed > 10 ? 'critical' : 'high', 'tenant', null, 'Lỗi đồng bộ SIS', "{$failed} job đồng bộ lỗi trong 24 giờ.", 'Kiểm tra mapping, retry queue và cấu hình kết nối SIS.')->toArray()];
    }

    public function createOrUpdateAlert(int $tenantId, string $type, string $severity, string $scopeType, ?int $scopeId, string $title, string $message, ?string $action = null): AnalyticsAlert
    {
        $existing = AnalyticsAlert::query()
            ->where('tenant_id', $tenantId)
            ->where('alert_type', $type)
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->whereIn('status', ['open', 'acknowledged'])
            ->first();

        if ($existing) {
            $existing->forceFill([
                'severity' => $severity,
                'title' => $title,
                'message' => $message,
                'recommended_action' => $action,
                'status' => $existing->status === 'resolved' ? 'open' : $existing->status,
            ])->save();

            return $existing->fresh();
        }

        return AnalyticsAlert::query()->create([
            'tenant_id' => $tenantId,
            'alert_type' => $type,
            'severity' => $severity,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'title' => $title,
            'message' => $message,
            'recommended_action' => $action,
            'status' => 'open',
        ]);
    }

    public function resolveAlert(AnalyticsAlert $alert): AnalyticsAlert
    {
        $alert->forceFill(['status' => 'resolved', 'resolved_at' => now()])->save();

        return $alert->fresh();
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
