<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Document;
use App\Services\AiDocumentPipelineService;
use App\Services\AiLearningPlatformService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AiLearningController extends Controller
{
    public function ingest(Request $request, AiDocumentPipelineService $pipeline, TenantContext $tenantContext)
    {
        $data = $request->validate([
            'course_id' => ['nullable', 'integer'],
            'content_repository_item_id' => ['nullable', 'integer'],
            'source_type' => ['required', 'in:pdf,ppt,docx,video_transcript,repository,slide,assignment'],
            'title' => ['required', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:255'],
            'storage_path' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        return response()->json($pipeline->ingest($data + ['tenant_id' => $tenantContext->id()]), 201);
    }

    public function documents(Request $request, TenantContext $tenantContext)
    {
        return Document::query()
            ->where('tenant_id', $tenantContext->id())
            ->withCount('chunks')
            ->when($request->filled('course_id'), fn ($query) => $query->where('course_id', $request->integer('course_id')))
            ->when($request->filled('source_type'), fn ($query) => $query->where('source_type', $request->input('source_type')))
            ->latest()
            ->paginate($request->integer('per_page', 25));
    }

    public function ask(Request $request, AiLearningPlatformService $ai, TenantContext $tenantContext)
    {
        $data = $request->validate([
            'course_id' => ['nullable', 'integer'],
            'document_id' => ['nullable', 'integer'],
            'assistant_type' => ['nullable', 'in:tutor,course_assistant,pdf,video,assignment'],
            'question' => ['required', 'string', 'max:2000'],
        ]);

        return response()->json($ai->ask($data + ['tenant_id' => $tenantContext->id(), 'user_id' => $request->user()?->id]));
    }

    public function summary(Request $request, AiLearningPlatformService $ai, TenantContext $tenantContext)
    {
        $data = $request->validate([
            'course_id' => ['nullable', 'integer'],
            'document_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        return $ai->summarize($data + ['tenant_id' => $tenantContext->id()]);
    }

    public function quiz(Request $request, AiLearningPlatformService $ai, TenantContext $tenantContext)
    {
        $data = $request->validate([
            'course_id' => ['nullable', 'integer'],
            'document_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'types' => ['nullable', 'array'],
            'types.*' => ['in:mcq,essay,fill_blank,matching'],
        ]);

        return response()->json($ai->generateQuiz($data + ['tenant_id' => $tenantContext->id()]), 201);
    }

    public function flashcards(Request $request, AiLearningPlatformService $ai, TenantContext $tenantContext)
    {
        $data = $request->validate([
            'course_id' => ['nullable', 'integer'],
            'document_id' => ['nullable', 'integer'],
            'count' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        return response()->json($ai->generateFlashcards($data + ['tenant_id' => $tenantContext->id()])->values(), 201);
    }

    public function coach(Request $request, AiLearningPlatformService $ai, TenantContext $tenantContext)
    {
        return $ai->coach([
            'tenant_id' => $tenantContext->id(),
            'course_id' => $request->integer('course_id') ?: null,
            'user_id' => $request->integer('user_id') ?: $request->user()?->id,
        ]);
    }

    public function outcomes(Request $request, AiLearningPlatformService $ai, TenantContext $tenantContext)
    {
        return $ai->outcomeAnalyzer([
            'tenant_id' => $tenantContext->id(),
            'course_id' => $request->integer('course_id') ?: null,
        ]);
    }

    public function analytics(AiLearningPlatformService $ai, TenantContext $tenantContext)
    {
        return $ai->analytics($tenantContext->id());
    }
}
