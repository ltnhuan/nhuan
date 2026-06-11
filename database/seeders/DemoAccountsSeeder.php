<?php

namespace Database\Seeders;

use App\Models\AcademicUnit;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Badge;
use App\Models\Certificate;
use App\Models\ClassSection;
use App\Models\Cohort;
use App\Models\CohortGroup;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamEnrollment;
use App\Models\GradeApprovalBatch;
use App\Models\GradeCategory;
use App\Models\GradeItem;
use App\Models\Gradebook;
use App\Models\IntegrationSystem;
use App\Models\LearnerGrade;
use App\Models\LearnerRiskProfile;
use App\Models\LmsUser;
use App\Models\Role;
use App\Models\RiskAlert;
use App\Models\SyncJob;
use App\Models\TeacherAssignment;
use App\Models\Tenant;
use App\Models\UserCourseProgress;
use App\Services\DigitalCredentialService;
use App\Services\GradeFormulaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoAccountsSeeder extends Seeder
{
    private const PASSWORD = 'admin123456';

    public function run(): void
    {
        $tenant = Tenant::query()->where('code', 'VABIS')->firstOrFail();
        $accounts = $this->seedAccounts($tenant);
        $courses = $this->seedDemoCourses($tenant, $accounts);
        [$cohort, $group] = $this->seedCohort($tenant);

        $studentClasses = $this->seedStudentDashboard($tenant, $accounts, $courses, $cohort, $group);
        $this->seedTeacherDashboard($tenant, $accounts, $courses, $cohort, $group, $studentClasses);
        $this->seedTrainingDashboard($tenant, $accounts, $courses, $cohort, $group);
        $this->seedFacultyDashboard($tenant, $accounts, $courses, $cohort, $group);
        $this->seedExecutiveDashboard($tenant, $accounts, $courses);
    }

    private function seedAccounts(Tenant $tenant): array
    {
        $definitions = [
            'admin' => ['admin.lms@vabis.edu.vn', 'ADMIN', 'Admin LMS', 'admin', 'tenant_admin', 'admin_lms'],
            'training' => ['daotao.lms@vabis.edu.vn', 'DAOTAO', 'Phòng Đào tạo Demo', 'staff', 'training_officer', 'training'],
            'faculty' => ['khoa.lms@vabis.edu.vn', 'KHOA', 'Khoa Công nghệ Demo', 'staff', 'faculty_manager', 'faculty'],
            'teacher' => ['gv.lms@vabis.edu.vn', 'GVDEMO', 'Giảng viên Demo', 'teacher', 'teacher', 'teacher'],
            'student' => ['sv.lms@vabis.edu.vn', 'SVDEMO', 'Sinh viên Demo', 'student', 'student', 'student'],
            'parent' => ['phuhuynh.lms@vabis.edu.vn', 'PHDEMO', 'Phụ huynh Demo', 'parent', 'parent', 'parent'],
            'executive' => ['bgh.lms@vabis.edu.vn', 'BGH', 'Ban Giám hiệu Demo', 'admin', 'academic_admin', 'executive'],
        ];

        $users = [];
        foreach ($definitions as $key => [$email, $code, $name, $type, $roleName, $persona]) {
            $metadata = [
                'demo' => true,
                'demo_password' => self::PASSWORD,
                'demo_persona' => $persona,
                'dashboard_label' => $name,
            ];
            if ($key === 'parent') {
                $metadata['child_email'] = 'sv.lms@vabis.edu.vn';
            }

            $user = LmsUser::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'email' => $email],
                [
                    'code' => $code,
                    'full_name' => $name,
                    'user_type' => $type,
                    'status' => 'active',
                    'metadata' => $metadata,
                ]
            );

            $role = Role::query()->where('tenant_id', $tenant->id)->where('name', $roleName)->first();
            if ($role) {
                DB::table('user_role_scope')->updateOrInsert([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'tenant_id' => $tenant->id,
                    'campus_id' => null,
                    'academic_unit_id' => null,
                    'course_id' => null,
                    'class_id' => null,
                ]);
                Cache::forget("eralms:permissions:{$tenant->id}:{$user->id}");
            }

            $users[$key] = $user;
        }

        return $users;
    }

    private function seedDemoCourses(Tenant $tenant, array $accounts)
    {
        $unit = AcademicUnit::query()->where('tenant_id', $tenant->id)->where('name', 'Khoa Công nghệ')->first()
            ?: AcademicUnit::query()->where('tenant_id', $tenant->id)->first();
        $titles = [
            'DEMO-CNC-101' => 'An toàn xưởng và 5S',
            'DEMO-CNC-102' => 'Đọc bản vẽ kỹ thuật',
            'DEMO-CNC-103' => 'Vận hành máy CNC cơ bản',
            'DEMO-CNC-104' => 'Đo kiểm chất lượng sản phẩm',
            'DEMO-CNC-105' => 'Kỹ năng làm việc doanh nghiệp',
            'DEMO-CNC-106' => 'Tiếng Anh kỹ thuật nền tảng',
            'DEMO-CNC-107' => 'Dự án tốt nghiệp mô phỏng',
            'DEMO-PUB-01' => 'PLC ứng dụng trong dây chuyền',
            'DEMO-PUB-02' => 'AI trong quản lý học tập',
        ];

        return collect($titles)->map(function (string $title, string $code) use ($tenant, $accounts, $unit) {
            $pending = in_array($code, ['DEMO-PUB-01', 'DEMO-PUB-02'], true);

            return Course::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $code],
                [
                    'title' => $title,
                    'slug' => Str::slug($code.'-'.$title),
                    'short_description' => 'Khóa demo có dữ liệu học tập, đánh giá và tiến độ thật cho dashboard.',
                    'description' => 'Dữ liệu demo phục vụ trình diễn theo vai trò.',
                    'academic_unit_id' => $unit?->id,
                    'level' => 'college',
                    'course_type' => 'blended',
                    'status' => $pending ? 'pending_publish' : 'published',
                    'visibility' => 'internal',
                    'language' => 'vi',
                    'estimated_hours' => $pending ? 18 : 30,
                    'owner_id' => $accounts['teacher']->id,
                    'approved_by' => $pending ? null : $accounts['training']->id,
                    'approved_at' => $pending ? null : now()->subDays(20),
                    'published_at' => $pending ? null : now()->subDays(18),
                    'settings' => ['demo' => true, 'publish_readiness' => $pending ? 86 : 100],
                ]
            );
        })->values();
    }

    private function seedCohort(Tenant $tenant): array
    {
        $cohort = Cohort::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'DEMO-K2026'],
            ['name' => 'Khóa demo 2026', 'type' => 'academic', 'status' => 'active', 'start_date' => now()->subMonths(2)->toDateString(), 'end_date' => now()->addMonths(5)->toDateString(), 'metadata' => ['demo' => true]]
        );
        $group = CohortGroup::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'cohort_id' => $cohort->id, 'code' => 'DEMO-CNC-A'],
            ['name' => 'Nhóm CNC A', 'group_type' => 'learning_group', 'capacity' => 35, 'status' => 'active', 'metadata' => ['demo' => true]]
        );

        return [$cohort, $group];
    }

    private function seedStudentDashboard(Tenant $tenant, array $accounts, $courses, Cohort $cohort, CohortGroup $group)
    {
        $student = $accounts['student'];
        $classes = collect();
        $progress = [42, 58, 73, 21, 86, 100, 100];

        foreach ($courses->take(7)->values() as $index => $course) {
            $class = $this->classSection($tenant, $course, $cohort, $group, 'SV', $index + 1, $index >= 5 ? 'completed' : 'active');
            $classes->push($class);
            $status = $index >= 5 ? 'completed' : 'active';
            Enrollment::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'class_section_id' => $class->id, 'user_id' => $student->id],
                [
                    'course_id' => $course->id,
                    'cohort_id' => $cohort->id,
                    'cohort_group_id' => $group->id,
                    'source' => 'seed',
                    'sis_enrollment_id' => 'SIS-DEMO-SV-'.($index + 1),
                    'status' => $status,
                    'completion_percent' => $progress[$index],
                    'risk_score' => $index === 3 ? 62 : 18 + ($index * 4),
                    'enrolled_at' => now()->subDays(45 - $index),
                    'activated_at' => now()->subDays(44 - $index),
                    'completed_at' => $status === 'completed' ? now()->subDays(7 - $index) : null,
                    'expires_at' => now()->addMonths(3),
                    'created_by' => $accounts['training']->id,
                    'metadata' => ['demo' => true, 'dashboard' => 'student'],
                ]
            );
            UserCourseProgress::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $student->id, 'course_id' => $course->id],
                [
                    'status' => $status === 'completed' ? 'completed' : 'in_progress',
                    'progress_percent' => $progress[$index],
                    'completed_components_count' => $status === 'completed' ? 12 : 3 + $index,
                    'total_components_count' => 12,
                    'completed_required_count' => $status === 'completed' ? 12 : 2 + $index,
                    'total_required_count' => 12,
                    'last_accessed_at' => now()->subHours(4 + $index),
                    'risk_level' => $index === 3 ? 'medium' : 'low',
                    'metadata' => ['demo' => true],
                ]
            );
        }

        $assignment = Assignment::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'course_id' => $courses[0]->id, 'title' => 'Báo cáo an toàn xưởng cá nhân'],
            [
                'description' => 'Sinh viên phân tích tình huống an toàn và nộp file minh chứng.',
                'assignment_type' => 'individual',
                'submission_type' => 'mixed',
                'status' => 'published',
                'open_at' => now()->subDays(3),
                'due_at' => now()->addDays(2),
                'allow_late' => true,
                'max_score' => 10,
                'pass_score' => 5,
                'max_submissions' => 2,
                'settings' => ['demo' => true, 'student_pending' => true],
                'created_by' => $accounts['teacher']->id,
            ]
        );
        AssignmentSubmission::query()
            ->where('tenant_id', $tenant->id)
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $student->id)
            ->delete();

        $exam = Exam::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'DEMO-QUIZ-SV-PENDING'],
            [
                'course_id' => $courses[1]->id,
                'title' => 'Quiz kiểm tra bản vẽ kỹ thuật',
                'description' => 'Quiz demo chưa làm của sinh viên.',
                'exam_type' => 'quiz',
                'delivery_mode' => 'self_paced',
                'status' => 'published',
                'total_score' => 10,
                'pass_score' => 5,
                'duration_minutes' => 20,
                'max_attempts' => 1,
                'show_result_mode' => 'immediately',
                'created_by' => $accounts['teacher']->id,
                'approved_by' => $accounts['training']->id,
                'approved_at' => now()->subDays(2),
                'settings' => ['demo' => true],
            ]
        );
        ExamEnrollment::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'exam_id' => $exam->id, 'user_id' => $student->id],
            ['course_id' => $courses[1]->id, 'class_id' => $classes[1]->id, 'status' => 'assigned', 'assigned_by' => $accounts['teacher']->id, 'available_from' => now()->subDay(), 'available_until' => now()->addWeek(), 'metadata' => ['demo' => true, 'pending' => true]]
        );

        $certificate = Certificate::query()->where('tenant_id', $tenant->id)->first();
        $badge = Badge::query()->where('tenant_id', $tenant->id)->first();
        $credentialService = app(DigitalCredentialService::class);
        if ($certificate) {
            $credentialService->issueCertificate($tenant->id, [
                'certificate_id' => $certificate->id,
                'user_id' => $student->id,
                'course_id' => $courses[5]->id,
                'issue_code' => 'CERT-SVDEMO-001',
                'field_values' => ['course_title' => $courses[5]->title],
                'sis_payload' => ['sync_status' => 'queued', 'demo' => true],
            ]);
        }
        if ($badge) {
            $credentialService->issueBadge($tenant->id, [
                'badge_id' => $badge->id,
                'user_id' => $student->id,
                'course_id' => $courses[6]->id,
                'issue_code' => 'BADGE-SVDEMO-001',
                'evidence' => ['demo' => true, 'score' => 92, 'reason' => 'Hoàn thành dự án mô phỏng'],
            ]);
        }

        LearnerRiskProfile::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $student->id, 'course_id' => $courses[3]->id],
            ['risk_score' => 62, 'risk_level' => 'medium', 'risk_factors' => ['Nộp bài chậm 1 lần', 'Tiến độ video thấp hơn lớp 18%'], 'recommendations' => ['Hoàn tất assignment trước hạn', 'Xem lại bài đo kiểm'], 'last_calculated_at' => now()]
        );

        return $classes;
    }

    private function seedTeacherDashboard(Tenant $tenant, array $accounts, $courses, Cohort $cohort, CohortGroup $group, $studentClasses): void
    {
        $teacher = $accounts['teacher'];
        $students = LmsUser::query()->where('tenant_id', $tenant->id)->where('user_type', 'student')->limit(12)->get();

        foreach ($studentClasses->take(3)->values() as $class) {
            TeacherAssignment::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'class_section_id' => $class->id, 'user_id' => $teacher->id, 'role' => 'lead_teacher'],
                ['course_id' => $class->course_id, 'status' => 'active', 'assigned_at' => now()->subMonth(), 'metadata' => ['demo' => true]]
            );
        }

        $assignment = Assignment::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'course_id' => $courses[2]->id, 'title' => 'Bài thực hành CNC - Lập trình G-code'],
            ['description' => 'Bài tập có 10 submission chờ chấm.', 'assignment_type' => 'individual', 'submission_type' => 'file', 'status' => 'published', 'open_at' => now()->subDays(10), 'due_at' => now()->subDay(), 'allow_late' => true, 'max_score' => 10, 'pass_score' => 5, 'settings' => ['demo' => true, 'teacher_pending_grading' => true], 'created_by' => $teacher->id]
        );

        foreach ($students->take(10)->values() as $index => $student) {
            AssignmentSubmission::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'assignment_id' => $assignment->id, 'user_id' => $student->id, 'submission_no' => 1],
                ['status' => $index % 4 === 0 ? 'late_submitted' : 'submitted', 'submitted_at' => now()->subHours(18 - $index), 'content_text' => 'Bài nộp demo chờ giảng viên chấm.', 'metadata' => ['demo' => true]]
            );
        }

        foreach ($courses->slice(7, 2) as $course) {
            $course->forceFill([
                'status' => 'pending_publish',
                'owner_id' => $teacher->id,
                'settings' => array_merge($course->settings ?? [], ['demo_pending_publish' => true, 'publish_readiness' => 86]),
            ])->save();
        }
    }

    private function seedTrainingDashboard(Tenant $tenant, array $accounts, $courses, Cohort $cohort, CohortGroup $group): void
    {
        foreach (range(1, 20) as $i) {
            $course = $courses[($i - 1) % 7];
            $this->classSection($tenant, $course, $cohort, $group, 'DT', $i, 'active');
        }

        foreach (range(1, 5) as $i) {
            $course = $courses[($i - 1) % 7];
            $gradebook = Gradebook::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'course_id' => $course->id, 'title' => 'Sổ điểm chờ duyệt demo '.$i],
                ['class_id' => null, 'grading_scheme' => 'weighted', 'status' => 'active', 'settings' => ['demo' => true], 'created_by' => $accounts['teacher']->id]
            );
            $category = GradeCategory::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'gradebook_id' => $gradebook->id, 'title' => 'Tổng kết'],
                ['weight' => 100, 'max_score' => 10, 'aggregation_method' => 'weighted', 'sort_order' => 1]
            );
            $item = GradeItem::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'gradebook_id' => $gradebook->id, 'title' => 'Điểm tổng kết'],
                ['category_id' => $category->id, 'source_type' => 'manual', 'max_score' => 10, 'weight' => 100, 'required' => true, 'sort_order' => 1, 'settings' => ['demo' => true]]
            );
            LearnerGrade::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'grade_item_id' => $item->id, 'user_id' => $accounts['student']->id],
                ['gradebook_id' => $gradebook->id, 'raw_score' => 8 + ($i / 10), 'final_score' => 8 + ($i / 10), 'pass_status' => 'passed', 'source_status' => 'final', 'updated_by' => $accounts['teacher']->id]
            );
            app(GradeFormulaService::class)->calculateLearner($gradebook, collect([$item]), $accounts['student']->id);
            GradeApprovalBatch::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'gradebook_id' => $gradebook->id, 'title' => 'Đợt duyệt điểm demo '.$i],
                ['status' => 'submitted', 'submitted_by' => $accounts['teacher']->id, 'submitted_at' => now()->subDays($i), 'sync_status' => 'waiting_approval', 'metadata' => ['demo' => true, 'queue' => 'training_approval']]
            );
        }

        $system = IntegrationSystem::query()->where('tenant_id', $tenant->id)->where('code', 'SIS-MOCK')->first();
        if ($system) {
            foreach ([
                ['pull', 'students', 'SIS trả về 12 học viên thiếu mã ngành'],
                ['pull', 'classes', '3 lớp chưa có mapping phòng học'],
                ['push', 'grades', 'Điểm tổng kết bị SIS từ chối do khóa bảng điểm'],
            ] as [$jobType, $entityType, $summary]) {
                SyncJob::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'system_id' => $system->id, 'job_type' => 'demo_'.$jobType, 'entity_type' => $entityType],
                    ['status' => 'failed', 'total_count' => 100, 'success_count' => 88, 'failed_count' => 12, 'started_at' => now()->subHours(3), 'finished_at' => now()->subHours(2), 'error_report' => ['summary' => $summary, 'owner' => 'Phòng Đào tạo'], 'created_by' => $accounts['training']->id]
                );
            }
        }
    }

    private function seedFacultyDashboard(Tenant $tenant, array $accounts, $courses, Cohort $cohort, CohortGroup $group): void
    {
        foreach (range(1, 6) as $i) {
            $class = $this->classSection($tenant, $courses[($i - 1) % 7], $cohort, $group, 'KHOA', $i, 'active');
            $class->forceFill(['metadata' => array_merge($class->metadata ?? [], ['faculty_dashboard' => true, 'capacity_used' => 24 + $i])])->save();
        }
    }

    private function seedExecutiveDashboard(Tenant $tenant, array $accounts, $courses): void
    {
        foreach (range(1, 5) as $i) {
            $student = LmsUser::query()->where('tenant_id', $tenant->id)->where('code', 'SV'.str_pad((string) $i, 5, '0', STR_PAD_LEFT))->first();
            if (! $student) {
                continue;
            }
            LearnerRiskProfile::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $student->id, 'course_id' => $courses[($i - 1) % 7]->id],
                ['risk_score' => 70 + $i, 'risk_level' => $i > 3 ? 'critical' : 'high', 'risk_factors' => ['Ít đăng nhập', 'Chưa nộp bài quan trọng'], 'recommendations' => ['Cố vấn học tập liên hệ trong tuần'], 'last_calculated_at' => now()]
            );
            RiskAlert::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $student->id, 'course_id' => $courses[($i - 1) % 7]->id, 'alert_type' => 'dropout_risk'],
                ['risk_profile_id' => null, 'severity' => $i > 3 ? 'critical' : 'high', 'status' => 'open', 'message' => 'Học viên có nguy cơ không hoàn thành đúng hạn.', 'recommended_actions' => ['Gọi cố vấn', 'Mở lớp phụ đạo'], 'triggered_at' => now()->subDays($i), 'assigned_to' => $accounts['training']->id]
            );
        }
    }

    private function classSection(Tenant $tenant, Course $course, Cohort $cohort, CohortGroup $group, string $prefix, int $index, string $status): ClassSection
    {
        return ClassSection::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'DEMO-'.$prefix.'-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT)],
            [
                'course_id' => $course->id,
                'cohort_id' => $cohort->id,
                'cohort_group_id' => $group->id,
                'sis_section_id' => 'SIS-DEMO-'.$prefix.'-'.$index,
                'name' => $course->title.' - Lớp demo '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'section_type' => 'class_section',
                'delivery_mode' => $index % 3 === 0 ? 'online' : 'blended',
                'status' => $status,
                'capacity' => 35,
                'starts_at' => now()->subWeeks(4)->addDays($index),
                'ends_at' => now()->addMonths(2)->addDays($index),
                'schedule' => ['weekday' => ['T2', 'T4', 'T6'][$index % 3], 'slot' => $index % 2 === 0 ? '08:00-10:00' : '13:30-15:30'],
                'metadata' => ['demo' => true],
            ]
        );
    }
}
