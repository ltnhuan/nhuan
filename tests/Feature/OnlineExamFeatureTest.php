<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamBlueprint;
use App\Models\ExamEnrollment;
use App\Models\LmsUser;
use App\Models\QuestionBank;
use App\Services\AttemptService;
use App\Services\ExamEnrollmentService;
use App\Services\ExamService;
use App\Services\ProctoringEventService;
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

    private function exam(): Exam
    {
        $blueprint = ExamBlueprint::query()->firstOrFail();
        return Exam::query()->create(['tenant_id' => 1, 'question_bank_id' => $blueprint->question_bank_id, 'blueprint_id' => $blueprint->id, 'code' => 'EXAM-T-'.uniqid(), 'title' => 'Exam kiểm thử', 'exam_type' => 'quiz', 'delivery_mode' => 'self_paced', 'status' => 'draft', 'total_score' => 10, 'pass_score' => 5, 'duration_minutes' => 45, 'max_attempts' => 1, 'show_result_mode' => 'immediately', 'created_by' => 1]);
    }
}
