<?php

namespace App\Services;

use App\Models\Gradebook;
use App\Models\GradeItem;
use App\Models\GradeSummary;
use App\Models\LearnerGrade;

class GradeFormulaService
{
    public function __construct(private GradebookService $gradebooks) {}

    public function recalculate(Gradebook $gradebook): array
    {
        $this->gradebooks->ensureEditable($gradebook);
        $items = $gradebook->items()->get();
        $userIds = LearnerGrade::query()->where('gradebook_id', $gradebook->id)->distinct()->pluck('user_id');
        $count = 0;
        foreach ($userIds as $userId) {
            $this->calculateLearner($gradebook, $items, (int) $userId);
            $count++;
        }
        return ['learners' => $count, 'items' => $items->count()];
    }

    public function calculateLearner(Gradebook $gradebook, $items, int $userId): GradeSummary
    {
        $grades = LearnerGrade::query()->where('gradebook_id', $gradebook->id)->where('user_id', $userId)->get()->keyBy('grade_item_id');
        $total = 0.0; $max = 0.0;
        $formulaVars = [];
        foreach ($items as $item) {
            $grade = $grades->get($item->id);
            if (! $grade || $grade->final_score === null) continue;
            [$earned, $possible] = $this->itemContribution($item, (float) $grade->final_score);
            $total += $earned; $max += $possible;
            $normalized = $possible > 0 ? ($earned / $possible) * 100 : 0;
            $formulaVars['item_'.$item->id] = round($normalized, 4);
            $formulaVars[$this->variableName($item->title)] = round($normalized, 4);
            $formulaVars[$this->variableName($item->source_type)] = round($normalized, 4);
        }
        if ($gradebook->grading_scheme === 'formula' && ! empty($gradebook->settings['formula'])) {
            $total = $this->evaluateNamedFormula($gradebook->settings['formula'], $formulaVars);
            $max = (float) ($gradebook->settings['max_score'] ?? 100);
        }
        $percent = $max > 0 ? round(($total / $max) * 100, 2) : 0;
        return GradeSummary::query()->updateOrCreate(
            ['tenant_id' => $gradebook->tenant_id, 'gradebook_id' => $gradebook->id, 'user_id' => $userId],
            ['total_score' => round($total, 2), 'max_score' => round($max, 2), 'percent' => $percent, 'letter_grade' => $this->letter($percent), 'pass_status' => $percent >= ($gradebook->settings['pass_percent'] ?? 50) ? 'passed' : ($max > 0 ? 'failed' : 'not_evaluated'), 'status' => 'draft', 'metadata' => ['scheme' => $gradebook->grading_scheme]]
        );
    }

    public function itemContribution(GradeItem $item, float $score): array
    {
        $max = max((float) $item->max_score, 0.01);
        if ($item->formula) {
            $score = $this->evaluateFormula($item->formula, ['score' => $score, 'max' => $max]);
        }
        if ($item->weight !== null) {
            return [($score / $max) * (float) $item->weight, (float) $item->weight];
        }
        return [$score, $max];
    }

    public function evaluateFormula(string $formula, array $vars): float
    {
        return $this->evaluateNamedFormula($formula, $vars);
    }

    public function evaluateNamedFormula(string $formula, array $vars): float
    {
        $expr = preg_replace_callback('/\b[a-zA-Z_][a-zA-Z0-9_]*\b/', fn ($m) => array_key_exists($m[0], $vars) ? (string) $vars[$m[0]] : '0', $formula);
        if (! preg_match('/^[0-9\.\+\-\*\/\(\) ]+$/', $expr ?? '')) throw new \InvalidArgumentException('Công thức điểm không hợp lệ.');
        return round((float) eval('return '.$expr.';'), 4);
    }

    private function letter(float $percent): string
    {
        return match (true) { $percent >= 90 => 'A', $percent >= 80 => 'B', $percent >= 65 => 'C', $percent >= 50 => 'D', default => 'F' };
    }

    private function variableName(string $label): string
    {
        $value = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $label));
        return trim($value, '_') ?: 'item';
    }
}
