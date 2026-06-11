<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Services\AttemptService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExamDataRepairSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('PRAGMA busy_timeout = 60000');

        $this->repairPublishedExamsWithoutQuestions();
        $this->call(CourseQuizEnrollmentSeeder::class);
        $this->repairAttemptSnapshots();
        $this->repairSubmittedAttemptAnswers();
        $this->markSeededFlaggedAttemptsReviewed();
    }

    private function repairPublishedExamsWithoutQuestions(): void
    {
        Exam::query()
            ->whereIn('status', ['published', 'open'])
            ->whereDoesntHave('questions')
            ->orderBy('id')
            ->get()
            ->each(function (Exam $exam): void {
                $bank = $this->ensureQuestionBank($exam);
                $questions = collect(range(1, 5))->map(fn (int $index) => $this->ensureQuestion($exam, $bank, $index));
                $section = ExamSection::query()->updateOrCreate([
                    'tenant_id' => $exam->tenant_id,
                    'exam_id' => $exam->id,
                    'sort_order' => 1,
                ], [
                    'title' => 'Nội dung kiểm tra chính',
                    'description' => 'Câu hỏi repair để đề published có cấu trúc hợp lệ.',
                    'question_count' => $questions->count(),
                    'score' => 10,
                    'config' => ['repaired_seed_data' => true],
                ]);

                $questions->values()->each(function (Question $question, int $index) use ($exam, $section): void {
                    ExamQuestion::query()->updateOrCreate([
                        'tenant_id' => $exam->tenant_id,
                        'exam_id' => $exam->id,
                        'question_id' => $question->id,
                    ], [
                        'section_id' => $section->id,
                        'score' => 2,
                        'sort_order' => $index + 1,
                        'required' => true,
                        'metadata' => ['repaired_seed_data' => true],
                    ]);
                });

                $exam->forceFill([
                    'question_bank_id' => $bank->id,
                    'total_score' => $exam->total_score > 0 ? $exam->total_score : 10,
                    'pass_score' => $exam->pass_score ?: 7,
                    'duration_minutes' => $exam->duration_minutes ?: 15,
                    'settings' => array_replace($exam->settings ?? [], ['repaired_seed_data' => true]),
                ])->save();
            });
    }

    private function ensureQuestionBank(Exam $exam): QuestionBank
    {
        if ($exam->question_bank_id) {
            $bank = QuestionBank::query()->find($exam->question_bank_id);
            if ($bank) {
                return $bank;
            }
        }

        return QuestionBank::query()->updateOrCreate([
            'tenant_id' => $exam->tenant_id,
            'code' => 'REPAIR-BANK-'.$exam->id,
        ], [
            'course_id' => $exam->course_id,
            'name' => 'Ngân hàng repair - '.$exam->title,
            'description' => 'Câu hỏi bổ sung để dữ liệu đề thi mẫu hợp lệ.',
            'visibility' => 'internal',
            'status' => 'published',
            'owner_id' => $exam->created_by,
            'settings' => ['repaired_seed_data' => true],
        ]);
    }

    private function ensureQuestion(Exam $exam, QuestionBank $bank, int $index): Question
    {
        $question = Question::query()->updateOrCreate([
            'tenant_id' => $exam->tenant_id,
            'code' => 'REPAIR-Q-'.$exam->id.'-'.$index,
        ], [
            'question_bank_id' => $bank->id,
            'question_type' => 'single_choice',
            'title' => 'Câu repair '.$index.' - '.$exam->title,
            'stem' => 'Phương án nào phù hợp nhất với mục tiêu đánh giá của đề "'.$exam->title.'"?',
            'explanation' => 'Phương án đúng bám sát mục tiêu, minh chứng và tiêu chí đánh giá.',
            'difficulty' => $index <= 2 ? 'easy' : 'medium',
            'bloom_level' => ['remember', 'understand', 'apply', 'analyze', 'evaluate'][$index - 1],
            'default_score' => 2,
            'penalty_score' => 0,
            'time_limit_seconds' => 120,
            'status' => 'approved',
            'owner_id' => $exam->created_by,
            'approved_by' => $exam->created_by,
            'approved_at' => now(),
            'metadata' => ['repaired_seed_data' => true],
        ]);

        foreach (['A', 'B', 'C', 'D'] as $optionIndex => $key) {
            QuestionOption::query()->updateOrCreate([
                'tenant_id' => $exam->tenant_id,
                'question_id' => $question->id,
                'option_key' => $key,
            ], [
                'content' => $optionIndex === 0
                    ? 'Xác định đúng yêu cầu, áp dụng kiến thức và có minh chứng rõ ràng.'
                    : ['Chỉ ghi nhớ thuật ngữ.', 'Bỏ qua tiêu chí đánh giá.', 'Không có minh chứng thực hành.'][$optionIndex - 1],
                'is_correct' => $optionIndex === 0,
                'score_weight' => $optionIndex === 0 ? 1 : 0,
                'feedback' => $optionIndex === 0 ? 'Chính xác.' : 'Chưa đúng.',
                'sort_order' => $optionIndex + 1,
                'metadata' => ['repaired_seed_data' => true],
            ]);
        }

        return $question;
    }

    private function repairAttemptSnapshots(): void
    {
        $attemptService = app(AttemptService::class);

        ExamAttempt::query()
            ->whereIn('status', ['in_progress', 'submitted', 'auto_submitted', 'graded', 'published'])
            ->whereDoesntHave('attemptQuestions')
            ->with('exam.questions')
            ->orderBy('id')
            ->chunkById(100, function ($attempts) use ($attemptService): void {
                foreach ($attempts as $attempt) {
                    if ($attempt->exam && $attempt->exam->questions->isNotEmpty()) {
                        $attemptService->generateAttemptQuestions($attempt);
                    }
                }
            });
    }

    private function repairSubmittedAttemptAnswers(): void
    {
        DB::table('exam_attempts')
            ->leftJoin('exam_answers', 'exam_answers.attempt_id', '=', 'exam_attempts.id')
            ->whereIn('exam_attempts.status', ['submitted', 'auto_submitted', 'graded', 'published'])
            ->whereNull('exam_answers.id')
            ->orderBy('exam_attempts.id')
            ->select('exam_attempts.id', 'exam_attempts.tenant_id')
            ->chunkById(100, function ($attempts): void {
                $attemptIds = $attempts->pluck('id')->all();
                $questions = DB::table('exam_attempt_questions')
                    ->whereIn('attempt_id', $attemptIds)
                    ->orderBy('attempt_id')
                    ->orderBy('display_order')
                    ->get(['id', 'tenant_id', 'attempt_id', 'question_id']);

                $now = now();
                $rows = $questions->map(fn ($question) => [
                    'tenant_id' => $question->tenant_id,
                    'attempt_id' => $question->attempt_id,
                    'attempt_question_id' => $question->id,
                    'question_id' => $question->question_id,
                    'answer_data' => json_encode([]),
                    'is_correct' => false,
                    'score' => 0,
                    'feedback' => 'Dữ liệu seed repair: chưa có câu trả lời.',
                    'graded_at' => $now,
                    'autosaved_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('exam_answers')->insert($chunk);
                }
            }, 'exam_attempts.id', 'id');
    }

    private function markSeededFlaggedAttemptsReviewed(): void
    {
        ExamAttempt::query()
            ->where('suspicious_score', '>=', 70)
            ->orderBy('id')
            ->chunkById(100, function ($attempts): void {
                foreach ($attempts as $attempt) {
                    $metadata = $attempt->metadata ?? [];
                    if ($metadata['reviewed_at'] ?? null) {
                        continue;
                    }

                    $attempt->forceFill([
                        'metadata' => array_replace($metadata, [
                            'reviewed_at' => now()->toDateTimeString(),
                            'reviewed_by' => 'seed_repair',
                            'review_note' => 'Flagged attempt mẫu đã được đánh dấu đã rà soát.',
                        ]),
                    ])->save();
                }
            });
    }
}
