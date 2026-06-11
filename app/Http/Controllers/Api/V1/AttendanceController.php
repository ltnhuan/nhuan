<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\LearnerEligibilitySummary;
use App\Models\LiveSession;
use App\Services\AttendanceCheckinService;
use App\Services\AttendanceSessionService;
use App\Services\EligibilityService;
use App\Services\LiveSessionService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AttendanceController extends Controller
{
    public function liveSessions(Request $request, TenantContext $tenant)
    {
        return LiveSession::query()->where('tenant_id', $tenant->id())->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->integer('course_id')))->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))->latest('start_at')->paginate($request->integer('per_page', 25));
    }

    public function storeLiveSession(Request $request, TenantContext $tenant, LiveSessionService $service)
    {
        return response()->json($service->create($request->all() + ['tenant_id' => $tenant->id(), 'created_by' => $request->user()?->id ?? 1]), 201);
    }

    public function updateLiveSession(Request $request, LiveSession $liveSession, LiveSessionService $service)
    {
        return $service->update($liveSession, $request->all());
    }

    public function startLiveSession(LiveSession $liveSession, LiveSessionService $service)
    {
        return $service->start($liveSession);
    }

    public function completeLiveSession(LiveSession $liveSession, LiveSessionService $service)
    {
        return $service->complete($liveSession);
    }

    public function attendanceSessions(Request $request, TenantContext $tenant)
    {
        return AttendanceSession::query()->where('tenant_id', $tenant->id())->withCount('records')->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->integer('course_id')))->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))->latest('open_at')->paginate($request->integer('per_page', 25));
    }

    public function storeAttendanceSession(Request $request, TenantContext $tenant, AttendanceSessionService $service)
    {
        return response()->json($service->create($request->all() + ['tenant_id' => $tenant->id(), 'created_by' => $request->user()?->id ?? 1]), 201);
    }

    public function openAttendanceSession(AttendanceSession $attendanceSession, AttendanceSessionService $service)
    {
        return $service->open($attendanceSession);
    }

    public function closeAttendanceSession(AttendanceSession $attendanceSession, AttendanceSessionService $service)
    {
        return $service->close($attendanceSession);
    }

    public function lockAttendanceSession(Request $request, AttendanceSession $attendanceSession, AttendanceSessionService $service)
    {
        return $service->lock($attendanceSession, $request->user()?->id ?? 1);
    }

    public function checkinQr(Request $request, AttendanceCheckinService $service)
    {
        return $service->checkinQr($request->input('qr_token'), $this->userId($request));
    }

    public function checkinOtp(Request $request, AttendanceCheckinService $service)
    {
        return $service->checkinOtp($request->integer('attendance_session_id'), $request->input('otp_code'), $this->userId($request));
    }

    public function manualUpdate(Request $request, AttendanceCheckinService $service)
    {
        $session = AttendanceSession::query()->findOrFail($request->integer('attendance_session_id'));
        return $service->manualUpdate($session, $request->integer('user_id'), $request->input('status', 'present'), $request->user()?->id ?? 1, $request->input('note'), $request->boolean('allow_locked_override'));
    }

    public function attendanceSummary(Course $course, Request $request, TenantContext $tenant)
    {
        return LearnerEligibilitySummary::query()->where('tenant_id', $tenant->id())->where('course_id', $course->id)->when($request->filled('class_id'), fn ($q) => $q->where('class_id', $request->integer('class_id')))->orderBy('attendance_percent')->paginate($request->integer('per_page', 100));
    }

    public function recalculateEligibility(Course $course, Request $request, TenantContext $tenant, EligibilityService $service)
    {
        return $service->recalculateCourse((int) $tenant->id(), (int) $course->id, $request->filled('class_id') ? $request->integer('class_id') : null);
    }

    private function userId(Request $request): int
    {
        return $request->user()?->id ?? $request->integer('user_id', 1);
    }
}
