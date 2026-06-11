<?php

namespace Database\Seeders;

use App\Models\AcademicUnit;
use App\Models\AnalyticsBenchmark;
use App\Models\Course;
use App\Models\DashboardMetricSnapshot;
use App\Models\LmsUser;
use App\Models\Tenant;
use App\Services\AlertEngineService;
use App\Services\ForecastService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DashboardAnalyticsSeeder extends Seeder
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

    public function run(): void
    {
        $tenant = Tenant::query()->where('code', 'VABIS')->first();
        if (! $tenant) {
            return;
        }

        $tenantId = (int) $tenant->id;
        $courses = Course::query()->where('tenant_id', $tenantId)->limit(200)->get();
        $students = LmsUser::query()->where('tenant_id', $tenantId)->where('user_type', 'student')->limit(5000)->get();
        $teachers = LmsUser::query()->where('tenant_id', $tenantId)->where('user_type', 'teacher')->limit(100)->get();
        $faculties = AcademicUnit::query()->where('tenant_id', $tenantId)->limit(20)->get();

        if ($courses->isEmpty() || $students->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($tenantId, $courses, $students, $teachers, $faculties): void {
            $this->clearAnalytics($tenantId);
            $classes = $this->ensureClasses($tenantId, $courses, $teachers);
            $dates = collect(range(11, 0))->map(fn ($month) => now()->subMonths($month)->endOfMonth()->toDateString());

            $metricRows = [];
            foreach ($dates as $dateIndex => $date) {
                $season = $dateIndex + 1;
                $tenantMetrics = $this->tenantMetrics($students->count(), $courses->count(), $classes->count(), $season);
                $this->appendMetricRows($metricRows, $tenantId, $date, $tenantMetrics, [], ['label' => 'Toàn trường']);
                $this->insertSummary('course_operation_snapshots', $tenantId, $date, $tenantMetrics, [], ['label' => 'Toàn trường']);
                $this->insertSummary('risk_analytics_snapshots', $tenantId, $date, $tenantMetrics, [], ['label' => 'Toàn trường']);
                $this->insertSummary('sis_sync_analytics_snapshots', $tenantId, $date, $tenantMetrics, [], ['label' => 'Toàn trường']);
                $this->insertSummary('ai_usage_snapshots', $tenantId, $date, $tenantMetrics, [], ['label' => 'Toàn trường']);

                foreach ($faculties as $index => $faculty) {
                    $metrics = $this->facultyMetrics($tenantMetrics, $index, $season);
                    $scope = ['faculty_id' => (int) $faculty->id];
                    $dimensions = ['faculty_name' => $faculty->name];
                    $this->appendMetricRows($metricRows, $tenantId, $date, $metrics, $scope, $dimensions);
                    $this->insertSummary('faculty_analytics_snapshots', $tenantId, $date, $metrics, $scope, $dimensions);
                }

                foreach ($classes->take(100) as $index => $class) {
                    $metrics = $this->classMetrics($tenantMetrics, $index, $season);
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
                    $this->appendMetricRows($metricRows, $tenantId, $date, $metrics, $scope, $dimensions);
                    $this->insertSummary('class_analytics_snapshots', $tenantId, $date, $metrics, $scope, $dimensions, ($metrics['course_completion_rate'] ?? 100) < 55 ? 'warning' : 'good');
                    $this->insertSummary('attendance_analytics_snapshots', $tenantId, $date, $metrics, $scope, $dimensions);
                }

                foreach ($courses as $index => $course) {
                    $metrics = $this->courseMetrics($tenantMetrics, $index, $season);
                    $scope = [
                        'course_id' => (int) $course->id,
                        'faculty_id' => $course->academic_unit_id ? (int) $course->academic_unit_id : null,
                    ];
                    $dimensions = ['course_title' => $course->title];
                    $this->appendMetricRows($metricRows, $tenantId, $date, $metrics, $scope, $dimensions);
                    $this->insertSummary('course_operation_snapshots', $tenantId, $date, $metrics, $scope, $dimensions);
                    $this->insertSummary('exam_analytics_snapshots', $tenantId, $date, $metrics, $scope, $dimensions);
                    $this->insertSummary('grade_analytics_snapshots', $tenantId, $date, $metrics, $scope, $dimensions);
                }

                if (count($metricRows) >= 5000) {
                    DashboardMetricSnapshot::query()->insert($metricRows);
                    $metricRows = [];
                }
            }

            if ($metricRows) {
                DashboardMetricSnapshot::query()->insert($metricRows);
            }

            $this->seedLearnerSnapshots($tenantId, $students, $courses, $classes);
            $this->seedWidgetConfigs($tenantId);
            $this->seedBenchmarks($tenantId);

            app(ForecastService::class)->calculateCourseCompletionForecast($tenantId);
            app(ForecastService::class)->calculateRiskForecast($tenantId);
            app(ForecastService::class)->calculateExamLoadForecast($tenantId);
            app(ForecastService::class)->calculateGradingBacklogForecast($tenantId);
            app(AlertEngineService::class)->detectHighRiskLearners($tenantId);
            app(AlertEngineService::class)->detectClassBehindSchedule($tenantId);
            app(AlertEngineService::class)->detectLowAttendance($tenantId);
        });
    }

    private function ensureClasses(int $tenantId, $courses, $teachers)
    {
        if (DB::table('class_sections')->where('tenant_id', $tenantId)->count() < 100) {
            $now = now();
            for ($i = 1; $i <= 100; $i++) {
                $course = $courses[($i - 1) % $courses->count()];
                DB::table('class_sections')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'code' => 'DASH'.str_pad((string) $i, 3, '0', STR_PAD_LEFT)],
                    [
                        'course_id' => $course->id,
                        'name' => 'Lớp vận hành '.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                        'section_type' => 'class_section',
                        'delivery_mode' => $i % 3 === 0 ? 'online' : 'blended',
                        'status' => 'active',
                        'capacity' => 60,
                        'metadata' => json_encode(['analytics_seed' => true]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }

        $classes = DB::table('class_sections')
            ->join('courses', 'courses.id', '=', 'class_sections.course_id')
            ->leftJoin('academic_units', 'academic_units.id', '=', 'courses.academic_unit_id')
            ->where('class_sections.tenant_id', $tenantId)
            ->select('class_sections.id', 'class_sections.name', 'class_sections.course_id', 'courses.title as course_title', 'courses.academic_unit_id as faculty_id', 'academic_units.name as faculty_name')
            ->limit(100)
            ->get();

        foreach ($classes as $index => $class) {
            $teacher = $teachers->isNotEmpty() ? $teachers[$index % $teachers->count()] : null;
            if ($teacher) {
                DB::table('teacher_assignments')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'class_section_id' => $class->id, 'user_id' => $teacher->id, 'role' => 'primary'],
                    ['course_id' => $class->course_id, 'status' => 'active', 'assigned_at' => now(), 'metadata' => json_encode(['analytics_seed' => true]), 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        return $classes;
    }

    private function seedLearnerSnapshots(int $tenantId, $students, $courses, $classes): void
    {
        $date = now()->endOfMonth()->toDateString();
        $metricRows = [];
        $summaryRows = [];
        $now = now();

        foreach ($students as $index => $student) {
            $course = $courses[$index % $courses->count()];
            $class = $classes[$index % max($classes->count(), 1)] ?? null;
            $persona = $index % 10;
            $completion = match (true) {
                $persona <= 1 => 34 + ($index % 12),
                $persona <= 3 => 54 + ($index % 14),
                $persona <= 7 => 72 + ($index % 10),
                default => 88 + ($index % 8),
            };
            $quiz = max(20, min(100, $completion - 8 + ($index % 18)));
            $attendance = max(30, min(100, $completion + 3 + ($index % 12)));
            $riskScore = round((100 - $completion) * 0.4 + (100 - $quiz) * 0.35 + (100 - $attendance) * 0.25, 2);
            $riskLevel = match (true) {
                $riskScore >= 85 => 'critical',
                $riskScore >= 70 => 'high',
                $riskScore >= 40 => 'medium',
                default => 'low',
            };
            $metrics = [
                'course_completion_rate' => $completion,
                'lesson_completion_rate' => $completion,
                'video_watch_rate' => min(100, $completion + 5),
                'assignment_completion_rate' => max(0, $completion - 4),
                'average_study_time' => 90 + ($completion * 2),
                'average_quiz_score' => $quiz,
                'attendance_rate' => $attendance,
                'risk_score' => $riskScore,
                'risk_level' => $riskLevel,
                'risk_reason' => $riskLevel === 'low' ? 'đang theo đúng tiến độ' : 'tiến độ, điểm quiz hoặc chuyên cần thấp',
                'recommended_action' => $riskLevel === 'low' ? 'Duy trì tiến độ học tập' : 'Cố vấn học tập liên hệ và lập kế hoạch bù tiến độ',
            ];
            $scope = [
                'user_id' => (int) $student->id,
                'course_id' => (int) $course->id,
                'class_id' => $class?->id ? (int) $class->id : null,
                'faculty_id' => $course->academic_unit_id ? (int) $course->academic_unit_id : null,
            ];
            $dimensions = [
                'student_name' => $student->full_name,
                'student_code' => $student->code,
                'course_title' => $course->title,
                'class_name' => $class->name ?? null,
            ];

            foreach (array_filter($metrics, 'is_numeric') as $key => $value) {
                $metricRows[] = array_merge($this->metricIdentity($tenantId, $date, $key, $scope), [
                    'metric_value' => $value,
                    'metric_unit' => $this->metricUnit($key),
                    'dimension' => json_encode($dimensions, JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $summaryRows[] = array_merge($this->summaryIdentity($tenantId, $date, $scope), [
                'metrics' => json_encode($metrics, JSON_UNESCAPED_UNICODE),
                'dimensions' => json_encode($dimensions, JSON_UNESCAPED_UNICODE),
                'data_quality' => in_array($riskLevel, ['high', 'critical'], true) ? 'warning' : 'good',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (count($metricRows) >= 5000) {
                DashboardMetricSnapshot::query()->insert($metricRows);
                $metricRows = [];
            }

            if (count($summaryRows) >= 1000) {
                DB::table('learner_analytics_snapshots')->insert($summaryRows);
                $summaryRows = [];
            }
        }

        if ($metricRows) {
            DashboardMetricSnapshot::query()->insert($metricRows);
        }

        if ($summaryRows) {
            DB::table('learner_analytics_snapshots')->insert($summaryRows);
        }
    }

    private function tenantMetrics(int $learners, int $courses, int $classes, int $season): array
    {
        $completion = min(92, 58 + ($season * 2.2));
        $attendance = min(94, 76 + ($season * 1.1));
        $pass = min(90, 68 + ($season * 1.5));
        $riskHigh = max(50, (int) round($learners * (0.18 - min(0.08, $season / 200))));

        return [
            'active_learners' => (int) round($learners * 0.82),
            'course_completion_rate' => round($completion, 2),
            'lesson_completion_rate' => round($completion + 3, 2),
            'video_watch_rate' => round($completion + 7, 2),
            'average_study_time' => 176 + ($season * 6),
            'learning_path_completion_rate' => round($completion - 4, 2),
            'on_time_progress_rate' => round($completion + 8, 2),
            'drop_off_point_count' => (int) round($learners * 0.04),
            'quiz_participation_rate' => 87,
            'average_quiz_score' => round($pass - 2, 2),
            'pass_rate' => round($pass, 2),
            'fail_rate' => round(100 - $pass, 2),
            'retake_rate' => 12,
            'question_difficulty_index' => 64,
            'question_discrimination_index' => 0,
            'exam_suspicious_rate' => 2.4,
            'manual_grading_backlog' => 86,
            'submission_rate' => 91,
            'on_time_submission_rate' => 84,
            'late_submission_rate' => 7,
            'grading_turnaround_time' => 28,
            'resubmission_rate' => 9,
            'rubric_achievement_rate' => 78,
            'attendance_rate' => round($attendance, 2),
            'absence_rate' => round(100 - $attendance, 2),
            'late_rate' => 4.8,
            'eligibility_rate' => round($attendance - 5, 2),
            'classes_below_attendance_threshold' => max(1, (int) round($classes * 0.08)),
            'grade_distribution_low' => (int) round($learners * 0.08),
            'grade_distribution_mid' => (int) round($learners * 0.24),
            'grade_distribution_good' => (int) round($learners * 0.42),
            'grade_distribution_excellent' => (int) round($learners * 0.26),
            'pending_approval_gradebooks' => 12,
            'locked_gradebooks' => 38,
            'grade_sync_success_rate' => 96,
            'grade_change_after_lock_alerts' => 3,
            'courses_missing_content' => max(1, (int) round($courses * 0.07)),
            'courses_pending_approval' => max(1, (int) round($courses * 0.12)),
            'published_courses' => max(1, (int) round($courses * 0.72)),
            'repository_usage' => 1480 + ($season * 54),
            'video_processing_failures' => 4,
            'content_reuse_rate' => 43,
            'clo_plo_coverage_rate' => 81,
            'risk_low' => (int) round($learners * 0.56),
            'risk_medium' => (int) round($learners * 0.28),
            'risk_high' => (int) round($learners * 0.12),
            'risk_critical' => (int) round($learners * 0.04),
            'risk_score' => 38,
            'high_risk_learners' => $riskHigh,
            'no_login_7_days' => (int) round($learners * 0.06),
            'learners_behind_schedule' => (int) round($learners * 0.11),
            'class_behind_schedule' => max(1, (int) round($classes * 0.09)),
            'low_quiz_score_learners' => (int) round($learners * 0.08),
            'low_attendance_learners' => (int) round($learners * 0.07),
            'intervention_success_rate' => 52,
            'sis_sync_success_rate' => 97,
            'sis_failed_sync_jobs' => 5,
            'mapping_conflicts' => 9,
            'pending_outbound_events' => 24,
            'average_sync_latency' => 11,
            'grade_push_success_rate' => 96,
            'attendance_push_success_rate' => 95,
            'certificates_issued' => 840 + ($season * 12),
            'badges_issued' => 1260 + ($season * 18),
            'verify_count' => 380 + ($season * 9),
            'revoked_certificates' => 3,
            'credential_completion_rate' => 72,
            'ai_tutor_usage' => 6200 + ($season * 220),
            'ai_generated_quiz_count' => 420 + ($season * 12),
            'ai_generated_flashcards' => 1800 + ($season * 45),
            'ai_unresolved_questions' => 36,
            'ai_usage_per_student' => 1.4,
        ];
    }

    private function facultyMetrics(array $base, int $index, int $season): array
    {
        $modifier = (($index % 7) - 3) * 1.7;
        $metrics = $base;
        foreach (['course_completion_rate', 'attendance_rate', 'pass_rate', 'sis_sync_success_rate'] as $key) {
            $metrics[$key] = round(max(0, min(100, $base[$key] + $modifier)), 2);
        }
        $metrics['active_learners'] = max(50, (int) round($base['active_learners'] / 12 + ($index * 11)));
        $metrics['high_risk_learners'] = max(5, (int) round($metrics['active_learners'] * (0.08 + (($index % 4) * 0.015))));
        $metrics['manual_grading_backlog'] = 12 + ($index % 9) + $season;
        $metrics['teacher_workload'] = 5 + ($index % 8);

        return $metrics;
    }

    private function classMetrics(array $base, int $index, int $season): array
    {
        $completion = max(28, min(96, $base['course_completion_rate'] + (($index % 11) - 5) * 2.4));
        $attendance = max(55, min(98, $base['attendance_rate'] + (($index % 9) - 4) * 2.2));
        $learners = 28 + ($index % 34);

        return array_merge($base, [
            'active_learners' => $learners,
            'course_completion_rate' => round($completion, 2),
            'lesson_completion_rate' => round(min(100, $completion + 4), 2),
            'attendance_rate' => round($attendance, 2),
            'absence_rate' => round(100 - $attendance, 2),
            'pass_rate' => round(max(30, min(100, $base['pass_rate'] + (($index % 8) - 4) * 3)), 2),
            'high_risk_learners' => max(0, (int) round($learners * ($completion < 55 ? 0.22 : 0.08))),
            'class_behind_schedule' => $completion < 55 ? 1 : 0,
            'manual_grading_backlog' => $index % 12,
        ]);
    }

    private function courseMetrics(array $base, int $index, int $season): array
    {
        $metrics = $base;
        $metrics['course_completion_rate'] = round(max(35, min(98, $base['course_completion_rate'] + (($index % 13) - 6) * 1.8)), 2);
        $metrics['published_courses'] = 1;
        $metrics['courses_missing_content'] = $index % 12 === 0 ? 1 : 0;
        $metrics['courses_pending_approval'] = $index % 8 === 0 ? 1 : 0;
        $metrics['manual_grading_backlog'] = $index % 9;
        $metrics['exam_suspicious_rate'] = round(($index % 5) * 0.8, 2);

        return $metrics;
    }

    private function appendMetricRows(array &$rows, int $tenantId, string $date, array $metrics, array $scope, array $dimensions): void
    {
        $now = now();
        foreach ($metrics as $key => $value) {
            if (! is_numeric($value)) {
                continue;
            }

            $rows[] = array_merge($this->metricIdentity($tenantId, $date, $key, $scope), [
                'metric_value' => $value,
                'metric_unit' => $this->metricUnit($key),
                'dimension' => json_encode($dimensions, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function insertSummary(string $table, int $tenantId, string $date, array $metrics, array $scope, array $dimensions, string $quality = 'good'): void
    {
        DB::table($table)->insert(array_merge($this->summaryIdentity($tenantId, $date, $scope), [
            'metrics' => json_encode($metrics, JSON_UNESCAPED_UNICODE),
            'dimensions' => json_encode($dimensions, JSON_UNESCAPED_UNICODE),
            'data_quality' => $quality,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    private function metricIdentity(int $tenantId, string $date, string $key, array $scope): array
    {
        return array_merge($this->summaryIdentity($tenantId, $date, $scope), ['metric_key' => $key]);
    }

    private function summaryIdentity(int $tenantId, string $date, array $scope): array
    {
        return [
            'tenant_id' => $tenantId,
            'snapshot_date' => Carbon::parse($date)->toDateString(),
            'academic_year_id' => null,
            'semester_id' => null,
            'campus_id' => null,
            'faculty_id' => $scope['faculty_id'] ?? null,
            'program_id' => null,
            'class_id' => $scope['class_id'] ?? null,
            'course_id' => $scope['course_id'] ?? null,
            'user_id' => $scope['user_id'] ?? null,
        ];
    }

    private function seedWidgetConfigs(int $tenantId): void
    {
        $dashboards = ['executive', 'academic', 'faculty', 'teacher', 'student', 'exam', 'attendance', 'gradebook', 'integration', 'content', 'certificate', 'ai', 'risk'];
        $types = ['kpi', 'line', 'bar', 'table', 'alert', 'forecast'];
        foreach ($dashboards as $dashboard) {
            foreach ($types as $index => $type) {
                DB::table('dashboard_widget_configs')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'dashboard_key' => $dashboard, 'widget_key' => "{$dashboard}_{$type}"],
                    [
                        'title' => Str::headline("{$dashboard} {$type}"),
                        'widget_type' => $type,
                        'data_source' => 'dashboard_metric_snapshots',
                        'config' => json_encode(['seeded' => true], JSON_UNESCAPED_UNICODE),
                        'permission_key' => 'analytics.dashboard',
                        'sort_order' => ($index + 1) * 10,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    private function seedBenchmarks(int $tenantId): void
    {
        foreach ([
            ['course_completion_rate', 74, 80, 76],
            ['attendance_rate', 84, 85, 82],
            ['pass_rate', 78, 80, 75],
            ['sis_sync_success_rate', 97, 98, 95],
        ] as [$key, $current, $target, $industry]) {
            AnalyticsBenchmark::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'benchmark_key' => $key, 'scope_type' => 'tenant', 'scope_id' => null],
                [
                    'current_value' => $current,
                    'internal_target' => $target,
                    'industry_value' => $industry,
                    'status' => $current >= $target ? 'good' : 'normal',
                    'note' => 'Benchmark seed theo mục tiêu nội bộ và placeholder ngành giáo dục.',
                ]
            );
        }
    }

    private function clearAnalytics(int $tenantId): void
    {
        DashboardMetricSnapshot::query()->where('tenant_id', $tenantId)->delete();
        DB::table('dashboard_widget_configs')->where('tenant_id', $tenantId)->delete();
        DB::table('dashboard_user_preferences')->where('tenant_id', $tenantId)->delete();
        DB::table('analytics_forecasts')->where('tenant_id', $tenantId)->delete();
        DB::table('analytics_alerts')->where('tenant_id', $tenantId)->delete();
        DB::table('analytics_benchmarks')->where('tenant_id', $tenantId)->delete();

        foreach (self::SUMMARY_TABLES as $table) {
            DB::table($table)->where('tenant_id', $tenantId)->delete();
        }
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
}
