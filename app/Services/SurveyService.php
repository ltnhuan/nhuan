<?php

namespace App\Services;

use App\Models\SurveyAnswer;
use App\Models\SurveyCampaign;
use App\Models\SurveyEvidenceFile;
use App\Models\SurveyForm;
use App\Models\SurveyImprovement;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SurveyService
{
    public const QUESTION_TYPES = ['text','rating','matrix','mcq','multi_select','nps'];
    public const TARGET_SCOPES = ['course','class','academic_unit','institution'];

    public function createForm(array $data): SurveyForm
    {
        $questions = $data['questions'] ?? [];
        unset($data['questions']);
        $form = SurveyForm::query()->create($data + ['survey_type' => 'general', 'status' => 'draft', 'settings' => []]);
        $this->syncQuestions($form, $questions);
        return $form->fresh(['questions']);
    }

    public function updateForm(SurveyForm $form, array $data): SurveyForm
    {
        $questions = $data['questions'] ?? null;
        unset($data['id'], $data['tenant_id'], $data['created_by'], $data['questions']);
        $form->fill($data)->save();
        if (is_array($questions)) {
            $this->syncQuestions($form, $questions);
        }
        return $form->fresh(['questions']);
    }

    public function syncQuestions(SurveyForm $form, array $questions): void
    {
        $seen = [];
        foreach (array_values($questions) as $index => $question) {
            $type = $question['question_type'] ?? $question['type'] ?? 'rating';
            if (! in_array($type, self::QUESTION_TYPES, true)) {
                throw new \InvalidArgumentException("Unsupported question type {$type}.");
            }

            $code = $question['code'] ?? Str::slug(Str::limit($question['prompt'] ?? 'q'.($index + 1), 32, ''));
            $model = SurveyQuestion::query()->updateOrCreate(
                ['tenant_id' => $form->tenant_id, 'survey_form_id' => $form->id, 'code' => $code],
                [
                    'question_type' => $type,
                    'prompt' => $question['prompt'] ?? $question['title'] ?? $code,
                    'help_text' => $question['help_text'] ?? null,
                    'required' => (bool) ($question['required'] ?? false),
                    'sort_order' => $question['sort_order'] ?? ($index + 1),
                    'options' => $question['options'] ?? null,
                    'matrix_rows' => $question['matrix_rows'] ?? $question['rows'] ?? null,
                    'matrix_columns' => $question['matrix_columns'] ?? $question['columns'] ?? null,
                    'scoring' => $question['scoring'] ?? null,
                ]
            );
            $seen[] = $model->id;
        }

        if ($questions !== []) {
            SurveyQuestion::query()->where('survey_form_id', $form->id)->whereNotIn('id', $seen)->delete();
        }
    }

    public function createCampaign(array $data): SurveyCampaign
    {
        $scope = $data['target_scope'] ?? 'course';
        if (! in_array($scope, self::TARGET_SCOPES, true)) {
            throw new \InvalidArgumentException("Unsupported survey target scope {$scope}.");
        }

        return SurveyCampaign::query()->create($data + ['status' => 'draft', 'channels' => ['email','lms_notification'], 'settings' => []]);
    }

    public function submitResponse(SurveyCampaign $campaign, array $payload, ?int $respondentId = null): SurveyResponse
    {
        $campaign->load('form.questions');
        $answers = collect($payload['answers'] ?? []);
        $isAnonymous = $campaign->is_anonymous && ! ($payload['identified'] ?? false);

        return DB::transaction(function () use ($campaign, $answers, $payload, $respondentId, $isAnonymous) {
            $response = SurveyResponse::query()->create([
                'tenant_id' => $campaign->tenant_id,
                'survey_campaign_id' => $campaign->id,
                'survey_form_id' => $campaign->survey_form_id,
                'respondent_id' => $isAnonymous ? null : $respondentId,
                'respondent_hash' => $isAnonymous && $respondentId ? hash('sha256', $campaign->id.'|'.$respondentId) : null,
                'is_anonymous' => $isAnonymous,
                'status' => 'submitted',
                'submitted_at' => now(),
                'metadata' => $payload['metadata'] ?? [],
            ]);

            $scores = [];
            $nps = null;
            foreach ($campaign->form->questions as $question) {
                $value = $answers->firstWhere('question_id', $question->id)['value'] ?? $answers->firstWhere('code', $question->code)['value'] ?? null;
                if ($question->required && $value === null) {
                    throw new \InvalidArgumentException("Question {$question->code} is required.");
                }
                if ($value === null) continue;

                $score = $this->scoreAnswer($question, $value);
                if ($score !== null) $scores[] = $score;
                if ($question->question_type === 'nps') $nps = (int) $score;

                SurveyAnswer::query()->create([
                    'tenant_id' => $campaign->tenant_id,
                    'survey_response_id' => $response->id,
                    'survey_question_id' => $question->id,
                    'question_type' => $question->question_type,
                    'text_answer' => is_scalar($value) && $question->question_type === 'text' ? (string) $value : null,
                    'numeric_answer' => is_numeric($value) ? (float) $value : null,
                    'json_answer' => is_array($value) ? $value : null,
                    'score' => $score,
                ]);
            }

            $response->forceFill([
                'average_score' => $scores ? round(array_sum($scores) / count($scores), 2) : null,
                'nps_score' => $nps,
            ])->save();

            return $response->fresh(['answers']);
        });
    }

    public function analytics(int $tenantId, array $filters = []): array
    {
        $responses = SurveyResponse::query()
            ->where('tenant_id', $tenantId)
            ->when($filters['campaign_id'] ?? null, fn ($q, $id) => $q->where('survey_campaign_id', $id))
            ->when($filters['survey_type'] ?? null, fn ($q, $type) => $q->whereHas('campaign.form', fn ($fq) => $fq->where('survey_type', $type)))
            ->get();

        $campaigns = SurveyCampaign::query()->where('tenant_id', $tenantId)->with('form')->get();
        $answers = SurveyAnswer::query()
            ->where('survey_answers.tenant_id', $tenantId)
            ->join('survey_questions', 'survey_questions.id', '=', 'survey_answers.survey_question_id')
            ->select('survey_answers.*', 'survey_questions.code as question_code', 'survey_questions.prompt')
            ->get();

        return [
            'summary' => [
                'campaigns' => $campaigns->count(),
                'responses' => $responses->count(),
                'average_score' => round((float) $responses->avg('average_score'), 2),
                'nps' => $this->nps($responses->pluck('nps_score')->filter()),
                'anonymous_rate' => $responses->count() ? round($responses->where('is_anonymous', true)->count() * 100 / $responses->count(), 2) : 0,
            ],
            'heatmap' => $this->heatmap($answers),
            'trend' => $this->trend($responses),
            'dashboards' => $this->roleDashboards($tenantId),
        ];
    }

    public function createImprovement(array $data): SurveyImprovement
    {
        return SurveyImprovement::query()->create($data + ['status' => 'open', 'priority' => 'medium', 'metrics' => []]);
    }

    public function createEvidence(array $data): SurveyEvidenceFile
    {
        return SurveyEvidenceFile::query()->create($data + ['evidence_type' => 'survey_report', 'metadata' => []]);
    }

    public function export(SurveyCampaign $campaign, string $format): array
    {
        $campaign->load('form.questions', 'responses.answers');
        if ($format === 'pdf') {
            $lines = [
                'Survey report: '.$campaign->title,
                'Responses: '.$campaign->responses->count(),
                'Average score: '.round((float) $campaign->responses->avg('average_score'), 2),
                'NPS: '.$this->nps($campaign->responses->pluck('nps_score')->filter()),
            ];
            return ['content' => $this->pdf($lines), 'mime' => 'application/pdf', 'filename' => $campaign->code.'-survey-report.pdf'];
        }

        $headers = $campaign->form->questions->pluck('code')->all();
        $rows = ['response_id,submitted_at,'.implode(',', $headers)];
        foreach ($campaign->responses as $response) {
            $answers = $response->answers->keyBy('survey_question_id');
            $values = $campaign->form->questions->map(function ($question) use ($answers) {
                $answer = $answers->get($question->id);
                $value = $answer?->text_answer ?? $answer?->numeric_answer ?? json_encode($answer?->json_answer, JSON_UNESCAPED_UNICODE);
                return '"'.str_replace('"', '""', (string) $value).'"';
            })->all();
            $rows[] = $response->id.','.$response->submitted_at?->toDateTimeString().','.implode(',', $values);
        }

        return ['content' => implode("\n", $rows), 'mime' => 'text/csv', 'filename' => $campaign->code.'-responses.csv'];
    }

    private function pdf(array $lines): string
    {
        $content = "BT\n/F1 16 Tf\n72 730 Td\n";
        foreach ($lines as $index => $line) {
            $text = preg_replace('/[^\x20-\x7E]/', '', $line);
            $content .= ($index === 0 ? '' : "0 -28 Td\n").'('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text).") Tj\n";
        }
        $content .= "ET";

        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >> endobj',
            '4 0 obj << /Length '.strlen($content).' >> stream'."\n".$content."\n".'endstream endobj',
            '5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }
        return $pdf.'trailer << /Size '.(count($objects) + 1).' /Root 1 0 R >>'."\nstartxref\n{$xref}\n%%EOF";
    }

    private function scoreAnswer(SurveyQuestion $question, mixed $value): ?float
    {
        return match ($question->question_type) {
            'rating', 'nps' => is_numeric($value) ? (float) $value : null,
            'matrix' => is_array($value) ? round(collect($value)->flatten()->filter(fn ($v) => is_numeric($v))->avg() ?: 0, 2) : null,
            'mcq' => is_numeric($value) ? (float) $value : null,
            default => null,
        };
    }

    private function nps(Collection $scores): int
    {
        if ($scores->isEmpty()) return 0;
        $promoters = $scores->filter(fn ($score) => $score >= 9)->count();
        $detractors = $scores->filter(fn ($score) => $score <= 6)->count();
        return (int) round((($promoters - $detractors) / $scores->count()) * 100);
    }

    private function heatmap(Collection $answers): array
    {
        return $answers->whereNotNull('score')->groupBy('question_code')->map(fn ($items, $code) => [
            'question' => $code,
            'label' => $items->first()->prompt,
            'average' => round((float) $items->avg('score'), 2),
            'count' => $items->count(),
        ])->values()->all();
    }

    private function trend(Collection $responses): array
    {
        return $responses->groupBy(fn ($response) => $response->submitted_at?->format('Y-m-d') ?? $response->created_at?->format('Y-m-d'))->map(fn ($items, $date) => [
            'date' => $date,
            'average_score' => round((float) $items->avg('average_score'), 2),
            'responses' => $items->count(),
        ])->values()->all();
    }

    private function roleDashboards(int $tenantId): array
    {
        $open = SurveyImprovement::query()->where('tenant_id', $tenantId)->whereIn('status', ['open','in_progress'])->count();
        $evidence = SurveyEvidenceFile::query()->where('tenant_id', $tenantId)->count();
        return [
            'bgh' => ['open_improvements' => $open, 'evidence_files' => $evidence],
            'khoa' => ['focus' => 'academic_unit', 'open_improvements' => $open],
            'dao_tao' => ['focus' => 'course_quality', 'evidence_files' => $evidence],
            'giang_vien' => ['focus' => 'teacher_feedback', 'open_improvements' => $open],
        ];
    }
}
