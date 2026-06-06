<?php

namespace App\Services;

use App\Models\ExamBlueprint;
use App\Models\Question;

class RandomExamEngine
{
    public function generateFromBlueprint(ExamBlueprint $blueprint): array
    {
        $draft = ['blueprint_id' => $blueprint->id, 'sections' => [], 'warnings' => []];
        $usedIds = [];

        foreach ($blueprint->config['sections'] ?? [] as $section) {
            $query = Question::query()->where('tenant_id', $blueprint->tenant_id)->whereIn('status', ['approved', 'published']);
            if ($blueprint->question_bank_id) {
                $query->where('question_bank_id', $blueprint->question_bank_id);
            }
            $query = $this->filterQuestionsByDifficulty($query, $section['difficulty'] ?? []);
            $query = $this->filterQuestionsByBloom($query, $section['bloom_level'] ?? []);
            $query = $this->avoidDuplicateQuestions($query, $usedIds);
            $questions = $query->inRandomOrder()->limit((int) $section['question_count'])->get(['id', 'code', 'title', 'question_type', 'difficulty', 'bloom_level', 'default_score']);
            $usedIds = array_merge($usedIds, $questions->pluck('id')->all());

            if ($questions->count() < (int) $section['question_count']) {
                $draft['warnings'][] = "Mục {$section['name']} không đủ số câu hỏi yêu cầu.";
            }
            $draft['sections'][] = ['name' => $section['name'], 'score_each' => $section['score_each'] ?? 1, 'questions' => $questions];
        }

        return $draft;
    }

    public function filterQuestionsByDifficulty($query, array $difficulty)
    {
        return $difficulty ? $query->whereIn('difficulty', $difficulty) : $query;
    }

    public function filterQuestionsByBloom($query, array $bloom)
    {
        return $bloom ? $query->whereIn('bloom_level', $bloom) : $query;
    }

    public function filterQuestionsByOutcome($query, array $outcomeIds)
    {
        return $outcomeIds ? $query->whereHas('outcomes', fn ($q) => $q->whereIn('learning_outcomes.id', $outcomeIds)) : $query;
    }

    public function avoidDuplicateQuestions($query, array $usedIds)
    {
        return $usedIds ? $query->whereNotIn('id', $usedIds) : $query;
    }

    public function randomizeOptions(array $options): array
    {
        shuffle($options);
        return $options;
    }
}
