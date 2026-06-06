<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\EligibilityRule;
use App\Models\LiveSession;
use App\Models\LmsUser;
use App\Services\EligibilityService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 1;
        $courses = Course::query()->where('tenant_id', $tenantId)->limit(20)->get();
        if ($courses->isEmpty()) return;
        $students = LmsUser::query()->where('tenant_id', $tenantId)->where('user_type', 'student')->limit(50)->get();

        EligibilityRule::query()->updateOrCreate(['tenant_id' => $tenantId, 'course_id' => null, 'class_id' => null, 'rule_type' => 'exam_eligibility'], ['title' => 'Chuyên cần tối thiểu 80%', 'config' => ['min_attendance_percent' => 80], 'status' => 'active', 'created_by' => 1]);

        foreach (range(1, 100) as $i) {
            $course = $courses[($i - 1) % $courses->count()];
            $start = now()->subDays(120 - $i)->setTime(8 + ($i % 4), 0);
            $provider = ['zoom','google_meet','teams','jitsi','bbb','manual'][$i % 6];
            $live = LiveSession::query()->updateOrCreate(['tenant_id' => $tenantId, 'course_id' => $course->id, 'title' => 'Live session demo '.$i], ['class_id' => 2000 + (($i - 1) % 10), 'component_id' => null, 'description' => 'Buổi học online/workshop mẫu', 'provider' => $provider, 'meeting_url' => 'https://meet.example.edu/session-'.$i, 'external_meeting_id' => 'EXT-'.$provider.'-'.$i, 'start_at' => $start, 'end_at' => $start->copy()->addMinutes(90), 'status' => $i > 90 ? 'scheduled' : 'completed', 'attendance_required' => true, 'min_attendance_minutes' => 60, 'settings' => ['connector_placeholder' => true], 'created_by' => 1]);
            $type = ['qr','otp','manual','duration','auto'][$i % 5];
            $session = AttendanceSession::query()->updateOrCreate(['tenant_id' => $tenantId, 'live_session_id' => $live->id, 'title' => 'Điểm danh buổi '.$i], ['course_id' => $course->id, 'class_id' => $live->class_id, 'attendance_type' => $type, 'open_at' => $start->copy()->subMinutes(10), 'close_at' => $start->copy()->addMinutes(30), 'qr_token' => $type === 'qr' ? Str::random(40) : null, 'otp_code' => $type === 'otp' ? (string) (100000 + $i) : null, 'status' => $i % 10 === 0 ? 'locked' : 'closed', 'created_by' => 1, 'locked_by' => $i % 10 === 0 ? 1 : null, 'locked_at' => $i % 10 === 0 ? now() : null, 'settings' => ['min_attendance_minutes' => 60]]);

            foreach ($students as $index => $student) {
                $status = (($index + $i) % 10) < 8 ? 'present' : ((($index + $i) % 10) === 8 ? 'late' : 'absent');
                AttendanceRecord::query()->updateOrCreate(['tenant_id' => $tenantId, 'attendance_session_id' => $session->id, 'user_id' => $student->id], ['live_session_id' => $live->id, 'status' => $status, 'checkin_at' => $status === 'absent' ? null : $start->copy()->addMinutes($status === 'late' ? 18 : 3), 'checkout_at' => $status === 'absent' ? null : $start->copy()->addMinutes(88), 'attended_minutes' => $status === 'absent' ? 0 : ($status === 'late' ? 65 : 85), 'source' => $type === 'duration' ? 'live_provider' : $type, 'note' => $status === 'late' ? 'Vào lớp muộn' : null, 'verified_by' => $type === 'manual' ? 1 : null, 'metadata' => ['demo' => true]]);
            }
        }

        foreach ($courses as $course) app(EligibilityService::class)->recalculateCourse($tenantId, $course->id);
    }
}
