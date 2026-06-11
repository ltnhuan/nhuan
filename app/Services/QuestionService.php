<?php

namespace App\Services;

use App\Models\Question;
use App\Models\QuestionFillBlankAnswer;
use App\Models\QuestionMatchingPair;
use App\Models\QuestionOption;
use App\Models\QuestionVersion;
use Illuminate\Support\Facades\DB;

class QuestionService
{
    public function __construct(private readonly QuestionTypeValidator $validator)
    {
    }

    public function createQuestion(array $data): Question
    {
        $this->validator->validate($data['question_type'], $data);

        return DB::transaction(function () use ($data) {
            $question = Question::query()->create(collect($data)->except(['options', 'matching_pairs', 'fill_blank_answers', 'outcome_ids'])->all() + ['status' => $data['status'] ?? 'draft']);
            $this->syncDetails($question, $data);
            $this->createQuestionVersion($question, $data['change_note'] ?? 'Tạo câu hỏi', $data['owner_id'] ?? null);
            return $question->fresh(['options', 'matchingPairs', 'fillBlankAnswers']);
        });
    }

    public function updateQuestion(Question $question, array $data): Question
    {
        $payload = array_merge($question->toArray(), $data);
        $this->validator->validate($payload['question_type'], $payload);

        return DB::transaction(function () use ($question, $data) {
            $question->fill(collect($data)->except(['options', 'matching_pairs', 'fill_blank_answers', 'outcome_ids', 'change_note'])->all())->save();
            $this->syncDetails($question, $data);
            $this->createQuestionVersion($question, $data['change_note'] ?? 'Cập nhật câu hỏi', $data['updated_by'] ?? null);
            return $question->fresh(['options', 'matchingPairs', 'fillBlankAnswers', 'versions']);
        });
    }

    public function createQuestionVersion(Question $question, ?string $changeNote, ?int $createdBy): QuestionVersion
    {
        // Snapshot lưu cả nội dung câu hỏi và đáp án để truy vết khi đề thi đã phát hành.
        $snapshot = $question->fresh(['options', 'matchingPairs', 'fillBlankAnswers', 'outcomes'])->toArray();
        $version = ((int) QuestionVersion::query()->where('question_id', $question->id)->max('version')) + 1;
        return QuestionVersion::query()->create(['tenant_id' => $question->tenant_id, 'question_id' => $question->id, 'version' => $version, 'snapshot' => $snapshot, 'change_note' => $changeNote, 'created_by' => $createdBy, 'created_at' => now()]);
    }

    public function approveQuestion(Question $question, int $approvedBy): Question
    {
        $question->forceFill(['status' => 'approved', 'approved_by' => $approvedBy, 'approved_at' => now()])->save();
        return $question;
    }

    public function publishQuestion(Question $question): Question
    {
        $question->forceFill(['status' => 'published'])->save();
        return $question;
    }

    public function archiveQuestion(Question $question): Question
    {
        $question->forceFill(['status' => 'archived'])->save();
        return $question;
    }

    public function cloneQuestion(Question $question, int $ownerId): Question
    {
        return DB::transaction(function () use ($question, $ownerId) {
            $copy = $question->replicate(['code', 'status', 'owner_id', 'approved_by', 'approved_at']);
            $copy->code = $question->code.'-COPY-'.now()->format('His');
            $copy->title = $question->title.' - Bản sao';
            $copy->status = 'draft';
            $copy->owner_id = $ownerId;
            $copy->save();
            foreach ($question->options as $option) {
                $copy->options()->create($option->replicate(['question_id'])->toArray() + ['question_id' => $copy->id]);
            }
            $this->createQuestionVersion($copy, 'Sao chép câu hỏi', $ownerId);
            return $copy->fresh(['options']);
        });
    }

    private function syncDetails(Question $question, array $data): void
    {
        if (array_key_exists('options', $data)) {
            QuestionOption::query()->where('question_id', $question->id)->delete();
            foreach ($data['options'] ?? [] as $index => $option) {
                $question->options()->create($option + ['tenant_id' => $question->tenant_id, 'option_key' => $option['option_key'] ?? chr(65 + $index), 'sort_order' => $index]);
            }
        }
        if (array_key_exists('matching_pairs', $data)) {
            QuestionMatchingPair::query()->where('question_id', $question->id)->delete();
            foreach ($data['matching_pairs'] ?? [] as $index => $pair) {
                $question->matchingPairs()->create($pair + ['tenant_id' => $question->tenant_id, 'sort_order' => $index]);
            }
        }
        if (array_key_exists('fill_blank_answers', $data)) {
            QuestionFillBlankAnswer::query()->where('question_id', $question->id)->delete();
            foreach ($data['fill_blank_answers'] ?? [] as $answer) {
                $question->fillBlankAnswers()->create($answer + ['tenant_id' => $question->tenant_id]);
            }
        }
    }
}
