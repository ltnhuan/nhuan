<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\LmsUser;
use App\Models\SurveyCampaign;
use App\Models\SurveyEvidenceFile;
use App\Models\SurveyForm;
use App\Models\SurveyImprovement;
use App\Services\SurveyService;
use Database\Seeders\CoreSeeder;
use Database\Seeders\CourseStudioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyEvaluationFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->seed(CourseStudioSeeder::class);
    }

    public function test_builder_supports_question_types_and_reorder(): void
    {
        $response = $this->admin()->postJson('/api/v1/surveys/forms', [
            'code' => 'QA-FORM',
            'title' => 'Khảo sát chất lượng',
            'survey_type' => 'course_evaluation',
            'questions' => [
                ['code' => 'text', 'question_type' => 'text', 'prompt' => 'Góp ý'],
                ['code' => 'rating', 'question_type' => 'rating', 'prompt' => 'Điểm tổng thể'],
                ['code' => 'matrix', 'question_type' => 'matrix', 'prompt' => 'Ma trận', 'rows' => ['Nội dung'], 'columns' => [1,2,3,4,5]],
                ['code' => 'mcq', 'question_type' => 'mcq', 'prompt' => 'Chọn một', 'options' => ['A','B']],
                ['code' => 'multi', 'question_type' => 'multi_select', 'prompt' => 'Chọn nhiều', 'options' => ['A','B']],
                ['code' => 'nps', 'question_type' => 'nps', 'prompt' => 'Recommendation Score'],
            ],
        ]);

        $response->assertCreated();
        $form = SurveyForm::query()->where('code', 'QA-FORM')->firstOrFail();
        $this->assertSame(['text','rating','matrix','mcq','multi_select','nps'], $form->questions()->pluck('question_type')->all());

        $ids = $form->questions()->pluck('id')->reverse()->values()->all();
        $this->admin()->postJson("/api/v1/surveys/forms/{$form->id}/reorder-questions", ['question_ids' => $ids])->assertOk();

        $this->assertSame($ids[0], $form->fresh()->questions()->first()->id);
    }

    public function test_anonymous_response_calculates_average_and_nps(): void
    {
        [$campaign, $student] = $this->campaign();

        $response = $this->student($student->email)->postJson("/api/v1/surveys/campaigns/{$campaign->id}/responses", [
            'answers' => [
                ['code' => 'expertise', 'value' => 5],
                ['code' => 'methodology', 'value' => 4],
                ['code' => 'teacher_nps', 'value' => 10],
                ['code' => 'comment', 'value' => 'Rất hữu ích'],
            ],
        ]);

        $response->assertCreated();
        $this->assertNull($response->json('respondent_id'));
        $this->assertSame(10, $response->json('nps_score'));
        $this->assertSame(6.33, (float) $response->json('average_score'));
        $this->assertDatabaseHas('survey_responses', ['survey_campaign_id' => $campaign->id, 'is_anonymous' => true, 'nps_score' => 10]);
    }

    public function test_analytics_improvement_evidence_and_exports_are_available(): void
    {
        [$campaign, $student] = $this->campaign();
        app(SurveyService::class)->submitResponse($campaign, ['answers' => [
            ['code' => 'expertise', 'value' => 5],
            ['code' => 'methodology', 'value' => 4],
            ['code' => 'teacher_nps', 'value' => 9],
        ]], $student->id);

        $analytics = $this->admin()->getJson('/api/v1/surveys/analytics');
        $analytics->assertOk()->assertJsonPath('summary.responses', 1)->assertJsonPath('summary.nps', 100);
        $this->assertNotEmpty($analytics->json('heatmap'));

        $improvement = $this->admin()->postJson('/api/v1/surveys/improvements', [
            'survey_campaign_id' => $campaign->id,
            'course_id' => $campaign->course_id,
            'issue_title' => 'Điểm hỗ trợ thấp',
            'improvement_action' => 'Bổ sung lịch hỗ trợ sau giờ học',
            'priority' => 'high',
        ]);
        $improvement->assertCreated();
        $this->assertSame(1, SurveyImprovement::query()->count());

        $evidence = $this->admin()->postJson('/api/v1/surveys/evidence', [
            'survey_campaign_id' => $campaign->id,
            'survey_improvement_id' => $improvement->json('id'),
            'title' => 'Báo cáo khảo sát',
            'file_path' => 'evidence/surveys/report.html',
        ]);
        $evidence->assertCreated();
        $this->assertSame(1, SurveyEvidenceFile::query()->count());

        $this->admin()->get("/api/v1/surveys/campaigns/{$campaign->id}/export?format=excel")->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->admin()->get("/api/v1/surveys/campaigns/{$campaign->id}/export?format=pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    private function campaign(): array
    {
        $course = Course::query()->firstOrFail();
        $student = LmsUser::query()->where('user_type', 'student')->firstOrFail();
        $form = app(SurveyService::class)->createForm([
            'tenant_id' => 1,
            'code' => 'TEACHER-EVAL',
            'title' => 'Đánh giá giảng viên',
            'survey_type' => 'teacher_evaluation',
            'created_by' => 1,
            'questions' => [
                ['code' => 'expertise', 'question_type' => 'rating', 'prompt' => 'Chuyên môn', 'required' => true],
                ['code' => 'methodology', 'question_type' => 'rating', 'prompt' => 'Phương pháp', 'required' => true],
                ['code' => 'teacher_nps', 'question_type' => 'nps', 'prompt' => 'Recommendation Score', 'required' => true],
                ['code' => 'comment', 'question_type' => 'text', 'prompt' => 'Góp ý'],
            ],
        ]);
        $campaign = app(SurveyService::class)->createCampaign([
            'tenant_id' => 1,
            'survey_form_id' => $form->id,
            'code' => 'TEACHER-CAMP',
            'title' => 'Đánh giá giảng viên theo khóa',
            'target_scope' => 'course',
            'course_id' => $course->id,
            'is_anonymous' => true,
            'allow_identified' => true,
            'status' => 'active',
            'created_by' => 1,
        ]);

        return [$campaign, $student];
    }

    private function admin()
    {
        return $this->withHeader('X-Tenant-Code', 'VABIS')->withHeader('X-Demo-User-Email', 'admin.lms@vabis.edu.vn');
    }

    private function student(string $email)
    {
        return $this->withHeader('X-Tenant-Code', 'VABIS')->withHeader('X-Demo-User-Email', $email);
    }
}
