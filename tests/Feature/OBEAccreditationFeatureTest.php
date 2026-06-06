<?php

namespace Tests\Feature;

use App\Models\CompetencyRecord;
use App\Models\Course;
use App\Models\LearningOutcome;
use App\Models\LmsUser;
use App\Services\AccreditationReportService;
use App\Services\AchievementAnalyticsService;
use App\Services\AssessmentMappingService;
use App\Services\OBEFrameworkService;
use App\Services\OutcomeMatrixService;
use Database\Seeders\CoreSeeder;
use Database\Seeders\CourseStudioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OBEAccreditationFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->seed(CourseStudioSeeder::class);
    }

    public function test_plo_clo_mapping_appears_in_matrix(): void
    {
        [$plo, $clo] = $this->outcomes();
        app(OBEFrameworkService::class)->mapOutcomePath(['tenant_id'=>1,'source_outcome_id'=>$clo->id,'target_outcome_id'=>$plo->id,'source_type'=>'CLO','source_id'=>$clo->id,'target_type'=>'PLO','target_id'=>$plo->id,'weight'=>0.8,'evidence_level'=>'assessed']);

        $matrix = app(OutcomeMatrixService::class)->matrix(1);

        $this->assertSame('strong', $matrix['rows'][0]['plos'][0]['heat']);
    }

    public function test_coverage_flags_unassessed_clo_and_weak_plo(): void
    {
        $this->outcomes();

        $coverage = app(OutcomeMatrixService::class)->coverage(1);

        $this->assertSame(1, $coverage['summary']['unassessed_clo_count']);
        $this->assertSame(1, $coverage['summary']['weak_plo_count']);
    }

    public function test_assessment_mapping_removes_unassessed_clo_gap(): void
    {
        [, $clo] = $this->outcomes();
        app(AssessmentMappingService::class)->mapAssessment(1, 'quiz', 10, [['outcome_id'=>$clo->id,'weight'=>1,'max_score'=>10]]);

        $coverage = app(OutcomeMatrixService::class)->coverage(1);

        $this->assertSame(0, $coverage['summary']['unassessed_clo_count']);
    }

    public function test_achievement_analytics_calculates_clo_percent(): void
    {
        [, $clo] = $this->outcomes();
        $student = LmsUser::query()->where('user_type','student')->firstOrFail();
        CompetencyRecord::query()->create(['tenant_id'=>1,'user_id'=>$student->id,'learning_outcome_id'=>$clo->id,'outcome_type'=>'CLO','code'=>$clo->code,'title'=>$clo->name,'score'=>82,'attainment_status'=>'achieved','evidence'=>['quiz'=>true]]);

        app(AchievementAnalyticsService::class)->recalculate(1);
        $dashboard = app(AchievementAnalyticsService::class)->dashboard(1);

        $this->assertSame(82.0, $dashboard['clo_percent']);
    }

    public function test_accreditation_report_export_metadata(): void
    {
        $report = app(AccreditationReportService::class)->generate(1, 'AUN-QA', 'pdf', [], 1);

        $this->assertSame('generated', $report->status);
        $this->assertSame('pdf', $report->format);
        $this->assertStringContainsString('aun-qa', $report->file_path);
    }

    public function test_api_dashboard_loads_for_accreditation_roles(): void
    {
        $this->withHeaders(['X-Demo-User-Email'=>'admin.lms@vabis.edu.vn'])->getJson('/api/v1/obe/dashboard')->assertOk()->assertJsonStructure(['clo_percent','plo_percent','competency_percent','at_risk_count']);
    }

    private function outcomes(): array
    {
        $course = Course::query()->firstOrFail();
        $plo = LearningOutcome::query()->create(['tenant_id'=>1,'code'=>'PLO-T','name'=>'PLO test','type'=>'PLO','status'=>'active']);
        $clo = LearningOutcome::query()->create(['tenant_id'=>1,'code'=>'CLO-T','name'=>'CLO test','type'=>'CLO','course_id'=>$course->id,'status'=>'active']);
        return [$plo, $clo];
    }
}
