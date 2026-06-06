<?php

namespace Tests\Feature;

use App\Models\ExamBlueprint;
use App\Models\LearningOutcome;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Services\OutcomeMappingService;
use App\Services\QuestionService;
use App\Services\QuestionTypeValidator;
use App\Services\RandomExamEngine;
use Database\Seeders\CoreSeeder;
use Database\Seeders\CourseStudioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionBankFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->seed(CourseStudioSeeder::class);
    }

    public function test_create_question_bank(): void
    {
        $this->withHeaders($this->adminHeaders())->postJson('/api/v1/question-banks', ['code' => 'QB-T', 'name' => 'Ngân hàng kiểm thử'])->assertCreated();
        $this->assertDatabaseHas('question_banks', ['code' => 'QB-T']);
    }

    public function test_validate_single_choice_requires_one_correct_answer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(QuestionTypeValidator::class)->validate('single_choice', ['options' => [['content' => 'A', 'is_correct' => true], ['content' => 'B', 'is_correct' => true]]]);
    }

    public function test_create_question_and_version(): void
    {
        $question = $this->createSingleChoice();
        $this->assertDatabaseHas('question_versions', ['question_id' => $question->id, 'version' => 1]);
    }

    public function test_update_question_creates_next_version(): void
    {
        $question = $this->createSingleChoice();
        app(QuestionService::class)->updateQuestion($question, ['title' => 'Câu hỏi đã sửa', 'options' => [['content' => 'A', 'is_correct' => true], ['content' => 'B', 'is_correct' => false]]]);
        $this->assertDatabaseHas('question_versions', ['question_id' => $question->id, 'version' => 2]);
    }

    public function test_map_outcome(): void
    {
        $question = $this->createSingleChoice();
        $outcome = LearningOutcome::query()->create(['tenant_id' => 1, 'code' => 'CLO-T', 'name' => 'CLO kiểm thử', 'type' => 'CLO', 'status' => 'active']);
        app(OutcomeMappingService::class)->mapQuestionToCloPlo($question, [['outcome_id' => $outcome->id, 'weight' => 1]]);
        $this->assertDatabaseHas('question_outcome_map', ['question_id' => $question->id, 'outcome_id' => $outcome->id]);
    }

    public function test_blueprint_preview_warns_when_not_enough_questions(): void
    {
        $bank = $this->bank();
        $blueprint = ExamBlueprint::query()->create(['tenant_id' => 1, 'question_bank_id' => $bank->id, 'code' => 'BP-T', 'name' => 'Blueprint kiểm thử', 'total_questions' => 10, 'total_score' => 10, 'duration_minutes' => 45, 'status' => 'active', 'config' => ['sections' => [['name' => 'Khó', 'question_count' => 10, 'difficulty' => ['expert'], 'bloom_level' => ['create'], 'score_each' => 1]]]]);
        $preview = app(RandomExamEngine::class)->generateFromBlueprint($blueprint);
        $this->assertNotEmpty($preview['warnings']);
    }

    private function createSingleChoice(): Question
    {
        $bank = $this->bank();
        return app(QuestionService::class)->createQuestion(['tenant_id' => 1, 'question_bank_id' => $bank->id, 'code' => 'Q-T-'.uniqid(), 'question_type' => 'single_choice', 'title' => 'Câu hỏi kiểm thử', 'stem' => 'Nội dung?', 'difficulty' => 'easy', 'bloom_level' => 'remember', 'default_score' => 1, 'owner_id' => 1, 'options' => [['content' => 'A', 'is_correct' => true], ['content' => 'B', 'is_correct' => false]]]);
    }

    private function bank(): QuestionBank
    {
        return QuestionBank::query()->first() ?: QuestionBank::query()->create(['tenant_id' => 1, 'code' => 'QB-BASE', 'name' => 'Ngân hàng nền', 'visibility' => 'tenant', 'status' => 'published', 'owner_id' => 1]);
    }

    private function adminHeaders(): array
    {
        return ['X-Tenant-Code' => 'VABIS', 'X-Demo-User-Email' => 'admin.lms@vabis.edu.vn'];
    }
}
