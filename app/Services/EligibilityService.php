<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\EligibilityRule;
use App\Models\LearnerEligibilitySummary;

class EligibilityService
{
    public function recalculateCourse(int $tenantId, int $courseId, ?int $classId = null): array
    {
        $sessionIds = AttendanceSession::query()->where('tenant_id', $tenantId)->where('course_id', $courseId)->when($classId, fn ($q) => $q->where('class_id', $classId))->whereIn('status', ['open','closed','locked'])->pluck('id');
        $userIds = AttendanceRecord::query()->where('tenant_id', $tenantId)->whereIn('attendance_session_id', $sessionIds)->distinct()->pluck('user_id');
        $updated = 0;
        foreach ($userIds as $userId) {
            $this->calculateLearner($tenantId, $courseId, $classId, (int) $userId, $sessionIds->all());
            $updated++;
        }
        return ['updated' => $updated, 'sessions' => $sessionIds->count()];
    }

    public function calculateLearner(int $tenantId, int $courseId, ?int $classId, int $userId, array $sessionIds = []): LearnerEligibilitySummary
    {
        if (! $sessionIds) {
            $sessionIds = AttendanceSession::query()->where('tenant_id', $tenantId)->where('course_id', $courseId)->when($classId, fn ($q) => $q->where('class_id', $classId))->whereIn('status', ['open','closed','locked'])->pluck('id')->all();
        }
        $total = max(count($sessionIds), 1);
        $records = AttendanceRecord::query()->where('tenant_id', $tenantId)->where('user_id', $userId)->whereIn('attendance_session_id', $sessionIds)->get();
        $present = $records->whereIn('status', ['present','late','excused'])->count();
        $absent = $total - $present;
        $late = $records->where('status', 'late')->count();
        $percent = round(($present / $total) * 100, 2);
        $required = $this->requiredPercent($tenantId, $courseId, $classId);
        return LearnerEligibilitySummary::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $userId, 'course_id' => $courseId, 'class_id' => $classId],
            ['attendance_percent' => $percent, 'absent_count' => max(0, $absent), 'late_count' => $late, 'eligible_for_exam' => $percent >= $required, 'reason' => $percent >= $required ? null : 'Chuyên cần dưới '.$required.'%', 'updated_at' => now()]
        );
    }

    public function requiredPercent(int $tenantId, int $courseId, ?int $classId = null): float
    {
        $rule = EligibilityRule::query()->where('tenant_id', $tenantId)->where('rule_type', 'exam_eligibility')->where('status', 'active')->where(function ($q) use ($courseId) { $q->whereNull('course_id')->orWhere('course_id', $courseId); })->when($classId, fn ($q) => $q->where(function ($qq) use ($classId) { $qq->whereNull('class_id')->orWhere('class_id', $classId); }))->latest('id')->first();
        return (float) ($rule->config['min_attendance_percent'] ?? 80);
    }
}
