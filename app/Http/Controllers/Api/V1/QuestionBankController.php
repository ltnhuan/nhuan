<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ExamBlueprint;
use App\Models\LearningOutcome;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionCategory;
use App\Models\QuestionImportJob;
use App\Services\OutcomeMappingService;
use App\Services\QuestionBankService;
use App\Services\QuestionImportService;
use App\Services\QuestionService;
use App\Services\RandomExamEngine;
use App\Services\TenantContext;
use App\Support\ApiPagination;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class QuestionBankController extends Controller
{
    public function banks(Request $request, TenantContext $tenant)
    {
        return QuestionBank::query()->where('tenant_id', $tenant->id())
            ->withCount('questions')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('visibility'), fn ($q) => $q->where('visibility', $request->input('visibility')))
            ->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->integer('course_id')))
            ->latest('updated_at')->paginate(ApiPagination::perPage($request, 25));
    }

    public function storeBank(Request $request, TenantContext $tenant, QuestionBankService $service)
    {
        return response()->json($service->createBank($request->validate([
            'academic_unit_id' => ['nullable', 'integer'], 'course_id' => ['nullable', 'integer'],
            'code' => ['required', 'string'], 'name' => ['required', 'string'], 'description' => ['nullable', 'string'],
            'visibility' => ['nullable', 'string'], 'settings' => ['nullable', 'array'],
        ]) + ['tenant_id' => $tenant->id(), 'owner_id' => $request->user()?->id ?? 1]), 201);
    }

    public function showBank(QuestionBank $bank)
    {
        return $bank->loadCount('questions')->load('categories.children');
    }

    public function updateBank(Request $request, QuestionBank $bank, QuestionBankService $service)
    {
        return $service->updateBank($bank, $request->all());
    }

    public function cloneBank(Request $request, QuestionBank $bank, QuestionBankService $service)
    {
        return $service->cloneBank($bank, $request->user()?->id ?? 1);
    }

    public function submitBank(QuestionBank $bank, QuestionBankService $service)
    {
        return $service->submitReview($bank);
    }

    public function approveBank(QuestionBank $bank, QuestionBankService $service)
    {
        return $service->approveBank($bank);
    }

    public function categories(Request $request, TenantContext $tenant)
    {
        return QuestionCategory::query()->where('tenant_id', $tenant->id())
            ->when($request->filled('question_bank_id'), fn ($q) => $q->where('question_bank_id', $request->integer('question_bank_id')))
            ->with('children')->whereNull('parent_id')->orderBy('sort_order')->get();
    }

    public function storeCategory(Request $request, TenantContext $tenant)
    {
        return response()->json(QuestionCategory::query()->create($request->validate([
            'question_bank_id' => ['required', 'integer'], 'parent_id' => ['nullable', 'integer'],
            'code' => ['required', 'string'], 'name' => ['required', 'string'], 'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'], 'metadata' => ['nullable', 'array'],
        ]) + ['tenant_id' => $tenant->id()]), 201);
    }

    public function updateCategory(Request $request, QuestionCategory $category)
    {
        $category->fill($request->all())->save();
        return $category;
    }

    public function deleteCategory(QuestionCategory $category)
    {
        $category->delete();
        return response()->noContent();
    }

    public function questions(Request $request, TenantContext $tenant)
    {
        return Question::query()->where('tenant_id', $tenant->id())
            ->when($request->filled('question_bank_id'), fn ($q) => $q->where('question_bank_id', $request->integer('question_bank_id')))
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('question_type'), fn ($q) => $q->where('question_type', $request->input('question_type')))
            ->when($request->filled('difficulty'), fn ($q) => $q->where('difficulty', $request->input('difficulty')))
            ->when($request->filled('bloom_level'), fn ($q) => $q->where('bloom_level', $request->input('bloom_level')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest('updated_at')->paginate(ApiPagination::perPage($request, 30));
    }

    public function storeQuestion(Request $request, TenantContext $tenant, QuestionService $service)
    {
        return response()->json($service->createQuestion($request->all() + ['tenant_id' => $tenant->id(), 'owner_id' => $request->user()?->id ?? 1]), 201);
    }

    public function showQuestion(Question $question)
    {
        return $question->load(['options', 'matchingPairs', 'fillBlankAnswers', 'outcomes', 'versions']);
    }

    public function updateQuestion(Request $request, Question $question, QuestionService $service)
    {
        return $service->updateQuestion($question, $request->all() + ['updated_by' => $request->user()?->id ?? 1]);
    }

    public function cloneQuestion(Request $request, Question $question, QuestionService $service)
    {
        return $service->cloneQuestion($question, $request->user()?->id ?? 1);
    }

    public function approveQuestion(Request $request, Question $question, QuestionService $service)
    {
        return $service->approveQuestion($question, $request->user()?->id ?? 1);
    }

    public function publishQuestion(Question $question, QuestionService $service)
    {
        return $service->publishQuestion($question);
    }

    public function archiveQuestion(Question $question, QuestionService $service)
    {
        return $service->archiveQuestion($question);
    }

    public function outcomes(Request $request, TenantContext $tenant)
    {
        return LearningOutcome::query()->where('tenant_id', $tenant->id())->orderBy('code')->paginate(ApiPagination::perPage($request, 50));
    }

    public function storeOutcome(Request $request, TenantContext $tenant)
    {
        return response()->json(LearningOutcome::query()->create($request->all() + ['tenant_id' => $tenant->id()]), 201);
    }

    public function mapOutcomes(Request $request, Question $question, OutcomeMappingService $mapping)
    {
        $mapping->mapQuestionToCloPlo($question, $request->input('outcomes', []));
        return $question->fresh('outcomes');
    }

    public function coverage(Request $request, TenantContext $tenant, OutcomeMappingService $mapping)
    {
        return $mapping->getCoverageMatrix((int) $tenant->id(), $request->integer('question_bank_id') ?: null);
    }

    public function import(Request $request, TenantContext $tenant, QuestionImportService $imports)
    {
        $request->validate(['file' => ['required', 'file'], 'format' => ['required', 'in:xlsx,csv,gift,qti,json']]);
        return response()->json($imports->createImportJob((int) $tenant->id(), $request->file('file'), $request->input('format'), $request->user()?->id ?? 1), 201);
    }

    public function importShow(QuestionImportJob $job)
    {
        return $job;
    }

    public function blueprints(Request $request, TenantContext $tenant)
    {
        return ExamBlueprint::query()->where('tenant_id', $tenant->id())->latest('updated_at')->paginate(ApiPagination::perPage($request, 25));
    }

    public function storeBlueprint(Request $request, TenantContext $tenant)
    {
        return response()->json(ExamBlueprint::query()->create($request->all() + ['tenant_id' => $tenant->id(), 'created_by' => $request->user()?->id ?? 1]), 201);
    }

    public function updateBlueprint(Request $request, ExamBlueprint $blueprint)
    {
        $blueprint->fill($request->all())->save();
        return $blueprint;
    }

    public function generatePreview(ExamBlueprint $blueprint, RandomExamEngine $engine)
    {
        return $engine->generateFromBlueprint($blueprint);
    }
}
