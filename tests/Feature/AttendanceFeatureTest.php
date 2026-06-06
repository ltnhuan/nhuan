<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\EligibilityRule;
use App\Models\LmsUser;
use App\Services\AttendanceCheckinService;
use App\Services\AttendanceSessionService;
use App\Services\EligibilityService;
use App\Services\LiveSessionService;
use Database\Seeders\CoreSeeder;
use Database\Seeders\CourseStudioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->seed(CourseStudioSeeder::class);
    }

    public function test_create_live_session(): void
    {
        $course = Course::query()->firstOrFail();
        $session = app(LiveSessionService::class)->create(['tenant_id' => 1, 'course_id' => $course->id, 'title' => 'Workshop online', 'provider' => 'zoom', 'meeting_url' => 'https://zoom.example.test/1', 'start_at' => now()->addHour(), 'end_at' => now()->addHours(2), 'created_by' => 1]);

        $this->assertSame('scheduled', $session->status);
        $this->assertSame('zoom', $session->provider);
    }

    public function test_open_qr_attendance_and_checkin_qr(): void
    {
        [$session, $student] = $this->attendanceSession('qr');
        $opened = app(AttendanceSessionService::class)->open($session);

        $record = app(AttendanceCheckinService::class)->checkinQr($opened->qr_token, $student->id);

        $this->assertSame('open', $opened->status);
        $this->assertNotEmpty($opened->qr_token);
        $this->assertSame('present', $record->status);
        $this->assertSame('qr', $record->source);
    }

    public function test_checkin_otp(): void
    {
        [$session, $student] = $this->attendanceSession('otp');
        $opened = app(AttendanceSessionService::class)->open($session);

        $record = app(AttendanceCheckinService::class)->checkinOtp($opened->id, $opened->otp_code, $student->id);

        $this->assertSame('present', $record->status);
        $this->assertSame('otp', $record->source);
    }

    public function test_manual_update(): void
    {
        [$session, $student] = $this->attendanceSession('manual');

        $record = app(AttendanceCheckinService::class)->manualUpdate($session, $student->id, 'excused', 1, 'Có đơn xin phép');

        $this->assertSame('excused', $record->status);
        $this->assertSame('Có đơn xin phép', $record->note);
    }

    public function test_locked_attendance_blocks_normal_update(): void
    {
        [$session, $student] = $this->attendanceSession('manual');
        app(AttendanceSessionService::class)->lock($session, 1);

        $this->expectException(\RuntimeException::class);
        app(AttendanceCheckinService::class)->manualUpdate($session->fresh(), $student->id, 'present', 1);
    }

    public function test_attendance_percent_and_exam_eligibility_under_80_percent(): void
    {
        $course = Course::query()->firstOrFail();
        $student = LmsUser::query()->where('user_type', 'student')->firstOrFail();
        EligibilityRule::query()->create(['tenant_id' => 1, 'course_id' => $course->id, 'rule_type' => 'exam_eligibility', 'title' => 'Chuyên cần >= 80%', 'config' => ['min_attendance_percent' => 80], 'status' => 'active', 'created_by' => 1]);

        foreach (range(1, 10) as $i) {
            $session = AttendanceSession::query()->create(['tenant_id' => 1, 'course_id' => $course->id, 'title' => 'Điểm danh '.$i, 'attendance_type' => 'manual', 'open_at' => now()->subDay(), 'close_at' => now()->addDay(), 'status' => 'closed', 'created_by' => 1]);
            AttendanceRecord::query()->create(['tenant_id' => 1, 'attendance_session_id' => $session->id, 'user_id' => $student->id, 'status' => $i <= 7 ? 'present' : 'absent', 'source' => 'manual']);
        }

        app(EligibilityService::class)->recalculateCourse(1, $course->id);
        $summary = app(EligibilityService::class)->calculateLearner(1, $course->id, null, $student->id);

        $this->assertSame(70.0, (float) $summary->attendance_percent);
        $this->assertFalse($summary->eligible_for_exam);
        $this->assertSame('Chuyên cần dưới 80%', $summary->reason);
    }

    private function attendanceSession(string $type): array
    {
        $course = Course::query()->firstOrFail();
        $student = LmsUser::query()->where('user_type', 'student')->firstOrFail();
        $session = AttendanceSession::query()->create(['tenant_id' => 1, 'course_id' => $course->id, 'title' => 'Điểm danh test', 'attendance_type' => $type, 'open_at' => now()->subMinutes(5), 'close_at' => now()->addMinutes(30), 'status' => 'draft', 'created_by' => 1, 'settings' => []]);
        return [$session, $student];
    }
}
