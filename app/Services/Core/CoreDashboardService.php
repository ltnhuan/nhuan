<?php

namespace App\Services\Core;

use App\Models\AcademicUnit;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\BadgeIssue;
use App\Models\Campus;
use App\Models\CertificateIssue;
use App\Models\ClassSection;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ExamEnrollment;
use App\Models\GradeApprovalBatch;
use App\Models\LearnerRiskProfile;
use App\Models\LmsUser;
use App\Models\RiskAlert;
use App\Models\SyncJob;
use App\Models\TeacherAssignment;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class CoreDashboardService
{
    public function summary(int $tenantId, ?LmsUser $viewer = null): array
    {
        return [
            'tenant' => Tenant::query()->find($tenantId),
            'campuses_count' => Campus::query()->where('tenant_id', $tenantId)->count(),
            'academic_units_count' => AcademicUnit::query()->where('tenant_id', $tenantId)->count(),
            'students_count' => LmsUser::query()->where('tenant_id', $tenantId)->where('user_type', 'student')->count(),
            'teachers_staff_count' => LmsUser::query()->where('tenant_id', $tenantId)->whereIn('user_type', ['teacher', 'staff', 'admin'])->count(),
            'locked_users_count' => LmsUser::query()->where('tenant_id', $tenantId)->where('status', 'locked')->count(),
            'recent_audit_logs' => AuditLog::query()->where('tenant_id', $tenantId)->latest('created_at')->limit(10)->get(),
            'demo_dashboard' => $viewer ? $this->demoDashboard($tenantId, $viewer) : null,
        ];
    }

    private function demoDashboard(int $tenantId, LmsUser $viewer): ?array
    {
        $persona = $viewer->metadata['demo_persona'] ?? null;
        if (! $persona) {
            return null;
        }

        return match ($persona) {
            'student' => $this->studentDashboard($tenantId, $viewer),
            'teacher' => $this->teacherDashboard($tenantId, $viewer),
            'training' => $this->trainingDashboard($tenantId, $viewer),
            'faculty' => $this->facultyDashboard($tenantId, $viewer),
            'parent' => $this->parentDashboard($tenantId, $viewer),
            'executive' => $this->executiveDashboard($tenantId, $viewer),
            default => $this->adminDashboard($tenantId, $viewer),
        };
    }

    private function studentDashboard(int $tenantId, LmsUser $student, ?string $titlePrefix = null): array
    {
        $enrollments = Enrollment::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $student->id)
            ->whereHas('classSection', fn ($query) => $query->where('code', 'like', 'DEMO-SV-%'))
            ->with(['course:id,code,title,status', 'classSection:id,code,name,status'])
            ->latest('updated_at')
            ->get();

        $active = $enrollments->where('status', 'active')->values();
        $completed = $enrollments->where('status', 'completed')->values();
        $courseIds = $enrollments->pluck('course_id')->filter()->unique()->values();
        $assignmentIds = AssignmentSubmission::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $student->id)
            ->pluck('assignment_id');
        $pendingAssignments = Assignment::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('course_id', $courseIds)
            ->where('status', 'published')
            ->whereNotIn('id', $assignmentIds)
            ->where(function ($query) {
                $query->whereNull('due_at')->orWhere('due_at', '>=', now()->subDay());
            })
            ->with('course:id,code,title')
            ->orderBy('due_at')
            ->limit(5)
            ->get();
        $attemptedExamIds = DB::table('exam_attempts')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $student->id)
            ->pluck('exam_id');
        $pendingQuizzes = ExamEnrollment::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $student->id)
            ->whereNotIn('exam_id', $attemptedExamIds)
            ->with(['exam:id,code,title,course_id,duration_minutes', 'exam.course:id,code,title'])
            ->limit(5)
            ->get();
        $certificates = CertificateIssue::query()->where('tenant_id', $tenantId)->where('user_id', $student->id)->where('issue_code', 'like', 'CERT-SVDEMO-%')->latest()->get();
        $badges = BadgeIssue::query()->where('tenant_id', $tenantId)->where('user_id', $student->id)->where('issue_code', 'like', 'BADGE-SVDEMO-%')->with('badge')->latest()->get();
        $risk = LearnerRiskProfile::query()->where('tenant_id', $tenantId)->where('user_id', $student->id)->orderByDesc('risk_score')->first();

        return [
            'persona' => 'student',
            'title' => $titlePrefix ?: 'Dashboard Sinh viên',
            'subtitle' => 'Theo dõi khóa đang học, hoàn thành, credential và việc cần làm trong tuần.',
            'cards' => [
                ['label' => 'Khóa đang học', 'value' => $active->count(), 'detail' => 'Mục tiêu demo: 5 khóa', 'tone' => 'blue'],
                ['label' => 'Khóa hoàn thành', 'value' => $completed->count(), 'detail' => 'Mục tiêu demo: 2 khóa', 'tone' => 'emerald'],
                ['label' => 'Certificate', 'value' => $certificates->count(), 'detail' => $certificates->first()?->certificate_title ?: 'Chưa có chứng chỉ', 'tone' => 'amber'],
                ['label' => 'Badge', 'value' => $badges->count(), 'detail' => $badges->first()?->badge?->name ?: 'Chưa có badge', 'tone' => 'violet'],
                ['label' => 'Assignment chờ nộp', 'value' => $pendingAssignments->count(), 'detail' => $pendingAssignments->first()?->title ?: 'Không có bài chờ', 'tone' => 'rose'],
                ['label' => 'Quiz chưa làm', 'value' => $pendingQuizzes->count(), 'detail' => $pendingQuizzes->first()?->exam?->title ?: 'Không có quiz chờ', 'tone' => 'slate'],
            ],
            'worklist' => [
                ...$active->take(5)->map(fn ($item) => ['label' => $item->course?->title, 'meta' => $item->classSection?->name, 'status' => $item->completion_percent.'% hoàn thành', 'href' => '/courses/learn?course_id='.$item->course_id])->all(),
                ...$pendingAssignments->take(1)->map(fn ($item) => ['label' => $item->title, 'meta' => $item->course?->title, 'status' => 'Chờ nộp', 'href' => '/assignments/submission'])->all(),
                ...$pendingQuizzes->take(1)->map(fn ($item) => ['label' => $item->exam?->title, 'meta' => $item->exam?->course?->title, 'status' => 'Chưa làm', 'href' => '/exams/take'])->all(),
            ],
            'signals' => [
                ['label' => 'Risk score', 'value' => round((float) ($risk?->risk_score ?? $enrollments->max('risk_score') ?? 0), 1), 'detail' => $risk?->risk_level ?? 'low'],
                ['label' => 'Progress trung bình', 'value' => round((float) $enrollments->avg('completion_percent'), 1).'%', 'detail' => 'Tính từ enrollment live'],
                ['label' => 'Credential ví số', 'value' => $certificates->count() + $badges->count(), 'detail' => 'Certificate + badge'],
            ],
        ];
    }

    private function teacherDashboard(int $tenantId, LmsUser $teacher): array
    {
        $classes = ClassSection::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('code', ['DEMO-SV-01', 'DEMO-SV-02', 'DEMO-SV-03'])
            ->whereHas('teacherAssignments', fn ($query) => $query->where('user_id', $teacher->id)->where('status', 'active'))
            ->with(['course:id,code,title'])
            ->withCount('enrollments')
            ->latest('updated_at')
            ->get();
        $courseIds = $classes->pluck('course_id')->unique()->values();
        $pendingSubmissions = AssignmentSubmission::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['submitted', 'late_submitted'])
            ->whereHas('assignment', fn ($query) => $query->whereIn('course_id', $courseIds)->where('title', 'Bài thực hành CNC - Lập trình G-code'))
            ->whereDoesntHave('grade')
            ->with(['assignment:id,title,course_id', 'user:id,code,full_name'])
            ->latest('submitted_at')
            ->get();
        $pendingCourses = Course::query()
            ->where('tenant_id', $tenantId)
            ->where('owner_id', $teacher->id)
            ->where('status', 'pending_publish')
            ->latest('updated_at')
            ->get();

        return [
            'persona' => 'teacher',
            'title' => 'Dashboard Giảng viên',
            'subtitle' => 'Tập trung vào lớp phụ trách, bài chờ chấm và khóa học đang chờ publish.',
            'cards' => [
                ['label' => 'Lớp phụ trách', 'value' => $classes->count(), 'detail' => 'Mục tiêu demo: 3 lớp', 'tone' => 'blue'],
                ['label' => 'Bài tập chờ chấm', 'value' => $pendingSubmissions->count(), 'detail' => 'Mục tiêu demo: 10 bài', 'tone' => 'amber'],
                ['label' => 'Khóa chờ publish', 'value' => $pendingCourses->count(), 'detail' => 'Mục tiêu demo: 2 khóa', 'tone' => 'violet'],
                ['label' => 'Sinh viên trong lớp', 'value' => $classes->sum('enrollments_count'), 'detail' => 'Theo lớp active', 'tone' => 'emerald'],
            ],
            'worklist' => [
                ...$classes->take(3)->map(fn ($item) => ['label' => $item->name, 'meta' => $item->course?->title, 'status' => $item->enrollments_count.' học viên', 'href' => '/learning-path/class-progress'])->all(),
                ...$pendingSubmissions->take(5)->map(fn ($item) => ['label' => $item->assignment?->title, 'meta' => $item->user?->full_name, 'status' => $item->status, 'href' => '/assignments/grading'])->all(),
                ...$pendingCourses->take(2)->map(fn ($item) => ['label' => $item->title, 'meta' => $item->code, 'status' => 'Chờ publish', 'href' => '/courses/studio?course_id='.$item->id])->all(),
            ],
            'signals' => [
                ['label' => 'Tải chấm', 'value' => $pendingSubmissions->count(), 'detail' => 'Submission chưa có grade'],
                ['label' => 'Publish readiness', 'value' => round((float) $pendingCourses->avg(fn ($item) => $item->settings['publish_readiness'] ?? 0)).'%', 'detail' => '2 khóa gần publish'],
                ['label' => 'Lớp online/blended', 'value' => $classes->pluck('delivery_mode')->unique()->count(), 'detail' => 'Hình thức giảng dạy'],
            ],
        ];
    }

    private function trainingDashboard(int $tenantId, LmsUser $viewer): array
    {
        $classes = ClassSection::query()->where('tenant_id', $tenantId)->where('code', 'like', 'DEMO-DT-%')->with('course:id,code,title')->latest('updated_at')->take(20)->get();
        $gradeBatches = GradeApprovalBatch::query()->where('tenant_id', $tenantId)->where('status', 'submitted')->with('gradebook:id,title,course_id')->latest('submitted_at')->take(5)->get();
        $syncErrors = SyncJob::query()->where('tenant_id', $tenantId)->where('status', 'failed')->latest('finished_at')->take(3)->get();

        return [
            'persona' => 'training',
            'title' => 'Dashboard Đào tạo',
            'subtitle' => 'Theo dõi vận hành lớp, gradebook chờ duyệt và lỗi đồng bộ SIS.',
            'cards' => [
                ['label' => 'Lớp đang vận hành', 'value' => $classes->count(), 'detail' => 'Mục tiêu demo: 20 lớp', 'tone' => 'blue'],
                ['label' => 'Gradebook chờ duyệt', 'value' => $gradeBatches->count(), 'detail' => 'Mục tiêu demo: 5 sổ điểm', 'tone' => 'amber'],
                ['label' => 'Sync SIS lỗi', 'value' => $syncErrors->count(), 'detail' => 'Mục tiêu demo: 3 lỗi', 'tone' => 'rose'],
                ['label' => 'Tổng sức chứa lớp', 'value' => $classes->sum('capacity'), 'detail' => 'Capacity các lớp demo', 'tone' => 'emerald'],
            ],
            'worklist' => [
                ...$gradeBatches->map(fn ($item) => ['label' => $item->title, 'meta' => $item->gradebook?->title, 'status' => 'Chờ duyệt', 'href' => '/gradebook/approval'])->all(),
                ...$syncErrors->map(fn ($item) => ['label' => strtoupper($item->entity_type), 'meta' => $item->error_report['summary'] ?? $item->job_type, 'status' => 'failed', 'href' => '/sis/sync-jobs'])->all(),
            ],
            'signals' => [
                ['label' => 'Completion lớp', 'value' => round((float) Enrollment::query()->where('tenant_id', $tenantId)->avg('completion_percent'), 1).'%', 'detail' => 'Trung bình enrollment'],
                ['label' => 'SIS failed rows', 'value' => $syncErrors->sum('failed_count'), 'detail' => 'Cần xử lý mapping'],
                ['label' => 'Grade SLA', 'value' => $gradeBatches->where('submitted_at', '<', now()->subDays(2))->count(), 'detail' => 'Batch quá 48h'],
            ],
        ];
    }

    private function facultyDashboard(int $tenantId, LmsUser $viewer): array
    {
        $classes = ClassSection::query()->where('tenant_id', $tenantId)->where('code', 'like', 'DEMO-KHOA-%')->with('course:id,code,title,status')->withCount('enrollments')->latest('updated_at')->get();
        $courseIds = $classes->pluck('course_id')->unique()->values();
        $teachers = TeacherAssignment::query()->where('tenant_id', $tenantId)->whereIn('course_id', $courseIds)->where('status', 'active')->distinct('user_id')->count('user_id');
        $risks = LearnerRiskProfile::query()->where('tenant_id', $tenantId)->whereIn('course_id', $courseIds)->whereIn('risk_level', ['medium', 'high', 'critical'])->count();

        return [
            'persona' => 'faculty',
            'title' => 'Dashboard Khoa',
            'subtitle' => 'Góc nhìn theo khoa: lớp, học phần, giảng viên và sinh viên có rủi ro.',
            'cards' => [
                ['label' => 'Lớp của khoa', 'value' => $classes->count(), 'detail' => 'Các lớp active', 'tone' => 'blue'],
                ['label' => 'Học phần published', 'value' => Course::query()->where('tenant_id', $tenantId)->whereIn('id', $courseIds)->where('status', 'published')->count(), 'detail' => 'Sẵn sàng giảng dạy', 'tone' => 'emerald'],
                ['label' => 'Giảng viên active', 'value' => $teachers, 'detail' => 'Theo teacher assignment', 'tone' => 'violet'],
                ['label' => 'Risk cần theo dõi', 'value' => $risks, 'detail' => 'Medium trở lên', 'tone' => 'amber'],
            ],
            'worklist' => $classes->take(8)->map(fn ($item) => ['label' => $item->name, 'meta' => $item->course?->title, 'status' => $item->enrollments_count.' học viên', 'href' => '/enrollment'])->all(),
            'signals' => [
                ['label' => 'Sức chứa', 'value' => $classes->sum('capacity'), 'detail' => 'Tổng capacity'],
                ['label' => 'Blended/online', 'value' => $classes->pluck('delivery_mode')->unique()->count(), 'detail' => 'Hình thức lớp'],
                ['label' => 'Course health', 'value' => round($classes->avg(fn ($item) => $item->metadata['capacity_used'] ?? 0), 1), 'detail' => 'Chỉ báo demo'],
            ],
        ];
    }

    private function parentDashboard(int $tenantId, LmsUser $parent): array
    {
        $childEmail = $parent->metadata['child_email'] ?? null;
        $child = $childEmail ? LmsUser::query()->where('tenant_id', $tenantId)->where('email', $childEmail)->first() : null;

        if (! $child) {
            return [
                'persona' => 'parent',
                'title' => 'Dashboard Phụ huynh',
                'subtitle' => 'Chưa gắn sinh viên theo dõi.',
                'cards' => [],
                'worklist' => [],
                'signals' => [],
            ];
        }

        $dashboard = $this->studentDashboard($tenantId, $child, 'Dashboard Phụ huynh');
        $dashboard['persona'] = 'parent';
        $dashboard['subtitle'] = 'Theo dõi tiến độ, việc cần làm và credential của '.$child->full_name.'.';
        $dashboard['signals'][] = ['label' => 'Sinh viên', 'value' => $child->code, 'detail' => $child->full_name];

        return $dashboard;
    }

    private function executiveDashboard(int $tenantId, LmsUser $viewer): array
    {
        $enrollments = Enrollment::query()->where('tenant_id', $tenantId);
        $riskAvg = LearnerRiskProfile::query()->where('tenant_id', $tenantId)->avg('risk_score');
        $completionRate = (clone $enrollments)->count()
            ? round((clone $enrollments)->where('status', 'completed')->count() * 100 / (clone $enrollments)->count(), 1)
            : 0;
        $openAlerts = RiskAlert::query()->where('tenant_id', $tenantId)->where('status', 'open')->count();
        $gradePending = GradeApprovalBatch::query()->where('tenant_id', $tenantId)->where('status', 'submitted')->count();
        $sisErrors = SyncJob::query()->where('tenant_id', $tenantId)->where('status', 'failed')->count();

        return [
            'persona' => 'executive',
            'title' => 'Dashboard BGH',
            'subtitle' => 'Dashboard tổng hợp về risk score, completion rate và KPI đào tạo.',
            'cards' => [
                ['label' => 'Risk score', 'value' => round((float) $riskAvg, 1), 'detail' => $openAlerts.' cảnh báo mở', 'tone' => 'rose'],
                ['label' => 'Completion rate', 'value' => $completionRate.'%', 'detail' => 'Theo enrollment live', 'tone' => 'emerald'],
                ['label' => 'KPI đào tạo', 'value' => max(0, 100 - $gradePending - $sisErrors * 2).'%', 'detail' => 'Tổng hợp duyệt điểm + SIS', 'tone' => 'blue'],
                ['label' => 'Lớp active', 'value' => ClassSection::query()->where('tenant_id', $tenantId)->where('status', 'active')->count(), 'detail' => 'Toàn tenant', 'tone' => 'violet'],
            ],
            'worklist' => [
                ['label' => 'Gradebook chờ duyệt', 'meta' => 'Phòng Đào tạo', 'status' => $gradePending.' batch', 'href' => '/gradebook/approval'],
                ['label' => 'Lỗi đồng bộ SIS', 'meta' => 'Integration Hub', 'status' => $sisErrors.' job failed', 'href' => '/sis/sync-jobs'],
                ['label' => 'Cảnh báo rủi ro học tập', 'meta' => 'Learning Analytics', 'status' => $openAlerts.' alert', 'href' => '/analytics'],
            ],
            'signals' => [
                ['label' => 'KPI lớp mở', 'value' => ClassSection::query()->where('tenant_id', $tenantId)->where('status', 'active')->count(), 'detail' => 'Lớp đang chạy'],
                ['label' => 'KPI publish', 'value' => Course::query()->where('tenant_id', $tenantId)->where('status', 'published')->count(), 'detail' => 'Khóa published'],
                ['label' => 'KPI kiểm soát lỗi', 'value' => $sisErrors, 'detail' => 'Sync SIS lỗi'],
            ],
        ];
    }

    private function adminDashboard(int $tenantId, LmsUser $viewer): array
    {
        return [
            'persona' => 'admin_lms',
            'title' => 'Dashboard Admin LMS',
            'subtitle' => 'Tổng quan tenant, người dùng, module và tình trạng vận hành nền tảng.',
            'cards' => [
                ['label' => 'Người học', 'value' => LmsUser::query()->where('tenant_id', $tenantId)->where('user_type', 'student')->count(), 'detail' => 'Tài khoản active/demo', 'tone' => 'blue'],
                ['label' => 'Giảng viên/cán bộ', 'value' => LmsUser::query()->where('tenant_id', $tenantId)->whereIn('user_type', ['teacher', 'staff', 'admin'])->count(), 'detail' => 'Bao gồm demo personas', 'tone' => 'emerald'],
                ['label' => 'Khóa học', 'value' => Course::query()->where('tenant_id', $tenantId)->count(), 'detail' => Course::query()->where('tenant_id', $tenantId)->where('status', 'published')->count().' published', 'tone' => 'violet'],
                ['label' => 'Lỗi SIS', 'value' => SyncJob::query()->where('tenant_id', $tenantId)->where('status', 'failed')->count(), 'detail' => 'Job cần xử lý', 'tone' => 'rose'],
            ],
            'worklist' => [
                ['label' => 'Kiểm tra hệ thống', 'meta' => 'System check', 'status' => 'Sẵn sàng', 'href' => '/admin/lms/system-check'],
                ['label' => 'Quản lý người dùng', 'meta' => 'RBAC demo', 'status' => 'Active', 'href' => '/settings/users'],
                ['label' => 'Moodle parity', 'meta' => 'Action registry', 'status' => 'Theo dõi', 'href' => '/moodle-parity'],
            ],
            'signals' => [
                ['label' => 'Campus', 'value' => Campus::query()->where('tenant_id', $tenantId)->count(), 'detail' => 'Cơ sở'],
                ['label' => 'Đơn vị', 'value' => AcademicUnit::query()->where('tenant_id', $tenantId)->count(), 'detail' => 'Khoa/phòng'],
                ['label' => 'Audit gần đây', 'value' => AuditLog::query()->where('tenant_id', $tenantId)->count(), 'detail' => 'Log hệ thống'],
            ],
        ];
    }
}
