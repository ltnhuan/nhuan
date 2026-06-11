<?php

namespace Tests\Feature;

use App\Models\AnalyticsAlert;
use App\Models\AnalyticsForecast;
use App\Models\Course;
use App\Models\DashboardMetricSnapshot;
use App\Models\LearningMetric;
use App\Models\LmsUser;
use App\Services\AlertEngineService;
use App\Services\ForecastService;
use Database\Seeders\CoreSeeder;
use Database\Seeders\CourseStudioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnterpriseDashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->seed(CourseStudioSeeder::class);
    }

    public function test_executive_dashboard_loads_from_summary_without_querying_raw_event_tables(): void
    {
        $this->metric('active_learners', 1200);
        $this->metric('course_completion_rate', 72);
        $this->metric('high_risk_learners', 45);
        $this->metric('attendance_rate', 86);
        $this->metric('pass_rate', 80);
        $this->metric('sis_sync_success_rate', 98);

        DB::enableQueryLog();
        $response = $this->dashboard('executive');
        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");

        $response->assertOk()->assertJsonPath('data.dashboard_key', 'executive');
        $this->assertSame(1200, (int) $response->json('data.kpis.0.value'));
        foreach (['learning_progress_events', 'video_watch_events', 'exam_attempt_events', 'assignment_events', 'attendance_events', 'integration_events', 'audit_logs'] as $rawTable) {
            $this->assertStringNotContainsString($rawTable, $queries);
        }
    }

    public function test_faculty_filter_changes_summary_metrics(): void
    {
        $facultyA = DB::table('academic_units')->where('tenant_id', 1)->first();
        $facultyB = DB::table('academic_units')->where('tenant_id', 1)->skip(1)->first();
        $this->metric('active_learners', 100, ['faculty_id' => $facultyA->id], ['faculty_name' => $facultyA->name]);
        $this->metric('active_learners', 250, ['faculty_id' => $facultyB->id], ['faculty_name' => $facultyB->name]);

        $a = $this->dashboard('executive', ['faculty_id' => $facultyA->id])->json('data.kpis.0.value');
        $b = $this->dashboard('executive', ['faculty_id' => $facultyB->id])->json('data.kpis.0.value');

        $this->assertNotSame($a, $b);
        $this->assertSame(100, (int) $a);
        $this->assertSame(250, (int) $b);
    }

    public function test_high_risk_drilldown_returns_student_summary_rows(): void
    {
        [$student, $course] = $this->studentCourse();
        DB::table('learner_analytics_snapshots')->insert([
            'tenant_id' => 1,
            'snapshot_date' => now()->toDateString(),
            'course_id' => $course->id,
            'user_id' => $student->id,
            'metrics' => json_encode(['risk_score' => 82, 'risk_level' => 'high', 'risk_reason' => 'điểm quiz thấp', 'recommended_action' => 'liên hệ cố vấn']),
            'dimensions' => json_encode(['student_name' => $student->full_name, 'course_title' => $course->title]),
            'data_quality' => 'warning',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->metric('high_risk_learners', 1);

        $response = $this->withTenant()->getJson('/api/v1/analytics/drilldown?dashboard_key=executive&metric_key=high_risk_learners');

        $response->assertOk();
        $response->assertJsonPath('data.rows.0.student_id', $student->id);
        $response->assertJsonPath('data.rows.0.risk_level', 'high');
    }

    public function test_rebuild_snapshot_creates_summary_metric_and_forecast(): void
    {
        [$student, $course] = $this->studentCourse();
        LearningMetric::query()->create([
            'tenant_id' => 1,
            'user_id' => $student->id,
            'course_id' => $course->id,
            'metric_date' => now()->toDateString(),
            'login_frequency' => 2,
            'study_time_minutes' => 120,
            'video_completion' => 70,
            'assignment_completion' => 65,
            'quiz_score' => 72,
            'attendance' => 88,
            'forum_activity' => 1,
        ]);

        $response = $this->withTenant()->postJson('/api/v1/analytics/rebuild-snapshots', ['scope' => 'tenant']);

        $response->assertOk();
        $this->assertDatabaseHas('dashboard_metric_snapshots', ['tenant_id' => 1, 'metric_key' => 'active_learners']);
        $this->assertDatabaseHas('analytics_forecasts', ['tenant_id' => 1, 'forecast_key' => 'risk_forecast']);
    }

    public function test_alert_engine_creates_high_risk_alert(): void
    {
        [$student, $course] = $this->studentCourse();
        DB::table('learner_analytics_snapshots')->insert([
            'tenant_id' => 1,
            'snapshot_date' => now()->toDateString(),
            'course_id' => $course->id,
            'user_id' => $student->id,
            'metrics' => json_encode(['risk_score' => 88, 'risk_level' => 'critical', 'risk_reason' => 'chậm tiến độ', 'recommended_action' => 'can thiệp ngay']),
            'dimensions' => json_encode(['student_name' => $student->full_name]),
            'data_quality' => 'warning',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(AlertEngineService::class)->detectHighRiskLearners(1);

        $this->assertDatabaseHas('analytics_alerts', [
            'tenant_id' => 1,
            'alert_type' => 'high_risk_learner',
            'severity' => 'critical',
            'scope_type' => 'student',
            'scope_id' => $student->id,
        ]);
    }

    public function test_forecast_has_predicted_value_and_explanation(): void
    {
        [$student, $course] = $this->studentCourse();
        DB::table('learner_analytics_snapshots')->insert([
            'tenant_id' => 1,
            'snapshot_date' => now()->toDateString(),
            'course_id' => $course->id,
            'user_id' => $student->id,
            'metrics' => json_encode(['risk_score' => 75, 'course_completion_rate' => 42, 'average_quiz_score' => 50, 'attendance_rate' => 72]),
            'dimensions' => json_encode(['student_name' => $student->full_name]),
            'data_quality' => 'warning',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(ForecastService::class)->calculateRiskForecast(1);
        $forecast = AnalyticsForecast::query()->where('forecast_key', 'risk_forecast')->firstOrFail();

        $this->assertGreaterThan(0, $forecast->predicted_value);
        $this->assertNotEmpty($forecast->explanation);
    }

    public function test_dashboard_handles_missing_data_without_fake_zeroes(): void
    {
        $response = $this->dashboard('executive');

        $response->assertOk();
        $response->assertJsonPath('data.data_quality.status', 'missing');
        $this->assertNull($response->json('data.kpis.0.value'));
    }

    public function test_student_dashboard_ignores_other_user_filter(): void
    {
        $student = LmsUser::query()->where('email', 'sv.lms@vabis.edu.vn')->firstOrFail();
        $other = LmsUser::query()->where('user_type', 'student')->where('id', '!=', $student->id)->firstOrFail();
        $this->metric('course_completion_rate', 55, ['user_id' => $student->id]);
        $this->metric('course_completion_rate', 95, ['user_id' => $other->id]);

        $response = $this->withTenant('sv.lms@vabis.edu.vn')->getJson('/api/v1/dashboards/student?user_id='.$other->id);

        $response->assertOk();
        $this->assertSame(55, (int) $response->json('data.kpis.0.value'));
    }

    public function test_teacher_dashboard_only_uses_assigned_classes(): void
    {
        $teacher = LmsUser::query()->where('email', 'gv.lms@vabis.edu.vn')->firstOrFail();
        $course = Course::query()->firstOrFail();
        $classA = $this->classSection($course->id, 'TA');
        $classB = $this->classSection($course->id, 'TB');
        DB::table('teacher_assignments')->insert([
            'tenant_id' => 1,
            'course_id' => $course->id,
            'class_section_id' => $classA,
            'user_id' => $teacher->id,
            'role' => 'primary',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->metric('active_learners', 30, ['class_id' => $classA, 'course_id' => $course->id]);
        $this->metric('active_learners', 80, ['class_id' => $classB, 'course_id' => $course->id]);

        $response = $this->withTenant('gv.lms@vabis.edu.vn')->getJson('/api/v1/dashboards/teacher');

        $response->assertOk();
        $this->assertSame(30, (int) $response->json('data.kpis.0.value'));
    }

    public function test_dashboard_export_runs(): void
    {
        $this->metric('active_learners', 1200);

        $response = $this->withTenant()->postJson('/api/v1/dashboards/export', [
            'dashboard_key' => 'executive',
            'format' => 'excel',
        ]);

        $response->assertOk();
        $this->assertStringEndsWith('.csv', $response->json('data.filename'));
    }

    private function dashboard(string $key, array $query = [])
    {
        $qs = http_build_query($query);

        return $this->withTenant()->getJson('/api/v1/dashboards/'.$key.($qs ? '?'.$qs : ''));
    }

    private function withTenant(string $email = 'admin.lms@vabis.edu.vn'): self
    {
        return $this->withHeader('X-Tenant-Code', 'VABIS')
            ->withHeader('X-Demo-User-Email', $email);
    }

    private function metric(string $key, float $value, array $scope = [], array $dimension = []): DashboardMetricSnapshot
    {
        return DashboardMetricSnapshot::query()->create(array_merge([
            'tenant_id' => 1,
            'snapshot_date' => now()->toDateString(),
            'metric_key' => $key,
            'metric_value' => $value,
            'metric_unit' => str_contains($key, 'rate') ? '%' : null,
            'dimension' => $dimension,
        ], $scope));
    }

    private function studentCourse(): array
    {
        return [
            LmsUser::query()->where('user_type', 'student')->firstOrFail(),
            Course::query()->firstOrFail(),
        ];
    }

    private function classSection(int $courseId, string $suffix): int
    {
        return (int) DB::table('class_sections')->insertGetId([
            'tenant_id' => 1,
            'course_id' => $courseId,
            'code' => 'TEST-'.$suffix,
            'name' => 'Test class '.$suffix,
            'section_type' => 'class_section',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
