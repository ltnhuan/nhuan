<?php

namespace App\Services;

use App\Models\LearningOutcome;
use App\Models\Question;
use Illuminate\Support\Facades\DB;

class OutcomeMappingService
{
    public function mapQuestionToCloPlo(Question $question, array $outcomes): void
    {
        foreach ($outcomes as $outcome) {
            DB::table('question_outcome_map')->updateOrInsert(
                ['question_id' => $question->id, 'outcome_id' => $outcome['outcome_id']],
                ['tenant_id' => $question->tenant_id, 'weight' => $outcome['weight'] ?? 1]
            );
        }
    }

    public function bulkMap(array $items): int
    {
        foreach ($items as $item) {
            $question = Question::query()->findOrFail($item['question_id']);
            $this->mapQuestionToCloPlo($question, $item['outcomes']);
        }
        return count($items);
    }

    public function getCoverageMatrix(int $tenantId, ?int $questionBankId = null): array
    {
        $outcomes = LearningOutcome::query()->where('tenant_id', $tenantId)->where('status', 'active')->orderBy('code')->get();
        $rows = [];
        foreach ($outcomes as $outcome) {
            $query = Question::query()
                ->join('question_outcome_map', 'questions.id', '=', 'question_outcome_map.question_id')
                ->where('questions.tenant_id', $tenantId)
                ->where('question_outcome_map.outcome_id', $outcome->id);
            if ($questionBankId) {
                $query->where('questions.question_bank_id', $questionBankId);
            }
            $rows[] = [
                'outcome' => $outcome->code,
                'name' => $outcome->name,
                'total' => (clone $query)->count(),
                'by_bloom' => (clone $query)->select('bloom_level', DB::raw('count(*) as total'))->groupBy('bloom_level')->pluck('total', 'bloom_level'),
                'by_difficulty' => (clone $query)->select('difficulty', DB::raw('count(*) as total'))->groupBy('difficulty')->pluck('total', 'difficulty'),
            ];
        }
        return $rows;
    }
}
