<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\LearnerRiskProfile;
use App\Models\LearningMetric;
use App\Models\LmsUser;
use App\Models\RiskAlert;
use App\Services\LearningAnalyticsService;
use Database\Seeders\CoreSeeder;
use Database\Seeders\CourseStudioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningAnalyticsFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->seed(CourseStudioSeeder::class);
    }

    public function test_risk_score_and_level_are_calculated_from_learning_metrics(): void
    {
        [$student, $course] = $this->studentCourse();
        $this->metric($student->id, $course->id, [
            'login_frequency' => 0,
            'study_time_minutes' => 30,
            'video_completion' => 20,
            'assignment_completion' => 10,
            'quiz_score' => 30,
            'attendance' => 40,
            'forum_activity' => 0,
        ]);

        $profile = app(LearningAnalyticsService::class)->calculateRisk(1, $student->id, $course->id);

        $this->assertGreaterThanOrEqual(70, $profile->risk_score);
        $this->assertContains($profile->risk_level, ['high', 'critical']);
        $this->assertContains('học bù', $profile->recommendations);
        $this->assertContains('ôn tập', $profile->recommendations);
    }

    public function test_early_warning_alerts_are_generated_for_progress_grade_and_dropout_risks(): void
    {
        [$student, $course] = $this->studentCourse();
        $this->metric($student->id, $course->id, [
            'login_frequency' => 0,
            'study_time_minutes' => 20,
            'video_completion' => 15,
            'assignment_completion' => 15,
            'quiz_score' => 35,
            'attendance' => 50,
            'forum_activity' => 0,
        ]);

        app(LearningAnalyticsService::class)->calculateRisk(1, $student->id, $course->id);

        $this->assertSame(3, RiskAlert::query()->where('user_id', $student->id)->where('course_id', $course->id)->count());
        $this->assertDatabaseHas('risk_alerts', ['alert_type' => 'behind_progress', 'message' => 'Chậm tiến độ học tập']);
        $this->assertDatabaseHas('risk_alerts', ['alert_type' => 'failing_course', 'message' => 'Nguy cơ rớt môn']);
        $this->assertDatabaseHas('risk_alerts', ['alert_type' => 'dropout_risk', 'message' => 'Nguy cơ nghỉ học']);
    }

    public function test_dashboard_returns_kpis_charts_and_alerts(): void
    {
        [$student, $course] = $this->studentCourse();
        $this->metric($student->id, $course->id, [
            'login_frequency' => 1,
            'study_time_minutes' => 90,
            'video_completion' => 45,
            'assignment_completion' => 55,
            'quiz_score' => 60,
            'attendance' => 75,
            'forum_activity' => 1,
        ]);
        app(LearningAnalyticsService::class)->calculateRisk(1, $student->id, $course->id);
        app(LearningAnalyticsService::class)->buildSummary(1, 'daily', now());

        $response = $this->withHeader('X-Tenant-Code', 'VABIS')
            ->withHeader('X-Demo-User-Email', 'admin.lms@vabis.edu.vn')
            ->getJson('/api/v1/analytics/dashboard?audience=executive');

        $response->assertOk();
        $response->assertJsonPath('audience', 'executive');
        $this->assertArrayHasKey('avg_risk_score', $response->json('kpis'));
        $this->assertNotEmpty($response->json('progress_trend'));
        $this->assertNotEmpty($response->json('grade_distribution'));
        $this->assertNotEmpty($response->json('completion_funnel'));
    }

    public function test_metrics_endpoint_obeys_course_and_date_filters(): void
    {
        [$student, $course] = $this->studentCourse();
        $secondCourse = Course::factory()->create();

        $this->metric($student->id, $course->id, [
            'metric_date' => now()->subDays(2)->toDateString(),
            'quiz_score' => 80,
        ]);
        $this->metric($student->id, $secondCourse->id, [
            'metric_date' => now()->toDateString(),
            'quiz_score' => 70,
        ]);

        $response = $this->withHeader('X-Tenant-Code', 'VABIS')
            ->withHeader('X-Demo-User-Email', 'admin.lms@vabis.edu.vn')
            ->getJson('/api/v1/analytics/metrics?course_id='.$course->id.'&from='.now()->subDays(3)->toDateString().'&to='.now()->subDays(1)->toDateString());

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertSame((string) $course->id, (string) $response->json('data.0.course_id'));
    }

    public function test_risks_and_alerts_endpoints_apply_date_filters_on_reports_page(): void
    {
        [$student, $course] = $this->studentCourse();
        $secondStudent = LmsUser::query()->where('user_type', 'student')->where('id', '!=', $student->id)->firstOrFail();

        LearnerRiskProfile::query()->create([
            'tenant_id' => 1,
            'user_id' => $student->id,
            'course_id' => $course->id,
            'risk_score' => 55,
            'risk_level' => 'medium',
            'risk_factors' => ['forum_activity_low'],
            'recommendations' => ['duy trì tiến độ'],
            'last_calculated_at' => now()->subDays(10),
        ]);

        LearnerRiskProfile::query()->create([
            'tenant_id' => 1,
            'user_id' => $secondStudent->id,
            'course_id' => $course->id,
            'risk_score' => 62,
            'risk_level' => 'medium',
            'risk_factors' => ['forum_activity_low'],
            'recommendations' => ['duy trì tiến độ'],
            'last_calculated_at' => now()->subDays(2),
        ]);

        RiskAlert::query()->create([
            'tenant_id' => 1,
            'user_id' => $student->id,
            'course_id' => $course->id,
            'risk_profile_id' => null,
            'alert_type' => 'course_dropout',
            'severity' => 'medium',
            'status' => 'open',
            'message' => 'Cảnh báo test',
            'recommended_actions' => ['theo dõi'],
            'triggered_at' => now()->subDays(2),
        ]);

        RiskAlert::query()->create([
            'tenant_id' => 1,
            'user_id' => $student->id,
            'course_id' => $course->id,
            'risk_profile_id' => null,
            'alert_type' => 'course_dropout',
            'severity' => 'medium',
            'status' => 'open',
            'message' => 'Cảnh báo cũ',
            'recommended_actions' => ['xử lý'],
            'triggered_at' => now()->subDays(10),
        ]);

        $risks = $this->withHeader('X-Tenant-Code', 'VABIS')
            ->withHeader('X-Demo-User-Email', 'admin.lms@vabis.edu.vn')
            ->getJson('/api/v1/analytics/risks?course_id='.$course->id.'&from='.now()->subDays(5)->toDateString().'&to='.now()->subDays(1)->toDateString());

        $risks->assertOk();
        $risks->assertJsonCount(1, 'data');

        $alerts = $this->withHeader('X-Tenant-Code', 'VABIS')
            ->withHeader('X-Demo-User-Email', 'admin.lms@vabis.edu.vn')
            ->getJson('/api/v1/analytics/alerts?course_id='.$course->id.'&from='.now()->subDays(5)->toDateString().'&to='.now()->subDays(1)->toDateString());

        $alerts->assertOk();
        $alerts->assertJsonCount(1, 'data');
    }

    private function studentCourse(): array
    {
        return [
            LmsUser::query()->where('user_type', 'student')->firstOrFail(),
            Course::query()->firstOrFail(),
        ];
    }

    private function metric(int $userId, int $courseId, array $overrides = []): LearningMetric
    {
        return LearningMetric::query()->create(array_replace([
            'tenant_id' => 1,
            'user_id' => $userId,
            'course_id' => $courseId,
            'metric_date' => now()->toDateString(),
            'login_frequency' => 3,
            'study_time_minutes' => 180,
            'video_completion' => 80,
            'assignment_completion' => 80,
            'quiz_score' => 75,
            'attendance' => 85,
            'forum_activity' => 2,
        ], $overrides));
    }
}
