<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\AiFlashcard;
use App\Models\AiGeneratedQuiz;
use App\Models\Course;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Embedding;
use App\Models\Enrollment;
use App\Models\LearningOutcome;
use App\Models\LmsUser;
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

    public function workspace(int $tenantId, ?int $userId = null, ?int $courseId = null, ?int $documentId = null): array
    {
        $relevantCourseIds = $this->relevantCourseIds($tenantId, $userId, $courseId, $documentId);
        $curatedTitles = $this->curatedDocumentTitles();

        $documents = Document::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'ready')
            ->with(['course:id,code,title'])
            ->withCount('chunks')
            ->where(function ($query) use ($relevantCourseIds, $curatedTitles) {
                $query->whereIn('title', $curatedTitles)
                    ->orWhereNull('course_id');

                if ($relevantCourseIds->isNotEmpty()) {
                    $query->orWhereIn('course_id', $relevantCourseIds->all());
                }
            })
            ->latest('updated_at')
            ->limit(40)
            ->get()
            ->sortBy(fn (Document $document) => $this->documentRank($document, $documentId, $courseId, $relevantCourseIds))
            ->take(16)
            ->values();

        if ($documents->isEmpty()) {
            $documents = Document::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ready')
                ->with(['course:id,code,title'])
                ->withCount('chunks')
                ->latest('updated_at')
                ->limit(16)
                ->get();
        }

        $progress = UserCourseProgress::query()
            ->where('tenant_id', $tenantId)
            ->with(['course:id,code,title'])
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when($courseId, fn ($query) => $query->where('course_id', $courseId))
            ->latest('last_accessed_at')
            ->latest('updated_at')
            ->limit(5)
            ->get();

        $learner = $userId
            ? LmsUser::query()
                ->select(['id', 'full_name', 'email', 'code', 'metadata'])
                ->where('tenant_id', $tenantId)
                ->find($userId)
            : null;

        $coach = $this->coach([
            'tenant_id' => $tenantId,
            'course_id' => $courseId,
            'user_id' => $userId,
        ]);

        $analytics = $this->analytics($tenantId);
        $selectedDocument = $documentId
            ? $documents->firstWhere('id', $documentId) ?? Document::query()
                ->where('tenant_id', $tenantId)
                ->with(['course:id,code,title'])
                ->withCount('chunks')
                ->find($documentId)
            : $documents->first();

        if ($selectedDocument && ! $documents->contains('id', $selectedDocument->id)) {
            $documents->prepend($selectedDocument);
        }

        $selectedCourse = $courseId
            ? Course::query()->select(['id', 'code', 'title'])->where('tenant_id', $tenantId)->find($courseId)
            : $selectedDocument?->course;
        $courses = $this->workspaceCourses($tenantId, $userId, $relevantCourseIds);
        $related = $this->relatedAssets($tenantId, $userId, $selectedDocument, $selectedCourse?->id);
        $scopeDocumentIds = $documents->pluck('id')->values();

        return [
            'assistant' => [
                'name' => 'Era AI Study Companion',
                'prompt_version' => 'study-companion-google-style-v1',
                'tone' => 'rõ ràng, ngắn gọn, có nguồn, ưu tiên hành động tiếp theo',
                'principles' => [
                    ['label' => 'Nguồn trước', 'description' => 'Ưu tiên học liệu đã ingest và hiển thị citation khi có dữ liệu truy hồi.'],
                    ['label' => 'Augment, not replace', 'description' => 'Gợi ý cách học, rubric và bước làm; không làm thay bài kiểm tra hay bài nộp.'],
                    ['label' => 'Next best action', 'description' => 'Mỗi câu trả lời kết thúc bằng bước học tiếp theo, quiz hoặc flashcard nên làm.'],
                    ['label' => 'Nói rõ giới hạn', 'description' => 'Nếu thiếu nguồn hoặc ngữ cảnh, trợ lý nói rõ và đề xuất cách bổ sung học liệu.'],
                ],
                'system_prompt' => $this->assistantBlueprint(),
            ],
            'learner' => [
                'id' => $learner?->id,
                'name' => $learner?->full_name ?? 'Sinh viên Demo',
                'email' => $learner?->email,
                'code' => $learner?->code,
                'program' => data_get($learner?->metadata, 'program', 'Chương trình học cá nhân'),
            ],
            'course' => $selectedCourse ? [
                'id' => $selectedCourse->id,
                'code' => $selectedCourse->code,
                'title' => $selectedCourse->title,
            ] : null,
            'courses' => $courses,
            'context' => [
                'scope' => $courseId ? 'course' : ($userId ? 'learner' : 'tenant'),
                'relevant_course_ids' => $relevantCourseIds->values()->all(),
                'selected_document_id' => $selectedDocument?->id,
                'selected_course_id' => $selectedCourse?->id,
                'selection_reason' => $selectedDocument
                    ? 'Nguồn được ưu tiên vì thuộc khóa học/người học hoặc bộ dữ liệu AI demo.'
                    : 'Chưa có nguồn học liệu phù hợp trong workspace.',
            ],
            'documents' => $documents->map(fn (Document $document) => [
                'id' => $document->id,
                'title' => $document->title,
                'source_type' => $document->source_type,
                'course_id' => $document->course_id,
                'course_title' => $document->course?->title,
                'status' => $document->status,
                'chunks_count' => (int) $document->chunks_count,
                'relevance' => $this->documentRelevance($document, $selectedDocument?->id, $relevantCourseIds),
                'updated_at' => $document->updated_at?->toIso8601String(),
            ])->values()->all(),
            'tools' => $this->workspaceTools(),
            'suggestions' => $this->workspaceSuggestions($selectedDocument),
            'study_plan' => $this->workspaceStudyPlan($coach, $progress, $selectedDocument),
            'related' => $related,
            'knowledge' => [
                'documents_ready' => (int) data_get($analytics, 'learning_impact.documents_ready', 0),
                'scope_documents' => $documents->count(),
                'chunks_ready' => DocumentChunk::query()
                    ->where('tenant_id', $tenantId)
                    ->when($courseId, fn ($query) => $query->where('course_id', $courseId))
                    ->count(),
                'scope_chunks' => DocumentChunk::query()
                    ->where('tenant_id', $tenantId)
                    ->whereIn('document_id', $scopeDocumentIds->all())
                    ->count(),
                'questions_asked' => (int) $analytics['ai_usage'],
                'quizzes_generated' => (int) data_get($analytics, 'learning_impact.quizzes_generated', 0),
                'flashcards_generated' => (int) data_get($analytics, 'learning_impact.flashcards_generated', 0),
                'related_quizzes' => count($related['quizzes']),
                'related_flashcards' => count($related['flashcards']),
                'related_conversations' => count($related['conversations']),
                'top_questions' => collect($analytics['top_questions'])->take(5)->values()->all(),
            ],
            'safety' => [
                'Trả lời dựa trên học liệu đang có và nói rõ khi thiếu nguồn.',
                'Không đưa đáp án hoàn chỉnh cho bài kiểm tra đang chấm điểm; chỉ hướng dẫn cách làm và tiêu chí tự kiểm.',
                'Không thu thập thêm dữ liệu cá nhân ngoài nội dung học tập cần thiết.',
            ],
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
            'planner' => 'Kế hoạch học đề xuất:',
            'quiz' => 'Gợi ý luyện quiz:',
            'flashcard' => 'Gợi ý flashcard:',
            'coach' => 'Learning Coach đề xuất:',
            default => 'AI Tutor trả lời:',
        };

        return $lead.' '.Str::limit($context, 650, '')."\n\nCâu hỏi: ".$question."\nÔn tập: xác định khái niệm chính, ví dụ thực tế, rồi tự kiểm tra bằng quiz/flashcard.";
    }

    private function relevantCourseIds(int $tenantId, ?int $userId, ?int $courseId, ?int $documentId): Collection
    {
        $ids = collect();

        if ($courseId) {
            $ids->push($courseId);
        }

        if ($documentId) {
            $documentCourseId = Document::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($documentId)
                ->value('course_id');

            if ($documentCourseId) {
                $ids->push((int) $documentCourseId);
            }
        }

        if ($userId) {
            $ids = $ids->merge(UserCourseProgress::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->latest('last_accessed_at')
                ->latest('updated_at')
                ->limit(8)
                ->pluck('course_id'));

            $ids = $ids->merge(Enrollment::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->whereIn('status', ['active', 'enrolled', 'completed', 'accepted'])
                ->latest('activated_at')
                ->latest('enrolled_at')
                ->latest('updated_at')
                ->limit(8)
                ->pluck('course_id'));
        }

        return $ids->filter()->map(fn ($id) => (int) $id)->unique()->values();
    }

    private function curatedDocumentTitles(): array
    {
        return [
            'AI Study Companion Playbook',
            'HSK1 AI Coach - Kế hoạch 7 ngày',
            'Assignment Rubric - Dự án thực hành xưởng',
            'Video Transcript - Cách học với AI Tutor',
        ];
    }

    private function documentRank(Document $document, ?int $selectedDocumentId, ?int $selectedCourseId, Collection $relevantCourseIds): int
    {
        if ($selectedDocumentId && $document->id === $selectedDocumentId) {
            return 0;
        }

        if ($selectedCourseId && $document->course_id === $selectedCourseId) {
            return 1;
        }

        if (in_array($document->title, $this->curatedDocumentTitles(), true)) {
            return 2;
        }

        if ($document->course_id && $relevantCourseIds->contains((int) $document->course_id)) {
            return 3;
        }

        if ($document->course_id === null) {
            return 4;
        }

        return 9;
    }

    private function documentRelevance(Document $document, ?int $selectedDocumentId, Collection $relevantCourseIds): string
    {
        if ($selectedDocumentId && $document->id === $selectedDocumentId) {
            return 'selected';
        }

        if (in_array($document->title, $this->curatedDocumentTitles(), true)) {
            return 'ai_demo';
        }

        if ($document->course_id && $relevantCourseIds->contains((int) $document->course_id)) {
            return 'course';
        }

        return 'workspace';
    }

    private function workspaceCourses(int $tenantId, ?int $userId, Collection $courseIds): array
    {
        if ($courseIds->isEmpty()) {
            return [];
        }

        $progress = UserCourseProgress::query()
            ->where('tenant_id', $tenantId)
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->whereIn('course_id', $courseIds->all())
            ->get()
            ->keyBy('course_id');

        $enrollments = Enrollment::query()
            ->where('tenant_id', $tenantId)
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->whereIn('course_id', $courseIds->all())
            ->get()
            ->keyBy('course_id');

        return Course::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $courseIds->all())
            ->withCount(['components', 'sections'])
            ->get()
            ->map(fn (Course $course) => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'progress_percent' => (float) ($progress->get($course->id)?->progress_percent ?? $enrollments->get($course->id)?->completion_percent ?? 0),
                'status' => $progress->get($course->id)?->status ?? $enrollments->get($course->id)?->status ?? 'related',
                'documents_count' => Document::query()
                    ->where('tenant_id', $tenantId)
                    ->where('course_id', $course->id)
                    ->where('status', 'ready')
                    ->count(),
                'components_count' => (int) ($course->components_count ?? 0),
                'sections_count' => (int) ($course->sections_count ?? 0),
            ])
            ->sortByDesc('progress_percent')
            ->values()
            ->all();
    }

    private function relatedAssets(int $tenantId, ?int $userId, ?Document $document, ?int $courseId): array
    {
        $documentId = $document?->id;
        $courseIds = collect([$courseId, $document?->course_id])->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $quizzes = AiGeneratedQuiz::query()
            ->where('tenant_id', $tenantId)
            ->when($documentId || $courseIds->isNotEmpty(), function ($query) use ($documentId, $courseIds) {
                $query->where(function ($query) use ($documentId, $courseIds) {
                    if ($documentId) {
                        $query->where('document_id', $documentId);
                    }

                    if ($courseIds->isNotEmpty()) {
                        $method = $documentId ? 'orWhereIn' : 'whereIn';
                        $query->{$method}('course_id', $courseIds->all());
                    }
                });
            })
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (AiGeneratedQuiz $quiz) => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'quiz_type' => $quiz->quiz_type,
                'document_id' => $quiz->document_id,
                'course_id' => $quiz->course_id,
                'questions_count' => count($quiz->questions ?? []),
                'created_at' => $quiz->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $flashcards = AiFlashcard::query()
            ->where('tenant_id', $tenantId)
            ->when($documentId || $courseIds->isNotEmpty(), function ($query) use ($documentId, $courseIds) {
                $query->where(function ($query) use ($documentId, $courseIds) {
                    if ($documentId) {
                        $query->where('document_id', $documentId);
                    }

                    if ($courseIds->isNotEmpty()) {
                        $method = $documentId ? 'orWhereIn' : 'whereIn';
                        $query->{$method}('course_id', $courseIds->all());
                    }
                });
            })
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (AiFlashcard $card) => [
                'id' => $card->id,
                'front' => $card->front,
                'back' => $card->back,
                'difficulty' => $card->difficulty,
                'document_id' => $card->document_id,
                'course_id' => $card->course_id,
                'created_at' => $card->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $conversations = AiConversation::query()
            ->where('tenant_id', $tenantId)
            ->when($userId, fn ($query) => $query->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)->orWhereNull('user_id');
            }))
            ->when($courseIds->isNotEmpty(), fn ($query) => $query->where(function ($query) use ($courseIds) {
                $query->whereIn('course_id', $courseIds->all())->orWhereNull('course_id');
            }))
            ->latest()
            ->limit(8)
            ->get()
            ->filter(function (AiConversation $conversation) use ($documentId) {
                if (! $documentId) {
                    return true;
                }

                return collect($conversation->citations ?? [])->contains(fn ($citation) => (int) ($citation['document_id'] ?? 0) === (int) $documentId)
                    || $conversation->course_id !== null;
            })
            ->take(6)
            ->map(fn (AiConversation $conversation) => [
                'id' => $conversation->id,
                'assistant_type' => $conversation->assistant_type,
                'question' => $conversation->question,
                'answer_preview' => Str::limit(strip_tags($conversation->answer), 140),
                'citations_count' => count($conversation->citations ?? []),
                'created_at' => $conversation->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'scope' => [
                'document_id' => $documentId,
                'document_title' => $document?->title,
                'course_id' => $courseId,
            ],
            'quizzes' => $quizzes,
            'flashcards' => $flashcards,
            'conversations' => $conversations,
        ];
    }

    private function assistantBlueprint(): string
    {
        return implode("\n", [
            'Bạn là Era AI Study Companion cho người học.',
            'Nhiệm vụ: trả lời theo học liệu truy hồi, chỉ ra nguồn/citation khi có, giải thích ngắn gọn, rồi đề xuất hành động tiếp theo.',
            'Phong cách: giống một trợ lý học tập hiện đại - rõ, thân thiện, không lan man, ưu tiên việc người học có thể làm ngay.',
            'Giới hạn: không bịa nguồn, không làm thay bài nộp/bài kiểm tra, không khẳng định khi thiếu dữ liệu.',
            'Cấu trúc trả lời: 1) câu trả lời chính, 2) bằng chứng từ học liệu, 3) bước tiếp theo hoặc câu tự kiểm.',
        ]);
    }

    private function workspaceTools(): array
    {
        return [
            [
                'key' => 'tutor',
                'label' => 'Ask with sources',
                'assistant_type' => 'tutor',
                'endpoint' => '/api/v1/ai/ask',
                'description' => 'Hỏi đáp theo học liệu và trả lời kèm citation.',
            ],
            [
                'key' => 'planner',
                'label' => 'Study planner',
                'assistant_type' => 'planner',
                'endpoint' => '/api/v1/ai/ask',
                'description' => 'Lập kế hoạch học theo ngày, mức tiến độ và deadline.',
            ],
            [
                'key' => 'quiz',
                'label' => 'Quiz maker',
                'assistant_type' => 'quiz',
                'endpoint' => '/api/v1/ai/quizzes',
                'description' => 'Tạo quiz MCQ, tự luận, điền khuyết và ghép đôi.',
            ],
            [
                'key' => 'flashcard',
                'label' => 'Flashcards',
                'assistant_type' => 'flashcard',
                'endpoint' => '/api/v1/ai/flashcards',
                'description' => 'Tạo thẻ ôn tập theo mức dễ, vừa, khó.',
            ],
            [
                'key' => 'assignment',
                'label' => 'Assignment coach',
                'assistant_type' => 'assignment',
                'endpoint' => '/api/v1/ai/ask',
                'description' => 'Phân tích đề, rubric và checklist nộp bài.',
            ],
            [
                'key' => 'video',
                'label' => 'Video coach',
                'assistant_type' => 'video',
                'endpoint' => '/api/v1/ai/ask',
                'description' => 'Hỏi nhanh theo transcript video hoặc live session.',
            ],
        ];
    }

    private function workspaceSuggestions(?Document $document): array
    {
        $source = $document?->title ? ' trong "'.$document->title.'"' : '';

        return [
            [
                'label' => 'Tóm tắt bài đang học',
                'assistant_type' => 'pdf',
                'tool' => 'ask',
                'tone' => 'blue',
                'prompt' => 'Tóm tắt 5 ý quan trọng nhất'.$source.' và cho tôi 3 câu tự kiểm.',
            ],
            [
                'label' => 'Lập kế hoạch 7 ngày',
                'assistant_type' => 'planner',
                'tool' => 'ask',
                'tone' => 'green',
                'prompt' => 'Lập kế hoạch học 7 ngày từ học liệu hiện có, mỗi ngày có mục tiêu, thời lượng và bài kiểm tra nhanh.',
            ],
            [
                'label' => 'Giải thích dễ hiểu',
                'assistant_type' => 'tutor',
                'tool' => 'ask',
                'tone' => 'yellow',
                'prompt' => 'Giải thích nội dung khó nhất'.$source.' theo kiểu bạn học cùng lớp, kèm ví dụ thực tế.',
            ],
            [
                'label' => 'Check rubric bài tập',
                'assistant_type' => 'assignment',
                'tool' => 'ask',
                'tone' => 'red',
                'prompt' => 'Phân tích rubric và tạo checklist tự kiểm trước khi nộp bài.',
            ],
            [
                'label' => 'Tạo quiz luyện tập',
                'assistant_type' => 'quiz',
                'tool' => 'quiz',
                'tone' => 'purple',
                'prompt' => 'Tạo quiz 10 câu từ học liệu đang chọn, có đáp án và giải thích ngắn.',
            ],
        ];
    }

    private function workspaceStudyPlan(array $coach, Collection $progress, ?Document $document): array
    {
        $documentTitle = $document?->title ?? 'học liệu mới nhất';
        $currentCourse = $progress->first()?->course?->title ?? 'khóa học đang học';
        $progressPercent = (float) ($coach['progress_percent'] ?? 0);

        return [
            'headline' => $progressPercent < 70 ? 'Ưu tiên củng cố nền tảng hôm nay' : 'Sẵn sàng luyện quiz tổng hợp',
            'today' => [
                [
                    'time' => '10 phút',
                    'title' => 'Ôn nhanh '.$documentTitle,
                    'action' => 'Ask summary',
                    'prompt' => 'Tóm tắt học liệu đang chọn và chỉ ra phần cần nhớ nhất.',
                ],
                [
                    'time' => '15 phút',
                    'title' => 'Làm quiz kiểm tra nhớ bài',
                    'action' => 'Generate quiz',
                    'prompt' => 'Tạo quiz luyện tập từ học liệu đang chọn.',
                ],
                [
                    'time' => '8 phút',
                    'title' => 'Flashcard điểm yếu',
                    'action' => 'Generate flashcards',
                    'prompt' => 'Tạo flashcard cho các khái niệm dễ nhầm.',
                ],
            ],
            'this_week' => [
                'course' => $currentCourse,
                'target_progress' => min(100, max(40, (int) $progressPercent + 15)),
                'next_lesson' => $coach['next_lesson'] ?? 'Tiếp tục bài học tiếp theo',
                'review' => $coach['review_recommendation'] ?? 'Ôn lại bằng quiz và flashcard',
            ],
        ];
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
