<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamBlueprint;
use App\Models\ExamEnrollment;
use App\Models\ExamQuestion;
use App\Models\LmsUser;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Services\AttemptService;
use App\Services\ExamEnrollmentService;
use App\Services\ExamService;
use App\Services\ProctoringEventService;
use App\Services\QuestionService;
use Database\Seeders\CoreSeeder;
use Database\Seeders\CourseStudioSeeder;
use Database\Seeders\QuestionBankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnlineExamFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->seed(CourseStudioSeeder::class);
        $this->seed(QuestionBankSeeder::class);
    }

    public function test_create_exam_from_blueprint_and_assign_user(): void
    {
        $exam = $this->exam();
        app(ExamService::class)->buildExamFromBlueprint($exam);
        $this->assertGreaterThan(0, $exam->questions()->count());
        $student = LmsUser::query()->where('user_type', 'student')->firstOrFail();
        app(ExamEnrollmentService::class)->assignExamToUser($exam, $student->id, 1);
        $this->assertDatabaseHas('exam_enrollments', ['exam_id' => $exam->id, 'user_id' => $student->id]);
    }

    public function test_start_attempt_generates_snapshot_without_client_correct_answer(): void
    {
        [$exam, $student] = $this->publishedAssignedExam();
        $attempt = app(AttemptService::class)->startAttempt($exam, $student->id);
        $state = app(AttemptService::class)->getAttemptState($attempt->fresh('attemptQuestions'));
        $this->assertGreaterThan(0, $attempt->attemptQuestions()->count());
        $this->assertArrayNotHasKey('is_correct', $state['questions'][0]['options'][0] ?? []);
    }

    public function test_autosave_submit_and_auto_grade_mcq(): void
    {
        [$exam, $student] = $this->publishedAssignedExam();
        $attempt = app(AttemptService::class)->startAttempt($exam, $student->id);
        $aq = $attempt->attemptQuestions()->get()->first(fn ($item) => collect($item->options_snapshot)->firstWhere('is_correct', true));
        $this->assertNotNull($aq);
        $correct = collect($aq->options_snapshot)->firstWhere('is_correct', true)['option_key'];
        app(AttemptService::class)->autosaveAnswer($attempt, $aq->id, ['option_key' => $correct]);
        $graded = app(AttemptService::class)->submitAttempt($attempt->fresh());
        $this->assertSame('graded', $graded->status);
        $this->assertDatabaseHas('exam_results', ['attempt_id' => $graded->id]);
    }

    public function test_online_exam_scores_pass_status_and_result_percent_are_correct(): void
    {
        [$exam, $student] = $this->controlledAssignedExam();
        $attempt = app(AttemptService::class)->startAttempt($exam, $student->id);
        $questions = $attempt->attemptQuestions()->get();

        $correct = collect($questions[0]->options_snapshot)->firstWhere('is_correct', true)['option_key'];
        $wrong = collect($questions[1]->options_snapshot)->firstWhere('is_correct', false)['option_key'];

        app(AttemptService::class)->autosaveAnswer($attempt, $questions[0]->id, ['option_key' => $correct]);
        app(AttemptService::class)->autosaveAnswer($attempt, $questions[1]->id, ['option_key' => $wrong]);

        $graded = app(AttemptService::class)->submitAttempt($attempt->fresh());

        $this->assertSame('graded', $graded->status);
        $this->assertSame('passed', $graded->pass_status);
        $this->assertEquals(5.0, (float) $graded->score);
        $this->assertDatabaseHas('exam_results', [
            'attempt_id' => $graded->id,
            'score' => 5,
            'max_score' => 10,
            'percent' => 50,
            'pass_status' => 'passed',
            'published' => true,
        ]);
    }

    public function test_online_exam_auto_submit_is_graded_and_unanswered_questions_get_zero(): void
    {
        [$exam, $student] = $this->controlledAssignedExam();
        $attempt = app(AttemptService::class)->startAttempt($exam, $student->id);
        $firstQuestion = $attempt->attemptQuestions()->firstOrFail();
        $correct = collect($firstQuestion->options_snapshot)->firstWhere('is_correct', true)['option_key'];

        app(AttemptService::class)->autosaveAnswer($attempt, $firstQuestion->id, ['option_key' => $correct]);

        $graded = app(AttemptService::class)->autoSubmitExpiredAttempt($attempt->fresh());

        $this->assertSame('graded', $graded->status);
        $this->assertEquals(5.0, (float) $graded->score);
        $this->assertSame(2, $graded->answers()->count());
        $this->assertDatabaseHas('exam_answers', [
            'attempt_id' => $graded->id,
            'is_correct' => false,
            'score' => 0,
            'feedback' => 'Chưa trả lời',
        ]);
    }

    public function test_online_exam_enforces_max_attempts(): void
    {
        [$exam, $student] = $this->controlledAssignedExam();
        $attempt = app(AttemptService::class)->startAttempt($exam, $student->id);
        app(AttemptService::class)->submitAttempt($attempt->fresh());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Người học đã dùng hết số lần làm bài.');

        app(AttemptService::class)->startAttempt($exam->fresh(), $student->id);
    }

    public function test_exam_eligibility_respects_enrollment_window_and_published_status(): void
    {
        [$exam, $student] = $this->controlledAssignedExam();
        $service = app(ExamEnrollmentService::class);
        $enrollment = ExamEnrollment::query()->where('exam_id', $exam->id)->where('user_id', $student->id)->firstOrFail();

        $enrollment->forceFill(['available_from' => now()->addDay(), 'available_until' => now()->addDays(2)])->save();
        $this->assertFalse($service->checkUserEligibility($exam->fresh(), $student->id));

        $enrollment->forceFill(['available_from' => now()->subDays(2), 'available_until' => now()->subDay()])->save();
        $this->assertFalse($service->checkUserEligibility($exam->fresh(), $student->id));

        $enrollment->forceFill(['available_from' => now()->subDay(), 'available_until' => now()->addDay()])->save();
        $this->assertTrue($service->checkUserEligibility($exam->fresh(), $student->id));

        $draftExam = $this->exam();
        app(ExamEnrollmentService::class)->assignExamToUser($draftExam, $student->id, 1, [
            'available_from' => now()->subDay(),
            'available_until' => now()->addDay(),
        ]);

        $availableIds = collect($service->getAvailableExamsForUser(1, $student->id)->items())->pluck('id');
        $this->assertTrue($availableIds->contains($exam->id));
        $this->assertFalse($availableIds->contains($draftExam->id));
    }

    public function test_proctoring_flags_high_suspicious_attempt(): void
    {
        [$exam, $student] = $this->publishedAssignedExam();
        $attempt = app(AttemptService::class)->startAttempt($exam, $student->id);
        $proctor = app(ProctoringEventService::class);
        foreach (range(1, 4) as $i) {
            $proctor->recordEvent($attempt->fresh(), 'copy_attempt');
        }
        $this->assertSame('flagged', $attempt->fresh()->status);
    }

    private function publishedAssignedExam(): array
    {
        $exam = app(ExamService::class)->buildExamFromBlueprint($this->exam());
        $exam->forceFill(['status' => 'published'])->save();
        $student = LmsUser::query()->where('user_type', 'student')->firstOrFail();
        app(ExamEnrollmentService::class)->assignExamToUser($exam, $student->id, 1);
        return [$exam->fresh(), $student];
    }

    private function controlledAssignedExam(): array
    {
        $bank = QuestionBank::query()->firstOrFail();
        $first = $this->singleChoiceQuestion($bank, 'LOGIC-1');
        $second = $this->singleChoiceQuestion($bank, 'LOGIC-2');

        $exam = Exam::query()->create([
            'tenant_id' => 1,
            'question_bank_id' => $bank->id,
            'code' => 'EXAM-LOGIC-'.uniqid(),
            'title' => 'Exam logic kiểm thử',
            'exam_type' => 'quiz',
            'delivery_mode' => 'self_paced',
            'status' => 'published',
            'total_score' => 10,
            'pass_score' => 5,
            'duration_minutes' => 45,
            'max_attempts' => 1,
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'show_result_mode' => 'immediately',
            'created_by' => 1,
        ]);

        foreach ([$first, $second] as $index => $question) {
            ExamQuestion::query()->create([
                'tenant_id' => 1,
                'exam_id' => $exam->id,
                'question_id' => $question->id,
                'score' => 5,
                'sort_order' => $index + 1,
                'required' => true,
            ]);
        }

        $student = LmsUser::query()->where('user_type', 'student')->firstOrFail();
        app(ExamEnrollmentService::class)->assignExamToUser($exam, $student->id, 1);

        return [$exam->fresh(), $student];
    }

    private function singleChoiceQuestion(QuestionBank $bank, string $code): Question
    {
        return app(QuestionService::class)->createQuestion([
            'tenant_id' => 1,
            'question_bank_id' => $bank->id,
            'code' => $code.'-'.uniqid(),
            'question_type' => 'single_choice',
            'title' => 'Câu hỏi '.$code,
            'stem' => 'Chọn đáp án đúng.',
            'difficulty' => 'easy',
            'bloom_level' => 'remember',
            'default_score' => 5,
            'status' => 'approved',
            'owner_id' => 1,
            'options' => [
                ['option_key' => 'A', 'content' => 'Đúng', 'is_correct' => true, 'score_weight' => 1],
                ['option_key' => 'B', 'content' => 'Sai', 'is_correct' => false, 'score_weight' => 0],
            ],
        ]);
    }

    private function exam(): Exam
    {
        $blueprint = ExamBlueprint::query()->firstOrFail();
        return Exam::query()->create(['tenant_id' => 1, 'question_bank_id' => $blueprint->question_bank_id, 'blueprint_id' => $blueprint->id, 'code' => 'EXAM-T-'.uniqid(), 'title' => 'Exam kiểm thử', 'exam_type' => 'quiz', 'delivery_mode' => 'self_paced', 'status' => 'draft', 'total_score' => 10, 'pass_score' => 5, 'duration_minutes' => 45, 'max_attempts' => 1, 'show_result_mode' => 'immediately', 'created_by' => 1]);
    }
}
