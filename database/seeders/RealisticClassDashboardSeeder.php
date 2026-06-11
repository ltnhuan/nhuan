<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassSection;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GradeCategory;
use App\Models\GradeItem;
use App\Models\GradeSummary;
use App\Models\Gradebook;
use App\Models\LearnerEligibilitySummary;
use App\Models\LearnerGrade;
use App\Models\LearnerRiskProfile;
use App\Models\LmsUser;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RealisticClassDashboardSeeder extends Seeder
{
    private const SEED_KEY = 'realistic_class_dashboard';

    public function run(): void
    {
        $tenant = Tenant::query()->where('code', 'VABIS')->firstOrFail();
        $teacherId = LmsUser::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('user_type', ['teacher', 'staff', 'admin'])
            ->value('id') ?: 1;

        $classes = $this->classes();
        $now = now();
        $totalLearners = 0;

        foreach ($classes as $classIndex => $spec) {
            $course = $this->course($tenant->id, $teacherId, $spec);
            $class = $this->classSection($tenant->id, $course->id, $spec);
            $this->teacherAssignment($tenant->id, $course->id, $class->id, $teacherId);

            $gradebook = $this->gradebook($tenant->id, $course->id, $class->id, $teacherId, $spec['name']);
            $gradeItems = $this->gradeItems($tenant->id, $gradebook->id);
            $sessions = $this->attendanceSessions($tenant->id, $course->id, $class->id, $teacherId, $spec['name']);

            $learners = [];
            $summaries = [];

            for ($i = 1; $i <= $spec['size']; $i++) {
                $persona = $this->persona($i, $spec['size']);
                $profile = $this->profile($persona, $i, $classIndex);
                $learner = $this->learner($tenant->id, $spec, $i, $persona);

                $learners[] = ['id' => $learner->id, 'persona' => $persona, 'profile' => $profile];
                $this->enrollment($tenant->id, $course->id, $class->id, $learner->id, $spec, $persona, $profile);
                $this->grades($tenant->id, $gradebook->id, $gradeItems, $learner->id, $profile, $teacherId);
                $this->attendance($tenant->id, $course->id, $class->id, $sessions, $learner->id, $persona, $profile);
                $this->analytics($tenant->id, $course->id, $class->id, $learner->id, $persona, $profile);

                $summaries[] = $this->summaryRow($persona, $profile);
                $totalLearners++;
            }

            $this->classAnalyticsSummary($tenant->id, $class->id, $spec['size'], $summaries, $now);
        }

        $this->command?->info("Seeded ".count($classes)." realistic classes and {$totalLearners} learners for dashboard testing.");
    }

    private function classes(): array
    {
        return [
            ['code' => '9PLUS-CNTT-K24A', 'name' => '9+ CNTT K24A', 'level' => '9+', 'major' => 'CNTT', 'size' => 42],
            ['code' => '9PLUS-CNTT-K24B', 'name' => '9+ CNTT K24B', 'level' => '9+', 'major' => 'CNTT', 'size' => 39],
            ['code' => '9PLUS-DL-K24A', 'name' => '9+ Du lịch K24A', 'level' => '9+', 'major' => 'Du lịch', 'size' => 36],
            ['code' => 'TC-CNTT-K23A', 'name' => 'TC CNTT K23A', 'level' => 'Trung cấp', 'major' => 'CNTT', 'size' => 45],
            ['code' => 'TC-CNTT-K23B', 'name' => 'TC CNTT K23B', 'level' => 'Trung cấp', 'major' => 'CNTT', 'size' => 41],
            ['code' => 'TC-DL-K23A', 'name' => 'TC Du lịch K23A', 'level' => 'Trung cấp', 'major' => 'Du lịch', 'size' => 35],
            ['code' => 'CD-CNTT-K22A', 'name' => 'CĐ CNTT K22A', 'level' => 'Cao đẳng', 'major' => 'CNTT', 'size' => 48],
            ['code' => 'CD-CNTT-K22B', 'name' => 'CĐ CNTT K22B', 'level' => 'Cao đẳng', 'major' => 'CNTT', 'size' => 44],
            ['code' => 'CD-DL-K22A', 'name' => 'CĐ Du lịch K22A', 'level' => 'Cao đẳng', 'major' => 'Du lịch', 'size' => 38],
            ['code' => 'CD-MKT-K22A', 'name' => 'CĐ Marketing K22A', 'level' => 'Cao đẳng', 'major' => 'Marketing', 'size' => 40],
            ['code' => 'TOEIC-FOUNDATION-A', 'name' => 'TOEIC Foundation A', 'level' => 'Ngoại ngữ', 'major' => 'TOEIC', 'size' => 34],
            ['code' => 'TOEIC-500-PLUS', 'name' => 'TOEIC 500+', 'level' => 'Ngoại ngữ', 'major' => 'TOEIC', 'size' => 32],
            ['code' => 'IELTS-45', 'name' => 'IELTS 4.5', 'level' => 'Ngoại ngữ', 'major' => 'IELTS', 'size' => 31],
            ['code' => 'IELTS-60', 'name' => 'IELTS 6.0', 'level' => 'Ngoại ngữ', 'major' => 'IELTS', 'size' => 30],
            ['code' => 'HSK1', 'name' => 'HSK1', 'level' => 'Ngoại ngữ', 'major' => 'Tiếng Trung', 'size' => 33],
            ['code' => 'HSK2', 'name' => 'HSK2', 'level' => 'Ngoại ngữ', 'major' => 'Tiếng Trung', 'size' => 30],
            ['code' => 'TOPIK-I', 'name' => 'TOPIK I', 'level' => 'Ngoại ngữ', 'major' => 'Tiếng Hàn', 'size' => 30],
        ];
    }

    private function course(int $tenantId, int $teacherId, array $spec): Course
    {
        return Course::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'COURSE-'.$spec['code']],
            [
                'title' => 'Chương trình '.$spec['name'],
                'slug' => Str::slug('chuong-trinh-'.$spec['code']),
                'short_description' => 'Khóa học mẫu thực tế cho dashboard lớp '.$spec['name'],
                'description' => 'Dữ liệu lớp học gồm tiến độ, điểm, chuyên cần và cảnh báo rủi ro theo từng nhóm học viên.',
                'level' => $spec['level'],
                'course_type' => $spec['level'] === 'Ngoại ngữ' ? 'language' : 'academic',
                'status' => 'published',
                'visibility' => 'tenant',
                'language' => 'vi',
                'estimated_hours' => $spec['level'] === 'Ngoại ngữ' ? 90 : 240,
                'owner_id' => $teacherId,
                'approved_by' => $teacherId,
                'approved_at' => now()->subMonths(5),
                'published_at' => now()->subMonths(5),
                'settings' => ['seed' => self::SEED_KEY, 'major' => $spec['major']],
            ]
        );
    }

    private function classSection(int $tenantId, int $courseId, array $spec): ClassSection
    {
        return ClassSection::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => $spec['code']],
            [
                'course_id' => $courseId,
                'sis_section_id' => 'SIS-'.$spec['code'],
                'name' => $spec['name'],
                'section_type' => 'class_section',
                'delivery_mode' => $spec['level'] === 'Ngoại ngữ' ? 'blended' : 'onsite',
                'status' => 'active',
                'capacity' => max(50, $spec['size'] + 5),
                'starts_at' => now()->subMonths($spec['level'] === 'Ngoại ngữ' ? 3 : 8),
                'ends_at' => now()->addMonths($spec['level'] === 'Ngoại ngữ' ? 3 : 10),
                'schedule' => ['days' => ['T2', 'T4', 'T6'], 'time' => $spec['level'] === 'Ngoại ngữ' ? '18:00-20:00' : '07:30-11:30'],
                'metadata' => ['seed' => self::SEED_KEY, 'level' => $spec['level'], 'major' => $spec['major'], 'target_size' => $spec['size']],
            ]
        );
    }

    private function teacherAssignment(int $tenantId, int $courseId, int $classId, int $teacherId): void
    {
        DB::table('teacher_assignments')->updateOrInsert(
            ['tenant_id' => $tenantId, 'class_section_id' => $classId, 'user_id' => $teacherId, 'role' => 'homeroom_teacher'],
            ['course_id' => $courseId, 'status' => 'active', 'assigned_at' => now()->subMonths(6), 'metadata' => json_encode(['seed' => self::SEED_KEY]), 'created_at' => now(), 'updated_at' => now()]
        );
    }

    private function gradebook(int $tenantId, int $courseId, int $classId, int $teacherId, string $className): Gradebook
    {
        return Gradebook::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'course_id' => $courseId, 'class_id' => $classId],
            ['title' => 'Sổ điểm '.$className, 'grading_scheme' => 'weighted', 'status' => 'active', 'settings' => ['seed' => self::SEED_KEY, 'pass_percent' => 50], 'created_by' => $teacherId]
        );
    }

    private function gradeItems(int $tenantId, int $gradebookId): array
    {
        $items = [];
        foreach ([['Quiz', 'Kiểm tra thường xuyên', 25], ['Assignment', 'Bài tập', 25], ['Attendance', 'Chuyên cần', 10], ['Final', 'Thi cuối kỳ', 40]] as $order => [$source, $title, $weight]) {
            $category = GradeCategory::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'gradebook_id' => $gradebookId, 'title' => $title],
                ['weight' => $weight, 'max_score' => 10, 'aggregation_method' => 'weighted', 'sort_order' => $order + 1]
            );
            $items[$source] = GradeItem::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'gradebook_id' => $gradebookId, 'title' => $title],
                ['category_id' => $category->id, 'source_type' => strtolower($source), 'max_score' => 10, 'weight' => $weight, 'required' => true, 'sort_order' => $order + 1, 'settings' => ['seed' => self::SEED_KEY]]
            );
        }

        return $items;
    }

    private function attendanceSessions(int $tenantId, int $courseId, int $classId, int $teacherId, string $className): array
    {
        $sessions = [];
        for ($i = 1; $i <= 12; $i++) {
            $openAt = now()->subWeeks(12 - $i)->setTime(7, 30);
            $sessions[] = AttendanceSession::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'class_id' => $classId, 'title' => 'Điểm danh '.$className.' - Buổi '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)],
                [
                    'course_id' => $courseId,
                    'attendance_type' => $i % 3 === 0 ? 'manual' : 'qr',
                    'open_at' => $openAt,
                    'close_at' => $openAt->copy()->addMinutes(45),
                    'qr_token' => 'QR-'.$classId.'-'.$i,
                    'status' => 'closed',
                    'created_by' => $teacherId,
                    'settings' => ['seed' => self::SEED_KEY, 'min_attendance_minutes' => 60],
                ]
            );
        }

        return $sessions;
    }

    private function learner(int $tenantId, array $spec, int $index, string $persona): LmsUser
    {
        $code = 'HV-'.$spec['code'].'-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT);

        return LmsUser::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => $code],
            [
                'sis_user_id' => 'SIS-'.$code,
                'full_name' => $this->name($index, $spec['code']),
                'email' => strtolower($code).'@demo.vabis.edu.vn',
                'phone' => '09'.str_pad((string) ((crc32($code) % 90000000) + 10000000), 8, '0', STR_PAD_LEFT),
                'user_type' => 'student',
                'status' => $persona === 'dropout' ? 'inactive' : 'active',
                'metadata' => ['seed' => self::SEED_KEY, 'class_code' => $spec['code'], 'persona' => $persona, 'level' => $spec['level'], 'major' => $spec['major']],
            ]
        );
    }

    private function enrollment(int $tenantId, int $courseId, int $classId, int $userId, array $spec, string $persona, array $profile): void
    {
        Enrollment::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'class_section_id' => $classId, 'user_id' => $userId],
            [
                'course_id' => $courseId,
                'source' => 'seed',
                'sis_enrollment_id' => 'ENR-'.$spec['code'].'-'.$userId,
                'status' => $persona === 'dropout' ? 'withdrawn' : 'active',
                'completion_percent' => $profile['progress'],
                'risk_score' => $profile['risk'],
                'enrolled_at' => now()->subMonths(6),
                'activated_at' => now()->subMonths(6),
                'withdrawn_at' => $persona === 'dropout' ? now()->subWeeks(3) : null,
                'created_by' => 1,
                'metadata' => ['seed' => self::SEED_KEY, 'persona' => $persona, 'avg_grade' => $profile['grade'], 'attendance_percent' => $profile['attendance']],
            ]
        );
    }

    private function grades(int $tenantId, int $gradebookId, array $items, int $userId, array $profile, int $teacherId): void
    {
        $scores = [
            'Quiz' => $this->score10($profile['grade'] + $profile['variance']),
            'Assignment' => $this->score10($profile['grade'] + 0.4 - $profile['variance']),
            'Attendance' => $this->score10($profile['attendance'] / 10),
            'Final' => $this->score10($profile['grade'] - 0.2 + ($profile['variance'] / 2)),
        ];
        $weighted = round(($scores['Quiz'] * 0.25) + ($scores['Assignment'] * 0.25) + ($scores['Attendance'] * 0.10) + ($scores['Final'] * 0.40), 2);
        $percent = round($weighted * 10, 2);

        foreach ($items as $key => $item) {
            LearnerGrade::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'grade_item_id' => $item->id, 'user_id' => $userId],
                [
                    'gradebook_id' => $gradebookId,
                    'raw_score' => $scores[$key],
                    'final_score' => $scores[$key],
                    'letter_grade' => $this->letter($scores[$key] * 10),
                    'pass_status' => $scores[$key] >= 5 ? 'passed' : 'failed',
                    'source_status' => 'final',
                    'feedback' => $scores[$key] < 5 ? 'Cần hỗ trợ và nộp bổ sung.' : null,
                    'updated_by' => $teacherId,
                ]
            );
        }

        GradeSummary::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'gradebook_id' => $gradebookId, 'user_id' => $userId],
            [
                'total_score' => $weighted,
                'max_score' => 10,
                'percent' => $percent,
                'letter_grade' => $this->letter($percent),
                'pass_status' => $percent >= 50 ? 'passed' : 'failed',
                'status' => 'approved',
                'approved_by' => $teacherId,
                'approved_at' => now()->subDays(3),
                'metadata' => ['seed' => self::SEED_KEY],
            ]
        );
    }

    private function attendance(int $tenantId, int $courseId, int $classId, array $sessions, int $userId, string $persona, array $profile): void
    {
        $total = count($sessions);
        $attended = (int) round($total * ($profile['attendance'] / 100));
        $late = min(max(0, (int) floor((100 - $profile['attendance']) / 18)), max(0, $attended - 1));
        $absent = $total - $attended;

        foreach ($sessions as $index => $session) {
            $status = $index >= $attended ? 'absent' : ($index < $late ? 'late' : 'present');
            $checkin = $status === 'absent' ? null : $session->open_at->copy()->addMinutes($status === 'late' ? 22 : 4);
            AttendanceRecord::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'attendance_session_id' => $session->id, 'user_id' => $userId],
                [
                    'status' => $status,
                    'checkin_at' => $checkin,
                    'checkout_at' => $status === 'absent' ? null : $session->open_at->copy()->addMinutes(105),
                    'attended_minutes' => $status === 'absent' ? 0 : ($status === 'late' ? 72 : 100),
                    'source' => 'seed',
                    'note' => $status === 'absent' ? ($persona === 'dropout' ? 'Nghỉ kéo dài' : 'Vắng buổi học') : null,
                    'metadata' => ['seed' => self::SEED_KEY, 'persona' => $persona],
                ]
            );
        }

        LearnerEligibilitySummary::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $userId, 'course_id' => $courseId, 'class_id' => $classId],
            [
                'attendance_percent' => $profile['attendance'],
                'absent_count' => $absent,
                'late_count' => $late,
                'eligible_for_exam' => $profile['attendance'] >= 80 && $profile['grade'] >= 5,
                'reason' => $profile['attendance'] < 80 ? 'Chuyên cần dưới 80%' : null,
                'updated_at' => now(),
            ]
        );
    }

    private function analytics(int $tenantId, int $courseId, int $classId, int $userId, string $persona, array $profile): void
    {
        $rows = [];
        for ($week = 7; $week >= 0; $week--) {
            $date = now()->subWeeks($week)->startOfWeek()->toDateString();
            $trend = (7 - $week) * ($persona === 'dropout' ? -1.5 : 1.2);
            $progressSignal = max(0, min(100, $profile['progress'] - 8 + $trend));
            $rows[] = [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'course_id' => $courseId,
                'class_section_id' => $classId,
                'metric_date' => $date,
                'login_frequency' => max(0, (int) round($progressSignal / 18)),
                'study_time_minutes' => max(0, (int) round($progressSignal * 3.5)),
                'video_completion' => $progressSignal,
                'assignment_completion' => max(0, min(100, $progressSignal + ($profile['grade'] - 5) * 4)),
                'quiz_score' => max(0, min(100, $profile['grade'] * 10)),
                'attendance' => $profile['attendance'],
                'forum_activity' => max(0, (int) round($progressSignal / 25)),
                'metadata' => json_encode(['seed' => self::SEED_KEY, 'persona' => $persona]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('learning_metrics')->upsert(
            $rows,
            ['tenant_id', 'user_id', 'course_id', 'metric_date'],
            ['class_section_id', 'login_frequency', 'study_time_minutes', 'video_completion', 'assignment_completion', 'quiz_score', 'attendance', 'forum_activity', 'metadata', 'updated_at']
        );

        $risk = LearnerRiskProfile::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $userId, 'course_id' => $courseId],
            [
                'risk_score' => $profile['risk'],
                'risk_level' => $this->riskLevel($profile['risk']),
                'risk_factors' => $this->riskFactors($persona, $profile),
                'recommendations' => $this->recommendations($persona),
                'last_calculated_at' => now(),
            ]
        );

        if ($profile['risk'] >= 70) {
            DB::table('risk_alerts')->updateOrInsert(
                ['tenant_id' => $tenantId, 'user_id' => $userId, 'course_id' => $courseId, 'alert_type' => 'dropout_risk'],
                [
                    'risk_profile_id' => $risk->id,
                    'severity' => $profile['risk'] >= 90 ? 'critical' : 'high',
                    'status' => 'open',
                    'message' => $profile['risk'] >= 90 ? 'Học viên có nguy cơ bỏ học rất cao.' : 'Học viên có nguy cơ rớt môn.',
                    'recommended_actions' => json_encode($this->recommendations($persona)),
                    'triggered_at' => now()->subDays(2),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function classAnalyticsSummary(int $tenantId, int $classId, int $size, array $rows, $now): void
    {
        $avgProgress = round(array_sum(array_column($rows, 'progress')) / $size, 2);
        $avgGrade = round(array_sum(array_column($rows, 'grade_percent')) / $size, 2);
        $avgRisk = round(array_sum(array_column($rows, 'risk')) / $size, 2);
        $highRisk = count(array_filter($rows, fn ($row) => $row['risk'] >= 70 && $row['risk'] < 90));
        $criticalRisk = count(array_filter($rows, fn ($row) => $row['risk'] >= 90));
        $completionRate = round(count(array_filter($rows, fn ($row) => $row['progress'] >= 80)) * 100 / $size, 2);

        foreach (['daily' => now()->toDateString(), 'weekly' => now()->startOfWeek()->toDateString(), 'monthly' => now()->startOfMonth()->toDateString()] as $period => $start) {
            DB::table("analytics_{$period}_summaries")->updateOrInsert(
                ['tenant_id' => $tenantId, 'scope_type' => 'class_section', 'scope_id' => $classId, 'period_start' => $start],
                [
                    'period_end' => $period === 'daily' ? now()->toDateString() : ($period === 'weekly' ? now()->endOfWeek()->toDateString() : now()->endOfMonth()->toDateString()),
                    'learner_count' => $size,
                    'avg_progress' => $avgProgress,
                    'avg_grade' => $avgGrade,
                    'avg_engagement' => round(100 - $avgRisk, 2),
                    'avg_risk_score' => $avgRisk,
                    'high_risk_count' => $highRisk,
                    'critical_risk_count' => $criticalRisk,
                    'completion_rate' => $completionRate,
                    'chart_data' => json_encode(['personas' => array_count_values(array_column($rows, 'persona')), 'seed' => self::SEED_KEY]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function persona(int $index, int $total): string
    {
        $ratio = $index / $total;

        return match (true) {
            $ratio <= 0.20 => 'excellent',
            $ratio <= 0.65 => 'average',
            $ratio <= 0.90 => 'at_risk',
            default => 'dropout',
        };
    }

    private function profile(string $persona, int $index, int $classIndex): array
    {
        $offset = (($index * 7 + $classIndex * 11) % 9) - 4;
        $variance = $offset / 10;

        return match ($persona) {
            'excellent' => ['progress' => 90 + ($index % 8), 'grade' => 8.2 + (($index + $classIndex) % 15) / 10, 'attendance' => 92 + ($index % 8), 'risk' => 5 + ($index % 14), 'variance' => $variance],
            'average' => ['progress' => 55 + ($index % 24), 'grade' => 5.6 + (($index + $classIndex) % 19) / 10, 'attendance' => 72 + ($index % 17), 'risk' => 25 + ($index % 25), 'variance' => $variance],
            'at_risk' => ['progress' => 22 + ($index % 26), 'grade' => 3.2 + (($index + $classIndex) % 20) / 10, 'attendance' => 42 + ($index % 27), 'risk' => 70 + ($index % 19), 'variance' => $variance],
            default => ['progress' => $index % 18, 'grade' => (($index + $classIndex) % 28) / 10, 'attendance' => $index % 31, 'risk' => 90 + ($index % 11), 'variance' => $variance],
        };
    }

    private function summaryRow(string $persona, array $profile): array
    {
        return ['persona' => $persona, 'progress' => $profile['progress'], 'grade_percent' => $profile['grade'] * 10, 'risk' => $profile['risk']];
    }

    private function score10(float $score): float
    {
        return round(max(0, min(10, $score)), 2);
    }

    private function letter(float $percent): string
    {
        return match (true) {
            $percent >= 90 => 'A',
            $percent >= 80 => 'B+',
            $percent >= 70 => 'B',
            $percent >= 60 => 'C+',
            $percent >= 50 => 'C',
            default => 'F',
        };
    }

    private function riskLevel(float $risk): string
    {
        return match (true) {
            $risk >= 90 => 'critical',
            $risk >= 70 => 'high',
            $risk >= 40 => 'medium',
            default => 'low',
        };
    }

    private function riskFactors(string $persona, array $profile): array
    {
        $factors = [];
        if ($profile['attendance'] < 80) $factors[] = 'Chuyên cần thấp';
        if ($profile['grade'] < 5) $factors[] = 'Điểm trung bình dưới chuẩn';
        if ($profile['progress'] < 50) $factors[] = 'Tiến độ học chậm';
        if ($persona === 'dropout') $factors[] = 'Không còn hoạt động học tập';

        return $factors ?: ['Duy trì tốt'];
    }

    private function recommendations(string $persona): array
    {
        return match ($persona) {
            'excellent' => ['Giao bài nâng cao', 'Khuyến khích hỗ trợ bạn cùng lớp'],
            'average' => ['Nhắc hoàn thành bài còn thiếu', 'Ôn tập theo lộ trình tuần'],
            'at_risk' => ['Gọi tư vấn học tập', 'Bổ sung buổi phụ đạo', 'Theo dõi chuyên cần hằng tuần'],
            default => ['Liên hệ gia đình/người bảo trợ', 'Lập kế hoạch quay lại học', 'Cảnh báo cố vấn học tập'],
        };
    }

    private function name(int $index, string $classCode): string
    {
        $last = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Huỳnh', 'Võ', 'Đặng', 'Bùi', 'Đỗ', 'Hồ', 'Ngô'];
        $middle = ['Văn', 'Thị', 'Minh', 'Gia', 'Thanh', 'Hoài', 'Khánh', 'Anh', 'Quang', 'Bảo', 'Hữu', 'Ngọc'];
        $first = ['An', 'Bình', 'Châu', 'Dũng', 'Giang', 'Hà', 'Hân', 'Khang', 'Linh', 'Long', 'Mai', 'Nam', 'Nhi', 'Phúc', 'Quân', 'Thảo', 'Trang', 'Tú', 'Vy', 'Yến'];
        $seed = abs(crc32($classCode.'-'.$index));

        return $last[$seed % count($last)].' '.$middle[($seed >> 4) % count($middle)].' '.$first[($seed >> 8) % count($first)];
    }
}
