<?php

namespace Tests\Feature;

use App\Models\AiFlashcard;
use App\Models\AiGeneratedQuiz;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Embedding;
use Database\Seeders\CoreSeeder;
use Database\Seeders\CourseStudioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiLearningPlatformFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->seed(CourseStudioSeeder::class);
    }

    public function test_ingest_document_chunks_and_embeds_content(): void
    {
        $response = $this->withHeaders($this->adminHeaders())->postJson('/api/v1/ai/ingest', $this->payload());

        $response->assertCreated()->assertJsonPath('status', 'ready');
        $this->assertDatabaseHas('documents', ['title' => 'AI Learning Test PDF', 'source_type' => 'pdf']);
        $this->assertGreaterThanOrEqual(1, DocumentChunk::query()->count());
        $this->assertGreaterThanOrEqual(1, Embedding::query()->count());
    }

    public function test_ai_tutor_answers_question_from_vector_store(): void
    {
        $documentId = $this->ingestDocumentId();

        $response = $this->withHeaders($this->adminHeaders())->postJson('/api/v1/ai/ask', [
            'document_id' => $documentId,
            'assistant_type' => 'tutor',
            'question' => 'AI Quiz Generator tạo được những dạng câu hỏi nào?',
        ]);

        $response->assertOk()
            ->assertJsonPath('assistant_type', 'tutor')
            ->assertJsonStructure(['answer', 'citations']);
        $this->assertStringContainsString('AI Tutor', $response->json('answer'));
    }

    public function test_workspace_returns_study_companion_contract(): void
    {
        $this->ingestDocumentId();

        $response = $this->withHeaders($this->adminHeaders())->getJson('/api/v1/ai/workspace');

        $response->assertOk()
            ->assertJsonPath('assistant.name', 'Era AI Study Companion')
            ->assertJsonStructure([
                'assistant' => ['name', 'prompt_version', 'principles', 'system_prompt'],
                'learner' => ['id', 'name', 'email'],
                'context' => ['scope', 'selected_document_id', 'selected_course_id', 'selection_reason'],
                'courses',
                'documents' => [['id', 'title', 'source_type', 'chunks_count']],
                'tools' => [['key', 'label', 'assistant_type', 'endpoint']],
                'suggestions' => [['label', 'assistant_type', 'tool', 'prompt']],
                'study_plan' => ['headline', 'today', 'this_week'],
                'related' => ['scope', 'quizzes', 'flashcards', 'conversations'],
                'knowledge' => ['documents_ready', 'scope_documents', 'chunks_ready', 'scope_chunks', 'questions_asked', 'quizzes_generated', 'flashcards_generated'],
                'safety',
            ]);

        $this->assertGreaterThanOrEqual(5, count($response->json('tools')));
        $this->assertGreaterThanOrEqual(3, count($response->json('suggestions')));
    }

    public function test_generate_quiz_from_ingested_document(): void
    {
        $documentId = $this->ingestDocumentId();

        $response = $this->withHeaders($this->adminHeaders())->postJson('/api/v1/ai/quizzes', [
            'document_id' => $documentId,
            'types' => ['mcq', 'essay', 'fill_blank', 'matching'],
        ]);

        $response->assertCreated()->assertJsonPath('quiz_type', 'mixed');
        $this->assertCount(4, $response->json('questions'));
        $this->assertSame(1, AiGeneratedQuiz::query()->count());
    }

    public function test_generate_flashcards_from_ingested_document(): void
    {
        $documentId = $this->ingestDocumentId();

        $response = $this->withHeaders($this->adminHeaders())->postJson('/api/v1/ai/flashcards', [
            'document_id' => $documentId,
            'count' => 3,
        ]);

        $response->assertCreated();
        $this->assertCount(3, $response->json());
        $this->assertSame(3, AiFlashcard::query()->count());
    }

    private function ingestDocumentId(): int
    {
        $response = $this->withHeaders($this->adminHeaders())->postJson('/api/v1/ai/ingest', $this->payload());

        return (int) $response->json('id');
    }

    private function payload(): array
    {
        return [
            'source_type' => 'pdf',
            'title' => 'AI Learning Test PDF',
            'content' => implode(' ', [
                'AI Tutor giải thích lại bài học và tạo ví dụ thực tế cho người học.',
                'AI Course Assistant hỗ trợ giáo viên thiết kế hoạt động học tập.',
                'AI Quiz Generator sinh MCQ, Essay, Fill Blank và Matching từ học liệu.',
                'AI Flashcard sinh Front, Back và Difficulty để ôn tập.',
                'AI Learning Coach theo dõi tiến độ, điểm và thời gian học.',
                'AI Outcome Analyzer phân tích CLO, PLO và Competency.',
            ]),
        ];
    }

    private function adminHeaders(): array
    {
        return ['X-Tenant-Code' => 'VABIS', 'X-Demo-User-Email' => 'admin.lms@vabis.edu.vn'];
    }
}
