<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\AiFlashcard;
use App\Models\AiGeneratedQuiz;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Embedding;
use App\Models\LearningOutcome;
use App\Models\UserCourseProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AiLearningPlatformService
{
    public function __construct(
        private readonly AiEmbeddingService $embedding,
        private readonly AiChatService $chat,
    )
    {
    }

    public function ask(array $data): AiConversation
    {
        $chunks = $this->retrieve($data['tenant_id'], $data['question'], $data['course_id'] ?? null, $data['document_id'] ?? null);
        $citations = $chunks->map(fn ($item) => [
            'document_id' => $item['chunk']->document_id,
            'chunk_id' => $item['chunk']->id,
            'title' => $item['chunk']->document?->title,
            'score' => $item['score'],
        ])->values()->all();

        $context = $chunks->pluck('chunk.content')->implode("\n");
        $assistantType = $data['assistant_type'] ?? 'tutor';
        $answer = $this->chat->answer($data['question'], $context, $assistantType)
            ?? $this->composeAnswer($data['question'], $context, $assistantType);

        return AiConversation::query()->create([
            'tenant_id' => $data['tenant_id'],
            'course_id' => $data['course_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'assistant_type' => $assistantType,
            'question' => $data['question'],
            'answer' => $answer,
            'citations' => $citations,
            'metadata' => [
                'retrieval_count' => $chunks->count(),
                'provider' => config('eralms.ai.provider', 'local'),
                'model' => config('eralms.ai.provider') === 'openrouter'
                    ? config('eralms.ai.openrouter.model')
                    : 'local-rag-template',
            ],
        ]);
    }

    public function summarize(array $data): array
    {
        $chunks = $this->contentChunks($data['tenant_id'], $data['course_id'] ?? null, $data['document_id'] ?? null)->take(6);
        $sentences = $this->sentences($chunks->pluck('content')->implode(' '));
        $keyPoints = array_slice($sentences, 0, 5);

        return [
            'summary' => implode(' ', array_slice($sentences, 0, 3)),
            'key_points' => $keyPoints,
            'mindmap' => [
                'topic' => $data['title'] ?? 'AI Learning Summary',
                'children' => array_map(fn ($point) => ['label' => Str::limit($point, 80, '')], $keyPoints),
            ],
        ];
    }

    public function generateQuiz(array $data): AiGeneratedQuiz
    {
        $sentences = $this->sourceSentences($data);
        $types = $data['types'] ?? ['mcq', 'essay', 'fill_blank', 'matching'];
        $questions = [];

        foreach ($types as $type) {
            $seed = $sentences[count($questions) % max(1, count($sentences))] ?? 'Nội dung học tập trọng tâm';
            $questions[] = $this->question($type, $seed, count($questions) + 1);
        }

        return AiGeneratedQuiz::query()->create([
            'tenant_id' => $data['tenant_id'],
            'course_id' => $data['course_id'] ?? null,
            'document_id' => $data['document_id'] ?? null,
            'quiz_type' => count($types) === 1 ? $types[0] : 'mixed',
            'title' => $data['title'] ?? 'AI generated quiz',
            'questions' => $questions,
            'metadata' => ['source' => 'document_pipeline', 'count' => count($questions)],
        ]);
    }

    public function generateFlashcards(array $data): Collection
    {
        $sentences = array_slice($this->sourceSentences($data), 0, $data['count'] ?? 5);
        $cards = collect();

        foreach ($sentences as $index => $sentence) {
            $front = 'Khái niệm chính #'.($index + 1);
            if (preg_match('/^(.{8,70}?)( là | gồm | giúp | nhằm )/u', $sentence, $match)) {
                $front = trim($match[1]);
            }

            $cards->push(AiFlashcard::query()->create([
                'tenant_id' => $data['tenant_id'],
                'course_id' => $data['course_id'] ?? null,
                'document_id' => $data['document_id'] ?? null,
                'front' => $front,
                'back' => $sentence,
                'difficulty' => ['easy', 'medium', 'hard'][$index % 3],
                'metadata' => ['generator' => 'ai_learning_platform'],
            ]));
        }

        return $cards;
    }

    public function coach(array $data): array
    {
        $progress = UserCourseProgress::query()
            ->where('tenant_id', $data['tenant_id'])
            ->when($data['course_id'] ?? null, fn ($query, $courseId) => $query->where('course_id', $courseId))
            ->when($data['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->latest()
            ->first();

        $completion = (float) ($progress?->progress_percent ?? 0);
        $metadata = $progress?->metadata ?? [];

        return [
            'progress_percent' => $completion,
            'score_snapshot' => $metadata['score'] ?? null,
            'study_time_minutes' => $metadata['study_time_minutes'] ?? 0,
            'next_lesson' => $completion < 50 ? 'Tiếp tục bài nền tảng tiếp theo' : 'Chuyển sang bài ứng dụng hoặc case study',
            'review_recommendation' => $completion < 70 ? 'Ôn lại các chunk có điểm truy hồi cao và làm flashcard khó' : 'Làm quiz tổng hợp để củng cố',
        ];
    }

    public function outcomeAnalyzer(array $data): array
    {
        $outcomes = LearningOutcome::query()
            ->where('tenant_id', $data['tenant_id'])
            ->when($data['course_id'] ?? null, fn ($query, $courseId) => $query->where('course_id', $courseId))
            ->get();

        return [
            'clo' => $outcomes->where('type', 'CLO')->values()->map(fn ($outcome) => $this->outcomeRow($outcome))->all(),
            'plo' => $outcomes->where('type', 'PLO')->values()->map(fn ($outcome) => $this->outcomeRow($outcome))->all(),
            'competency' => $outcomes->whereNotIn('type', ['CLO', 'PLO'])->values()->map(fn ($outcome) => $this->outcomeRow($outcome))->all(),
            'coverage_status' => $outcomes->isEmpty() ? 'no_outcomes_configured' : 'ready',
        ];
    }

    public function analytics(int $tenantId): array
    {
        return [
            'ai_usage' => AiConversation::query()->where('tenant_id', $tenantId)->count(),
            'top_questions' => AiConversation::query()->where('tenant_id', $tenantId)->select('question')->latest()->limit(10)->pluck('question'),
            'learning_impact' => [
                'documents_ready' => Document::query()->where('tenant_id', $tenantId)->where('status', 'ready')->count(),
                'quizzes_generated' => AiGeneratedQuiz::query()->where('tenant_id', $tenantId)->count(),
                'flashcards_generated' => AiFlashcard::query()->where('tenant_id', $tenantId)->count(),
            ],
        ];
    }

    private function retrieve(int $tenantId, string $question, ?int $courseId, ?int $documentId): Collection
    {
        $queryVector = $this->embedding->embed($question);
        $embeddings = Embedding::query()
            ->where('tenant_id', $tenantId)
            ->with('chunk.document')
            ->whereHas('chunk', function ($query) use ($courseId, $documentId) {
                $query->when($courseId, fn ($query) => $query->where('course_id', $courseId))
                    ->when($documentId, fn ($query) => $query->where('document_id', $documentId));
            })
            ->get();

        return $embeddings
            ->map(fn ($row) => ['chunk' => $row->chunk, 'score' => $this->embedding->similarity($queryVector, $row->vector)])
            ->sortByDesc('score')
            ->take(4)
            ->values();
    }

    private function contentChunks(int $tenantId, ?int $courseId, ?int $documentId): Collection
    {
        return DocumentChunk::query()
            ->where('tenant_id', $tenantId)
            ->when($courseId, fn ($query) => $query->where('course_id', $courseId))
            ->when($documentId, fn ($query) => $query->where('document_id', $documentId))
            ->orderBy('document_id')
            ->orderBy('chunk_index')
            ->get();
    }

    private function sourceSentences(array $data): array
    {
        $chunks = $this->contentChunks($data['tenant_id'], $data['course_id'] ?? null, $data['document_id'] ?? null)->take(8);

        return $this->sentences($chunks->pluck('content')->implode(' '));
    }

    private function sentences(string $content): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/u', trim($content), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(array_map(fn ($sentence) => trim($sentence), $sentences), fn ($sentence) => mb_strlen($sentence) > 20));
    }

    private function composeAnswer(string $question, string $context, string $assistantType): string
    {
        if ($context === '') {
            return 'Chưa có học liệu phù hợp trong vector store. Hãy ingest PDF, slide, transcript hoặc repository item trước.';
        }

        $lead = match ($assistantType) {
            'course_assistant' => 'Gợi ý triển khai khóa học:',
            'assignment' => 'Gợi ý xử lý bài tập:',
            'video' => 'Tóm tắt từ transcript/video:',
            default => 'AI Tutor trả lời:',
        };

        return $lead.' '.Str::limit($context, 650, '')."\n\nCâu hỏi: ".$question."\nÔn tập: xác định khái niệm chính, ví dụ thực tế, rồi tự kiểm tra bằng quiz/flashcard.";
    }

    private function question(string $type, string $seed, int $index): array
    {
        return match ($type) {
            'essay' => ['type' => 'essay', 'prompt' => "Phân tích ý nghĩa của nội dung {$index}: {$seed}", 'rubric' => ['accuracy', 'evidence', 'application']],
            'fill_blank' => ['type' => 'fill_blank', 'prompt' => Str::replaceFirst(' ', ' _____ ', $seed), 'answer' => Str::before($seed, ' ')],
            'matching' => ['type' => 'matching', 'pairs' => [['left' => 'Khái niệm '.$index, 'right' => Str::limit($seed, 90, '')]]],
            default => ['type' => 'mcq', 'stem' => "Ý nào phản ánh đúng nội dung {$index}?", 'options' => [$seed, 'Một nhận định không liên quan', 'Một ví dụ sai ngữ cảnh', 'Một kết luận thiếu dữ liệu'], 'answer' => 0],
        };
    }

    private function outcomeRow($outcome): array
    {
        return [
            'id' => $outcome->id,
            'code' => $outcome->code,
            'name' => $outcome->name,
            'achievement' => 0,
            'evidence' => ['documents', 'quizzes', 'gradebook'],
        ];
    }
}
