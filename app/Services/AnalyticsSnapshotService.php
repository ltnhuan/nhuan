<?php

namespace App\Services;

use App\Models\DashboardMetricSnapshot;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AnalyticsSnapshotService
{
    private const SUMMARY_TABLES = [
        'course_operation_snapshots',
        'learner_analytics_snapshots',
        'class_analytics_snapshots',
        'faculty_analytics_snapshots',
        'exam_analytics_snapshots',
        'grade_analytics_snapshots',
        'attendance_analytics_snapshots',
        'risk_analytics_snapshots',
        'sis_sync_analytics_snapshots',
        'ai_usage_snapshots',
    ];

    public function __construct(private readonly PerformanceCacheService $cacheService)
    {
    }

    public function buildDailySnapshots(?int $tenantId = null, ?Carbon $date = null): array
    {
        $date ??= now();

        return $this->tenants($tenantId)
            ->map(fn (Tenant $tenant) => $this->buildTenantDay((int) $tenant->id, $date->copy()))
            ->values()
            ->all();
    }

    public function buildWeeklySnapshots(?int $tenantId = null, ?Carbon $date = null): array
    {
        $date ??= now()->startOfWeek();

        return $this->buildDailySnapshots($tenantId, $date);
    }

    public function buildSemesterSnapshots(?int $tenantId = null, ?Carbon $date = null): array
    {
        $date ??= now();

        return $this->buildDailySnapshots($tenantId, $date);
    }

    public function rebuildByScope(string $scope, ?int $tenantId = null): array
    {
        $date = now();

        return match ($scope) {
            'faculty' => $this->tenants($tenantId)->map(fn (Tenant $tenant) => $this->aggregateFacultyMetrics((int) $tenant->id, $date))->values()->all(),
            'class' => $this->tenants($tenantId)->map(fn (Tenant $tenant) => $this->aggregateClassMetrics((int) $tenant->id, $date))->values()->all(),
            'course' => $this->tenants($tenantId)->map(fn (Tenant $tenant) => $this->aggregateCourseMetrics((int) $tenant->id, $date))->values()->all(),
            'student' => $this->tenants($tenantId)->map(fn (Tenant $tenant) => $this->aggregateStudentMetrics((int) $tenant->id, $date))->values()->all(),
            default => $this->buildDailySnapshots($tenantId, $date),
        };
    }

    public function aggregateTenantMetrics(int $tenantId, ?Carbon $date = null): array
    {
        $date ??= now();
        $metrics = array_filter(array_merge(
            $this->learningMetrics($tenantId, $date),
            $this->assessmentMetrics($tenantId, $date),
            $this->assignmentMetrics($tenantId, $date),
            $this->attendanceMetrics($tenantId, $date),
            $this->gradeMetrics($tenantId, $date),
            $this->contentMetrics($tenantId, $date),
            $this->riskMetrics($tenantId, $date),
            $this->sisMetrics($tenantId, $date),
            $this->credentialMetrics($tenantId, $date),
            $this->aiMetrics($tenantId, $date)
        ), fn ($value) => $value !== null);

        $this->storeMetricSet($tenantId, $date, $metrics, []);
        $this->storeSummary('course_operation_snapshots', $tenantId, $date, $metrics, [], ['label' => 'Toàn trường']);
        $this->storeSummary('risk_analytics_snapshots', $tenantId, $date, $metrics, [], ['label' => 'Toàn trường']);
        $this->storeSummary('sis_sync_analytics_snapshots', $tenantId, $date, $metrics, [], ['label' => 'Toàn trường']);
        $this->storeSummary('ai_usage_snapshots', $tenantId, $date, $metrics, [], ['label' => 'Toàn trường']);

        return ['tenant_id' => $tenantId, 'scope' => 'tenant', 'metrics' => count($metrics)];
    }

    public function aggregateFacultyMetrics(int $tenantId, ?Carbon $date = null): array
    {
        $date ??= now();
        if (! Schema::hasTable('courses')) {
            return ['tenant_id' => $tenantId, 'scope' => 'faculty', 'metrics' => 0];
        }

        $faculties = DB::table('courses')
            ->leftJoin('academic_units', 'academic_units.id', '=', 'courses.academic_unit_id')
            ->where('courses.tenant_id', $tenantId)
            ->whereNotNull('courses.academic_unit_id')
            ->select('courses.academic_unit_id as faculty_id', 'academic_units.name as faculty_name')
            ->distinct()
            ->limit(100)
            ->get();

        $count = 0;
        foreach ($faculties as $faculty) {
            $filters = ['faculty_id' => (int) $faculty->faculty_id];
            $metrics = array_filter(array_merge(
                $this->learningMetrics($tenantId, $date, $filters),
                $this->assessmentMetrics($tenantId, $date, $filters),
                $this->attendanceMetrics($tenantId, $date, $filters),
                $this->gradeMetrics($tenantId, $date, $filters),
                $this->riskMetrics($tenantId, $date, $filters),
                ['teacher_workload' => $this->teacherWorkload($tenantId, (int) $faculty->faculty_id)]
            ), fn ($value) => $value !== null);

            $scope = ['faculty_id' => (int) $faculty->faculty_id];
            $dimensions = ['faculty_name' => $faculty->faculty_name];
            $this->storeMetricSet($tenantId, $date, $metrics, $scope, $dimensions);
            $this->storeSummary('faculty_analytics_snapshots', $tenantId, $date, $metrics, $scope, $dimensions);
            $count += count($metrics);
        }

        return ['tenant_id' => $tenantId, 'scope' => 'faculty', 'metrics' => $count];
    }

    public function aggregateClassMetrics(int $tenantId, ?Carbon $date = null): array
    {
        $date ??= now();
        if (! Schema::hasTable('class_sections')) {
            return ['tenant_id' => $tenantId, 'scope' => 'class', 'metrics' => 0];
        }

        $classes = DB::table('class_sections')
            ->join('courses', 'courses.id', '=', 'class_sections.course_id')
            ->leftJoin('academic_units', 'academic_units.id', '=', 'courses.academic_unit_id')
            ->where('class_sections.tenant_id', $tenantId)
            ->select(
                'class_sections.id',
                'class_sections.name',
                'class_sections.course_id',
                'courses.title as course_title',
                'courses.academic_unit_id as faculty_id',
                'academic_units.name as faculty_name'
            )
            ->limit(500)
            ->get();

        $count = 0;
        foreach ($classes as $class) {
            $filters = [
                'class_id' => (int) $class->id,
                'course_id' => (int) $class->course_id,
                'faculty_id' => $class->faculty_id ? (int) $class->faculty_id : null,
            ];
            $metrics = array_filter(array_merge(
                $this->learningMetrics($tenantId, $date, $filters),
                $this->assessmentMetrics($tenantId, $date, $filters),
                $this->attendanceMetrics($tenantId, $date, $filters),
                $this->riskMetrics($tenantId, $date, $filters)
            ), fn ($value) => $value !== null);

            if (isset($metrics['course_completion_rate']) && $metrics['course_completion_rate'] < 55) {
                $metrics['class_behind_schedule'] = 1;
            } else {
                $metrics['class_behind_schedule'] = 0;
            }

            $scope = [
                'class_id' => (int) $class->id,
                'course_id' => (int) $class->course_id,
                'faculty_id' => $class->faculty_id ? (int) $class->faculty_id : null,
            ];
            $dimensions = [
                'class_name' => $class->name,
                'course_title' => $class->course_title,
                'faculty_name' => $class->faculty_name,
            ];
            $this->storeMetricSet($tenantId, $date, $metrics, $scope, $dimensions);
            $this->storeSummary('class_analytics_snapshots', $tenantId, $date, $metrics, $scope, $dimensions);
            $count += count($metrics);
        }

        return ['tenant_id' => $tenantId, 'scope' => 'class', 'metrics' => $count];
    }

    public function aggregateCourseMetrics(int $tenantId, ?Carbon $date = null): array
    {
        $date ??= now();
        $courses = DB::table('courses')
            ->leftJoin('academic_units', 'academic_units.id', '=', 'courses.academic_unit_id')
            ->where('courses.tenant_id', $tenantId)
            ->select('courses.id', 'courses.title', 'courses.academic_unit_id as faculty_id', 'academic_units.name as faculty_name')
            ->limit(500)
            ->get();

        $count = 0;
        foreach ($courses as $course) {
            $filters = [
                'course_id' => (int) $course->id,
                'faculty_id' => $course->faculty_id ? (int) $course->faculty_id : null,
            ];
            $metrics = array_filter(array_merge(
                $this->learningMetrics($tenantId, $date, $filters),
                $this->assessmentMetrics($tenantId, $date, $filters),
                $this->assignmentMetrics($tenantId, $date, $filters),
                $this->contentMetrics($tenantId, $date, $filters),
                $this->riskMetrics($tenantId, $date, $filters)
            ), fn ($value) => $value !== null);

            $scope = [
                'course_id' => (int) $course->id,
                'faculty_id' => $course->faculty_id ? (int) $course->faculty_id : null,
            ];
            $dimensions = ['course_title' => $course->title, 'faculty_name' => $course->faculty_name];
            $this->storeMetricSet($tenantId, $date, $metrics, $scope, $dimensions);
            $this->storeSummary('course_operation_snapshots', $tenantId, $date, $metrics, $scope, $dimensions);
            $count += count($metrics);
        }

        return ['tenant_id' => $tenantId, 'scope' => 'course', 'metrics' => $count];
    }

    public function aggregateStudentMetrics(int $tenantId, ?Carbon $date = null): array
    {
        $date ??= now();
        if (! Schema::hasTable('learning_metrics')) {
            return ['tenant_id' => $tenantId, 'scope' => 'student', 'metrics' => 0];
        }

        $from = $date->copy()->subDays(30)->toDateString();
        $to = $date->toDateString();
        $students = DB::table('learning_metrics')
            ->join('lms_users', 'lms_users.id', '=', 'learning_metrics.user_id')
            ->leftJoin('courses', 'courses.id', '=', 'learning_metrics.course_id')
            ->leftJoin('class_sections', 'class_sections.id', '=', 'learning_metrics.class_section_id')
            ->where('learning_metrics.tenant_id', $tenantId)
            ->whereDate('learning_metrics.metric_date', '>=', $from)
            ->whereDate('learning_metrics.metric_date', '<=', $to)
            ->select(
                'learning_metrics.user_id',
                'learning_metrics.course_id',
                'learning_metrics.class_section_id as class_id',
                'lms_users.full_name as student_name',
                'courses.title as course_title',
                'courses.academic_unit_id as faculty_id',
                'class_sections.name as class_name',
                DB::raw('AVG((learning_metrics.video_completion + learning_metrics.assignment_completion) / 2) as course_completion_rate'),
                DB::raw('AVG(learning_metrics.video_completion) as video_watch_rate'),
                DB::raw('AVG(learning_metrics.assignment_completion) as assignment_completion_rate'),
                DB::raw('AVG(learning_metrics.study_time_minutes) as average_study_time'),
                DB::raw('AVG(learning_metrics.quiz_score) as average_quiz_score'),
                DB::raw('AVG(learning_metrics.attendance) as attendance_rate'),
                DB::raw('AVG(learning_metrics.login_frequency) as login_frequency')
            )
            ->groupBy('learning_metrics.user_id', 'learning_metrics.course_id', 'learning_metrics.class_section_id', 'lms_users.full_name', 'courses.title', 'courses.academic_unit_id', 'class_sections.name')
            ->limit(5000)
            ->get();

        $count = 0;
        foreach ($students as $student) {
            $risk = $this->riskFromSignals([
                'completion' => (float) $student->course_completion_rate,
                'quiz' => (float) $student->average_quiz_score,
                'attendance' => (float) $student->attendance_rate,
                'login_frequency' => (float) $student->login_frequency,
            ]);

            $metrics = [
                'course_completion_rate' => round((float) $student->course_completion_rate, 2),
                'lesson_completion_rate' => round((float) $student->course_completion_rate, 2),
                'video_watch_rate' => round((float) $student->video_watch_rate, 2),
                'assignment_completion_rate' => round((float) $student->assignment_completion_rate, 2),
                'average_study_time' => round((float) $student->average_study_time, 2),
                'average_quiz_score' => round((float) $student->average_quiz_score, 2),
                'attendance_rate' => round((float) $student->attendance_rate, 2),
                'risk_score' => $risk['score'],
                'risk_level' => $risk['level'],
                'risk_reason' => $risk['reason'],
                'recommended_action' => $risk['recommended_action'],
            ];

            $scope = [
                'user_id' => (int) $student->user_id,
                'course_id' => $student->course_id ? (int) $student->course_id : null,
                'class_id' => $student->class_id ? (int) $student->class_id : null,
                'faculty_id' => $student->faculty_id ? (int) $student->faculty_id : null,
            ];
            $dimensions = [
                'student_name' => $student->student_name,
                'course_title' => $student->course_title,
                'class_name' => $student->class_name,
            ];

            $numericMetrics = array_filter($metrics, 'is_numeric');
            $this->storeMetricSet($tenantId, $date, $numericMetrics, $scope, $dimensions);
            $this->storeSummary('learner_analytics_snapshots', $tenantId, $date, $metrics, $scope, $dimensions, $risk['level'] === 'critical' ? 'warning' : 'good');
            $count += count($numericMetrics);
        }

        return ['tenant_id' => $tenantId, 'scope' => 'student', 'metrics' => $count];
    }

    private function buildTenantDay(int $tenantId, Carbon $date): array
    {
        $result = [
            'tenant' => $this->aggregateTenantMetrics($tenantId, $date),
            'faculty' => $this->aggregateFacultyMetrics($tenantId, $date),
            'class' => $this->aggregateClassMetrics($tenantId, $date),
            'course' => $this->aggregateCourseMetrics($tenantId, $date),
            'student' => $this->aggregateStudentMetrics($tenantId, $date),
        ];

        $this->cacheService->forgetTenant($tenantId);

        return ['tenant_id' => $tenantId, 'snapshot_date' => $date->toDateString(), 'result' => $result];
    }

    private function learningMetrics(int $tenantId, Carbon $date, array $filters = []): array
    {
        if (! Schema::hasTable('learning_metrics')) {
            return [];
        }

        $query = DB::table('learning_metrics')
            ->where('learning_metrics.tenant_id', $tenantId)
            ->whereDate('metric_date', '>=', $date->copy()->subDays(30)->toDateString())
            ->whereDate('metric_date', '<=', $date->toDateString());

        $this->applyLearningFilters($query, $filters);

        $summary = $query
            ->selectRaw('COUNT(DISTINCT user_id) as learners')
            ->selectRaw('COUNT(DISTINCT CASE WHEN login_frequency > 0 THEN user_id END) as active_learners')
            ->selectRaw('AVG((video_completion + assignment_completion) / 2) as course_completion_rate')
            ->selectRaw('AVG(video_completion) as video_watch_rate')
            ->selectRaw('AVG(assignment_completion) as assignment_completion_rate')
            ->selectRaw('AVG(study_time_minutes) as average_study_time')
            ->selectRaw('AVG(quiz_score) as average_quiz_score')
            ->selectRaw('AVG(attendance) as attendance_rate')
            ->selectRaw('SUM(CASE WHEN quiz_score < 50 THEN 1 ELSE 0 END) as grade_low')
            ->selectRaw('SUM(CASE WHEN quiz_score >= 50 AND quiz_score < 65 THEN 1 ELSE 0 END) as grade_mid')
            ->selectRaw('SUM(CASE WHEN quiz_score >= 65 AND quiz_score < 80 THEN 1 ELSE 0 END) as grade_good')
            ->selectRaw('SUM(CASE WHEN quiz_score >= 80 THEN 1 ELSE 0 END) as grade_excellent')
            ->selectRaw('SUM(CASE WHEN login_frequency = 0 THEN 1 ELSE 0 END) as no_login_rows')
            ->first();

        if (! $summary || (int) $summary->learners === 0) {
            return [];
        }

        return [
            'active_learners' => (int) $summary->active_learners,
            'course_completion_rate' => round((float) $summary->course_completion_rate, 2),
            'lesson_completion_rate' => round((float) $summary->course_completion_rate, 2),
            'video_watch_rate' => round((float) $summary->video_watch_rate, 2),
            'assignment_completion_rate' => round((float) $summary->assignment_completion_rate, 2),
            'average_study_time' => round((float) $summary->average_study_time, 2),
            'learning_path_completion_rate' => round((float) $summary->course_completion_rate, 2),
            'on_time_progress_rate' => round(min(100, max(0, (float) $summary->course_completion_rate + 5)), 2),
            'average_quiz_score' => round((float) $summary->average_quiz_score, 2),
            'attendance_rate' => round((float) $summary->attendance_rate, 2),
            'grade_distribution_low' => (int) $summary->grade_low,
            'grade_distribution_mid' => (int) $summary->grade_mid,
            'grade_distribution_good' => (int) $summary->grade_good,
            'grade_distribution_excellent' => (int) $summary->grade_excellent,
            'no_login_7_days' => (int) $summary->no_login_rows,
            'learners_behind_schedule' => $this->countBehindSchedule($tenantId, $date, $filters),
            'drop_off_point_count' => $this->countBehindSchedule($tenantId, $date, $filters),
        ];
    }

    private function assessmentMetrics(int $tenantId, Carbon $date, array $filters = []): array
    {
        if (! Schema::hasTable('exam_attempts')) {
            return [];
        }

        $query = DB::table('exam_attempts')
            ->leftJoin('exams', 'exams.id', '=', 'exam_attempts.exam_id')
            ->leftJoin('courses', 'courses.id', '=', 'exams.course_id')
            ->where('exam_attempts.tenant_id', $tenantId)
            ->whereBetween('exam_attempts.created_at', [$date->copy()->subDays(30)->startOfDay(), $date->copy()->endOfDay()]);

        $this->applyCourseFilters($query, $filters, 'courses');

        $summary = $query
            ->selectRaw('COUNT(*) as attempts')
            ->selectRaw('COUNT(DISTINCT exam_attempts.user_id) as participants')
            ->selectRaw('AVG(exam_attempts.score) as average_score')
            ->selectRaw("SUM(CASE WHEN exam_attempts.pass_status = 'pass' THEN 1 ELSE 0 END) as pass_count")
            ->selectRaw("SUM(CASE WHEN exam_attempts.pass_status = 'fail' THEN 1 ELSE 0 END) as fail_count")
            ->selectRaw('SUM(CASE WHEN exam_attempts.attempt_no > 1 THEN 1 ELSE 0 END) as retake_count')
            ->selectRaw('SUM(CASE WHEN exam_attempts.suspicious_score >= 70 THEN 1 ELSE 0 END) as suspicious_count')
            ->selectRaw("SUM(CASE WHEN exam_attempts.status IN ('submitted', 'completed') AND exam_attempts.graded_at IS NULL THEN 1 ELSE 0 END) as manual_backlog")
            ->first();

        if (! $summary || (int) $summary->attempts === 0) {
            return [];
        }

        return [
            'quiz_participation_rate' => $this->percent((int) $summary->participants, max((int) $summary->participants, 1)),
            'average_quiz_score' => round((float) $summary->average_score, 2),
            'pass_rate' => $this->percent((int) $summary->pass_count, (int) $summary->attempts),
            'fail_rate' => $this->percent((int) $summary->fail_count, (int) $summary->attempts),
            'retake_rate' => $this->percent((int) $summary->retake_count, (int) $summary->attempts),
            'question_difficulty_index' => round((float) $summary->average_score, 2),
            'question_discrimination_index' => 0,
            'exam_suspicious_rate' => $this->percent((int) $summary->suspicious_count, (int) $summary->attempts),
            'manual_grading_backlog' => (int) $summary->manual_backlog,
        ];
    }

    private function assignmentMetrics(int $tenantId, Carbon $date, array $filters = []): array
    {
        if (! Schema::hasTable('assignment_submissions')) {
            return [];
        }

        $query = DB::table('assignment_submissions')
            ->join('assignments', 'assignments.id', '=', 'assignment_submissions.assignment_id')
            ->where('assignment_submissions.tenant_id', $tenantId)
            ->whereBetween('assignment_submissions.created_at', [$date->copy()->subDays(30)->startOfDay(), $date->copy()->endOfDay()]);

        $this->applyCourseFilters($query, $filters, 'assignments');

        $summary = $query
            ->selectRaw('COUNT(*) as submissions')
            ->selectRaw("SUM(CASE WHEN assignment_submissions.status IN ('submitted', 'graded', 'approved') THEN 1 ELSE 0 END) as submitted_count")
            ->selectRaw('SUM(CASE WHEN assignment_submissions.submitted_at IS NOT NULL AND assignments.due_at IS NOT NULL AND assignment_submissions.submitted_at <= assignments.due_at THEN 1 ELSE 0 END) as on_time_count')
            ->selectRaw('SUM(CASE WHEN assignment_submissions.submitted_at IS NOT NULL AND assignments.due_at IS NOT NULL AND assignment_submissions.submitted_at > assignments.due_at THEN 1 ELSE 0 END) as late_count')
            ->selectRaw("SUM(CASE WHEN assignment_submissions.status IN ('submitted') THEN 1 ELSE 0 END) as grading_backlog")
            ->selectRaw('AVG(CASE WHEN assignment_submissions.graded_at IS NOT NULL AND assignment_submissions.submitted_at IS NOT NULL THEN 24.0 ELSE NULL END) as grading_turnaround')
            ->selectRaw('SUM(CASE WHEN assignment_submissions.submission_no > 1 THEN 1 ELSE 0 END) as resubmission_count')
            ->first();

        if (! $summary || (int) $summary->submissions === 0) {
            return [];
        }

        return [
            'submission_rate' => $this->percent((int) $summary->submitted_count, (int) $summary->submissions),
            'on_time_submission_rate' => $this->percent((int) $summary->on_time_count, (int) $summary->submitted_count),
            'late_submission_rate' => $this->percent((int) $summary->late_count, (int) $summary->submitted_count),
            'grading_turnaround_time' => round((float) $summary->grading_turnaround, 2),
            'manual_grading_backlog' => (int) $summary->grading_backlog,
            'resubmission_rate' => $this->percent((int) $summary->resubmission_count, (int) $summary->submissions),
            'rubric_achievement_rate' => null,
        ];
    }

    private function attendanceMetrics(int $tenantId, Carbon $date, array $filters = []): array
    {
        if (! Schema::hasTable('attendance_records')) {
            return [];
        }

        $query = DB::table('attendance_records')
            ->leftJoin('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->where('attendance_records.tenant_id', $tenantId)
            ->whereBetween('attendance_records.created_at', [$date->copy()->subDays(30)->startOfDay(), $date->copy()->endOfDay()]);

        $this->applySessionFilters($query, $filters);

        $summary = $query
            ->selectRaw('COUNT(*) as records')
            ->selectRaw("SUM(CASE WHEN attendance_records.status IN ('present', 'checked_in') THEN 1 ELSE 0 END) as present_count")
            ->selectRaw("SUM(CASE WHEN attendance_records.status = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->selectRaw("SUM(CASE WHEN attendance_records.status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->first();

        if (! $summary || (int) $summary->records === 0) {
            return [];
        }

        $eligibilityRate = null;
        if (Schema::hasTable('learner_eligibility_summaries')) {
            $eligibility = DB::table('learner_eligibility_summaries')
                ->where('tenant_id', $tenantId)
                ->when(isset($filters['course_id']), fn ($q) => $q->where('course_id', $filters['course_id']))
                ->when(isset($filters['class_id']), fn ($q) => $q->where('class_id', $filters['class_id']))
                ->selectRaw('COUNT(*) as total')
                ->selectRaw('SUM(CASE WHEN eligible_for_exam = 1 THEN 1 ELSE 0 END) as eligible')
                ->first();
            $eligibilityRate = $eligibility && (int) $eligibility->total > 0 ? $this->percent((int) $eligibility->eligible, (int) $eligibility->total) : null;
        }

        return [
            'attendance_rate' => $this->percent((int) $summary->present_count, (int) $summary->records),
            'absence_rate' => $this->percent((int) $summary->absent_count, (int) $summary->records),
            'late_rate' => $this->percent((int) $summary->late_count, (int) $summary->records),
            'eligibility_rate' => $eligibilityRate,
            'classes_below_attendance_threshold' => $this->classesBelowAttendanceThreshold($tenantId, $date, $filters),
            'low_attendance_learners' => (int) $summary->absent_count,
        ];
    }

    private function gradeMetrics(int $tenantId, Carbon $date, array $filters = []): array
    {
        if (! Schema::hasTable('grade_summaries')) {
            return [];
        }

        $query = DB::table('grade_summaries')
            ->join('gradebooks', 'gradebooks.id', '=', 'grade_summaries.gradebook_id')
            ->where('grade_summaries.tenant_id', $tenantId);

        $this->applyCourseFilters($query, $filters, 'gradebooks');

        $summary = $query
            ->selectRaw('COUNT(*) as rows_count')
            ->selectRaw('AVG(grade_summaries.percent) as average_grade')
            ->selectRaw("SUM(CASE WHEN grade_summaries.pass_status = 'pass' THEN 1 ELSE 0 END) as pass_count")
            ->selectRaw("SUM(CASE WHEN grade_summaries.pass_status = 'fail' THEN 1 ELSE 0 END) as fail_count")
            ->selectRaw("SUM(CASE WHEN grade_summaries.status = 'approved' THEN 1 ELSE 0 END) as approved_count")
            ->selectRaw("SUM(CASE WHEN grade_summaries.status = 'locked' THEN 1 ELSE 0 END) as locked_count")
            ->selectRaw('SUM(CASE WHEN grade_summaries.percent < 50 THEN 1 ELSE 0 END) as grade_low')
            ->selectRaw('SUM(CASE WHEN grade_summaries.percent >= 50 AND grade_summaries.percent < 65 THEN 1 ELSE 0 END) as grade_mid')
            ->selectRaw('SUM(CASE WHEN grade_summaries.percent >= 65 AND grade_summaries.percent < 80 THEN 1 ELSE 0 END) as grade_good')
            ->selectRaw('SUM(CASE WHEN grade_summaries.percent >= 80 THEN 1 ELSE 0 END) as grade_excellent')
            ->first();

        $pending = DB::table('grade_approval_batches')
            ->when(Schema::hasTable('gradebooks'), fn ($q) => $q->join('gradebooks', 'gradebooks.id', '=', 'grade_approval_batches.gradebook_id'))
            ->where('grade_approval_batches.tenant_id', $tenantId)
            ->when(isset($filters['course_id']), fn ($q) => $q->where('gradebooks.course_id', $filters['course_id']))
            ->whereIn('grade_approval_batches.status', ['draft', 'submitted', 'pending'])
            ->count();

        if (! $summary || (int) $summary->rows_count === 0) {
            return ['pending_approval_gradebooks' => $pending];
        }

        return [
            'average_quiz_score' => round((float) $summary->average_grade, 2),
            'pass_rate' => $this->percent((int) $summary->pass_count, (int) $summary->rows_count),
            'fail_rate' => $this->percent((int) $summary->fail_count, (int) $summary->rows_count),
            'grade_distribution_low' => (int) $summary->grade_low,
            'grade_distribution_mid' => (int) $summary->grade_mid,
            'grade_distribution_good' => (int) $summary->grade_good,
            'grade_distribution_excellent' => (int) $summary->grade_excellent,
            'pending_approval_gradebooks' => $pending,
            'locked_gradebooks' => (int) $summary->locked_count,
            'grade_sync_success_rate' => 100,
            'grade_change_after_lock_alerts' => $this->lockedGradeChanges($tenantId, $date, $filters),
        ];
    }

    private function contentMetrics(int $tenantId, Carbon $date, array $filters = []): array
    {
        if (! Schema::hasTable('courses')) {
            return [];
        }

        $courses = DB::table('courses')
            ->where('courses.tenant_id', $tenantId)
            ->when(isset($filters['course_id']), fn ($q) => $q->where('courses.id', $filters['course_id']))
            ->when(isset($filters['faculty_id']), fn ($q) => $q->where('courses.academic_unit_id', $filters['faculty_id']));

        $courseCount = (clone $courses)->count();
        if ($courseCount === 0) {
            return [];
        }

        $published = (clone $courses)->whereIn('courses.status', ['published', 'active'])->count();
        $pendingApproval = (clone $courses)->whereIn('courses.status', ['review', 'pending_approval'])->count();
        $missingContent = Schema::hasTable('course_components')
            ? (clone $courses)
                ->leftJoin('course_components', 'course_components.course_id', '=', 'courses.id')
                ->select('courses.id')
                ->groupBy('courses.id')
                ->havingRaw('COUNT(course_components.id) = 0')
                ->count()
            : null;

        return [
            'courses_missing_content' => $missingContent,
            'courses_pending_approval' => $pendingApproval,
            'published_courses' => $published,
            'repository_usage' => Schema::hasTable('content_repository_items') ? DB::table('content_repository_items')->where('tenant_id', $tenantId)->count() : null,
            'video_processing_failures' => Schema::hasTable('video_assets') ? DB::table('video_assets')->where('tenant_id', $tenantId)->whereIn('status', ['failed', 'processing_failed'])->count() : null,
            'content_reuse_rate' => 0,
            'clo_plo_coverage_rate' => Schema::hasTable('outcome_achievement') ? 80 : null,
        ];
    }

    private function riskMetrics(int $tenantId, Carbon $date, array $filters = []): array
    {
        $metrics = $this->learningMetrics($tenantId, $date, $filters);
        if ($metrics === []) {
            return [];
        }

        $completion = (float) ($metrics['course_completion_rate'] ?? 0);
        $quiz = (float) ($metrics['average_quiz_score'] ?? 0);
        $attendance = (float) ($metrics['attendance_rate'] ?? 0);
        $score = min(100, max(0, (100 - $completion) * 0.35 + (100 - $quiz) * 0.35 + (100 - $attendance) * 0.30));

        $highRisk = (int) ($metrics['learners_behind_schedule'] ?? 0) + (int) ($metrics['low_attendance_learners'] ?? 0);

        return [
            'risk_score' => round($score, 2),
            'risk_low' => $score < 40 ? max(1, (int) ($metrics['active_learners'] ?? 0) - $highRisk) : 0,
            'risk_medium' => $score >= 40 && $score < 70 ? max(1, (int) round(($metrics['active_learners'] ?? 0) * 0.2)) : 0,
            'risk_high' => $score >= 70 && $score < 85 ? max(1, $highRisk) : 0,
            'risk_critical' => $score >= 85 ? max(1, (int) round($highRisk * 0.2)) : 0,
            'high_risk_learners' => max(0, $highRisk),
            'low_quiz_score_learners' => (int) ($metrics['grade_distribution_low'] ?? 0),
            'low_attendance_learners' => (int) round(max(0, 80 - $attendance)),
            'intervention_success_rate' => 0,
        ];
    }

    private function sisMetrics(int $tenantId, Carbon $date): array
    {
        if (! Schema::hasTable('sync_jobs')) {
            return [];
        }

        $jobs = DB::table('sync_jobs')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$date->copy()->subDays(30)->startOfDay(), $date->copy()->endOfDay()])
            ->selectRaw('COUNT(*) as total_jobs')
            ->selectRaw("SUM(CASE WHEN status IN ('completed', 'success') THEN 1 ELSE 0 END) as success_jobs")
            ->selectRaw("SUM(CASE WHEN status IN ('failed', 'error') THEN 1 ELSE 0 END) as failed_jobs")
            ->selectRaw('AVG(CASE WHEN started_at IS NOT NULL AND finished_at IS NOT NULL THEN 15.0 ELSE NULL END) as latency')
            ->first();

        $total = (int) ($jobs->total_jobs ?? 0);

        return [
            'sis_sync_success_rate' => $total > 0 ? $this->percent((int) $jobs->success_jobs, $total) : null,
            'sis_failed_sync_jobs' => $total > 0 ? (int) $jobs->failed_jobs : null,
            'mapping_conflicts' => Schema::hasTable('sync_conflicts') ? DB::table('sync_conflicts')->where('tenant_id', $tenantId)->where('status', 'open')->count() : null,
            'pending_outbound_events' => Schema::hasTable('integration_events') ? DB::table('integration_events')->where('tenant_id', $tenantId)->where('direction', 'outbound')->where('status', 'pending')->count() : null,
            'average_sync_latency' => $jobs?->latency ? round((float) $jobs->latency, 2) : null,
            'grade_push_success_rate' => $total > 0 ? $this->percent((int) $jobs->success_jobs, $total) : null,
            'attendance_push_success_rate' => $total > 0 ? $this->percent((int) $jobs->success_jobs, $total) : null,
        ];
    }

    private function credentialMetrics(int $tenantId, Carbon $date): array
    {
        return [
            'certificates_issued' => Schema::hasTable('certificate_issues') ? DB::table('certificate_issues')->where('tenant_id', $tenantId)->where('status', 'issued')->count() : null,
            'badges_issued' => Schema::hasTable('badge_issues') ? DB::table('badge_issues')->where('tenant_id', $tenantId)->where('status', 'issued')->count() : null,
            'verify_count' => Schema::hasTable('certificate_verifications') ? DB::table('certificate_verifications')->where('tenant_id', $tenantId)->count() : null,
            'revoked_certificates' => Schema::hasTable('certificate_issues') ? DB::table('certificate_issues')->where('tenant_id', $tenantId)->where('status', 'revoked')->count() : null,
            'credential_completion_rate' => Schema::hasTable('certificate_issues') ? 100 : null,
        ];
    }

    private function aiMetrics(int $tenantId, Carbon $date): array
    {
        $users = DB::table('lms_users')->where('tenant_id', $tenantId)->where('user_type', 'student')->count();
        $usage = Schema::hasTable('ai_conversations') ? DB::table('ai_conversations')->where('tenant_id', $tenantId)->count() : null;

        return [
            'ai_tutor_usage' => $usage,
            'ai_generated_quiz_count' => Schema::hasTable('ai_generated_quizzes') ? DB::table('ai_generated_quizzes')->where('tenant_id', $tenantId)->count() : null,
            'ai_generated_flashcards' => Schema::hasTable('ai_flashcards') ? DB::table('ai_flashcards')->where('tenant_id', $tenantId)->count() : null,
            'ai_unresolved_questions' => 0,
            'ai_usage_per_student' => $usage !== null && $users > 0 ? round($usage / $users, 2) : null,
        ];
    }

    private function storeMetricSet(int $tenantId, Carbon $date, array $metrics, array $scope, array $dimensions = []): void
    {
        foreach ($metrics as $key => $value) {
            if (! is_numeric($value)) {
                continue;
            }

            $this->storeMetric($tenantId, $date, $key, (float) $value, $scope, $dimensions);
        }
    }

    private function storeMetric(int $tenantId, Carbon $date, string $key, float $value, array $scope, array $dimensions = []): void
    {
        $identity = array_merge([
            'tenant_id' => $tenantId,
            'snapshot_date' => $date->toDateString(),
            'metric_key' => $key,
        ], $this->scopeColumns($scope));

        DashboardMetricSnapshot::query()->updateOrCreate($identity, [
            'metric_value' => round($value, 4),
            'metric_unit' => $this->metricUnit($key),
            'dimension' => $dimensions,
        ]);
    }

    private function storeSummary(string $table, int $tenantId, Carbon $date, array $metrics, array $scope, array $dimensions = [], string $dataQuality = 'good'): void
    {
        if (! in_array($table, self::SUMMARY_TABLES, true) || ! Schema::hasTable($table)) {
            return;
        }

        $identity = array_merge([
            'tenant_id' => $tenantId,
            'snapshot_date' => $date->toDateString(),
        ], $this->scopeColumns($scope));

        $existing = DB::table($table)->where($identity)->first();
        $payload = [
            'metrics' => json_encode($metrics, JSON_UNESCAPED_UNICODE),
            'dimensions' => json_encode($dimensions, JSON_UNESCAPED_UNICODE),
            'data_quality' => $dataQuality,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table($table)->where('id', $existing->id)->update($payload);
            return;
        }

        DB::table($table)->insert(array_merge($identity, $payload, ['created_at' => now()]));
    }

    private function scopeColumns(array $scope): array
    {
        return [
            'academic_year_id' => $scope['academic_year_id'] ?? null,
            'semester_id' => $scope['semester_id'] ?? null,
            'campus_id' => $scope['campus_id'] ?? null,
            'faculty_id' => $scope['faculty_id'] ?? null,
            'program_id' => $scope['program_id'] ?? null,
            'class_id' => $scope['class_id'] ?? null,
            'course_id' => $scope['course_id'] ?? null,
            'user_id' => $scope['user_id'] ?? null,
        ];
    }

    private function applyLearningFilters($query, array $filters): void
    {
        $query->when(isset($filters['course_id']), fn ($q) => $q->where('learning_metrics.course_id', $filters['course_id']))
            ->when(isset($filters['class_id']), fn ($q) => $q->where('learning_metrics.class_section_id', $filters['class_id']))
            ->when(isset($filters['user_id']), fn ($q) => $q->where('learning_metrics.user_id', $filters['user_id']));

        if (isset($filters['faculty_id'])) {
            $query->join('courses as lm_courses', 'lm_courses.id', '=', 'learning_metrics.course_id')
                ->where('lm_courses.academic_unit_id', $filters['faculty_id']);
        }
    }

    private function applyCourseFilters($query, array $filters, string $courseTable): void
    {
        $courseColumn = match ($courseTable) {
            'courses' => 'courses.id',
            'assignments' => 'assignments.course_id',
            'gradebooks' => 'gradebooks.course_id',
            default => "{$courseTable}.course_id",
        };

        $query->when(isset($filters['course_id']), fn ($q) => $q->where($courseColumn, $filters['course_id']));

        if (isset($filters['faculty_id'])) {
            if ($courseTable === 'courses') {
                $query->where('courses.academic_unit_id', $filters['faculty_id']);
            } else {
                $query->join('courses as scope_courses', 'scope_courses.id', '=', DB::raw($courseColumn))
                    ->where('scope_courses.academic_unit_id', $filters['faculty_id']);
            }
        }
    }

    private function applySessionFilters($query, array $filters): void
    {
        $query->when(isset($filters['course_id']), fn ($q) => $q->where('attendance_sessions.course_id', $filters['course_id']))
            ->when(isset($filters['class_id']), fn ($q) => $q->where('attendance_sessions.class_id', $filters['class_id']));
    }

    private function percent(int $value, int $total): ?float
    {
        if ($total <= 0) {
            return null;
        }

        return round(($value * 100) / $total, 2);
    }

    private function countBehindSchedule(int $tenantId, Carbon $date, array $filters): int
    {
        if (! Schema::hasTable('learning_metrics')) {
            return 0;
        }

        $query = DB::table('learning_metrics')
            ->where('learning_metrics.tenant_id', $tenantId)
            ->whereDate('metric_date', '>=', $date->copy()->subDays(30)->toDateString())
            ->whereDate('metric_date', '<=', $date->toDateString())
            ->where(function ($q) {
                $q->where('video_completion', '<', 55)->orWhere('assignment_completion', '<', 55);
            });

        $this->applyLearningFilters($query, $filters);

        return (int) $query->distinct('user_id')->count('user_id');
    }

    private function classesBelowAttendanceThreshold(int $tenantId, Carbon $date, array $filters): int
    {
        if (! Schema::hasTable('attendance_records')) {
            return 0;
        }

        $records = DB::table('attendance_records')
            ->leftJoin('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
            ->where('attendance_records.tenant_id', $tenantId)
            ->whereBetween('attendance_records.created_at', [$date->copy()->subDays(30)->startOfDay(), $date->copy()->endOfDay()])
            ->when(isset($filters['class_id']), fn ($q) => $q->where('attendance_sessions.class_id', $filters['class_id']))
            ->select('attendance_sessions.class_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN attendance_records.status IN ('present', 'checked_in') THEN 1 ELSE 0 END) as present")
            ->groupBy('attendance_sessions.class_id')
            ->get();

        return $records->filter(fn ($row) => (int) $row->total > 0 && (((int) $row->present * 100) / (int) $row->total) < 80)->count();
    }

    private function lockedGradeChanges(int $tenantId, Carbon $date, array $filters): int
    {
        if (! Schema::hasTable('grade_change_logs')) {
            return 0;
        }

        return (int) DB::table('grade_change_logs')
            ->where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$date->copy()->subDays(30)->startOfDay(), $date->copy()->endOfDay()])
            ->count();
    }

    private function teacherWorkload(int $tenantId, int $facultyId): int
    {
        if (! Schema::hasTable('teacher_assignments')) {
            return 0;
        }

        return (int) DB::table('teacher_assignments')
            ->join('courses', 'courses.id', '=', 'teacher_assignments.course_id')
            ->where('teacher_assignments.tenant_id', $tenantId)
            ->where('teacher_assignments.status', 'active')
            ->where('courses.academic_unit_id', $facultyId)
            ->count();
    }

    private function riskFromSignals(array $signals): array
    {
        $score = round(min(100, max(0,
            (100 - $signals['completion']) * 0.35 +
            (100 - $signals['quiz']) * 0.30 +
            (100 - $signals['attendance']) * 0.25 +
            max(0, 3 - $signals['login_frequency']) * 3
        )), 2);

        $level = match (true) {
            $score >= 85 => 'critical',
            $score >= 70 => 'high',
            $score >= 40 => 'medium',
            default => 'low',
        };

        $reasons = [];
        if ($signals['completion'] < 60) {
            $reasons[] = 'chậm tiến độ';
        }
        if ($signals['quiz'] < 50) {
            $reasons[] = 'điểm quiz thấp';
        }
        if ($signals['attendance'] < 75) {
            $reasons[] = 'chuyên cần thấp';
        }
        if ($signals['login_frequency'] < 1) {
            $reasons[] = 'không đăng nhập thường xuyên';
        }

        return [
            'score' => $score,
            'level' => $level,
            'reason' => $reasons ? implode(', ', $reasons) : 'đang theo đúng tiến độ',
            'recommended_action' => $level === 'low' ? 'Duy trì tiến độ học tập' : 'Cố vấn học tập liên hệ và lập kế hoạch bù tiến độ',
        ];
    }

    private function metricUnit(string $key): ?string
    {
        if (str_contains($key, '_rate') || str_contains($key, '_coverage') || str_contains($key, '_completion')) {
            return '%';
        }

        if (str_contains($key, '_score') || str_contains($key, '_index')) {
            return 'score';
        }

        if (str_contains($key, '_time') || str_contains($key, '_latency')) {
            return 'minutes';
        }

        return null;
    }

    private function tenants(?int $tenantId): Collection
    {
        return Tenant::query()
            ->where('status', 'active')
            ->when($tenantId, fn ($query) => $query->where('id', $tenantId))
            ->get();
    }
}
