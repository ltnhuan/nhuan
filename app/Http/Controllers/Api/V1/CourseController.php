<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseComponent;
use App\Models\CourseSection;
use App\Models\ContentRepositoryItem;
use App\Models\Exam;
use App\Models\Assignment;
use App\Models\LearningOutcome;
use App\Services\ActivityRegistryService;
use App\Services\ApprovalWorkflowService;
use App\Services\CourseStructureService;
use App\Services\CourseStudioService;
use App\Services\TenantContext;
use App\Support\ApiResponse;
use App\Support\ApiPagination;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CourseController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext)
    {
        return Course::query()
            ->where('tenant_id', $tenantContext->id())
            ->with(['category:id,name', 'academicUnit:id,name', 'owner:id,full_name'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = (string) $request->string('search');
                $query->where(fn ($query) => $query->where('code', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%"));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('course_type'), fn ($query) => $query->where('course_type', $request->input('course_type')))
            ->when($request->filled('level'), fn ($query) => $query->where('level', $request->input('level')))
            ->when($request->filled('academic_unit_id'), fn ($query) => $query->where('academic_unit_id', $request->input('academic_unit_id')))
            ->latest('updated_at')
            ->paginate(ApiPagination::perPage($request, 25));
    }

    public function categories(TenantContext $tenantContext)
    {
        return CourseCategory::query()->where('tenant_id', $tenantContext->id())->with('children')->whereNull('parent_id')->orderBy('sort_order')->get();
    }

    public function activityTypes(ActivityRegistryService $activityRegistry)
    {
        return $activityRegistry->allEnabled();
    }

    public function store(Request $request, CourseStudioService $studio, TenantContext $tenantContext)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:255'],
            'short_description' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer'],
            'academic_unit_id' => ['nullable', 'integer'],
            'level' => ['required', 'string'],
            'course_type' => ['required', 'string'],
            'visibility' => ['nullable', 'string'],
            'language' => ['nullable', 'string'],
            'thumbnail_url' => ['nullable', 'string'],
            'estimated_hours' => ['nullable', 'numeric'],
            'settings' => ['nullable', 'array'],
        ]);

        return response()->json($studio->createCourse($data + ['tenant_id' => $tenantContext->id(), 'owner_id' => $request->user()?->id ?? 1]), 201);
    }

    public function show(Course $course)
    {
        return $course->load(['category', 'academicUnit', 'owner', 'versions.creator', 'publishLogs']);
    }

    public function update(Request $request, Course $course)
    {
        $course->fill($request->all())->save();

        return $course->fresh(['category', 'academicUnit', 'owner']);
    }

    public function clone(Course $course, CourseStudioService $studio, Request $request)
    {
        return ApiResponse::success($studio->cloneCourse($course, $request->user()?->id ?? 1), 'Đã clone khóa học.');
    }

    public function submitReview(Course $course, CourseStudioService $studio, Request $request)
    {
        return ApiResponse::success($studio->submitReview($course, $request->user()?->id ?? 1, $request->input('note')), 'Đã gửi khóa học sang review.');
    }

    public function approve(Course $course, CourseStudioService $studio, Request $request)
    {
        return ApiResponse::success($studio->approveCourse($course, $request->user()?->id ?? 1, $request->input('note')), 'Đã duyệt khóa học.');
    }

    public function publish(Course $course, CourseStudioService $studio, Request $request)
    {
        return ApiResponse::success($studio->publishCourse($course, $request->user()?->id ?? 1, $request->input('note')), 'Đã publish khóa học.');
    }

    public function archive(Course $course, CourseStudioService $studio, Request $request)
    {
        return ApiResponse::success($studio->archiveCourse($course, $request->user()?->id ?? 1, $request->input('note')), 'Đã archive khóa học.');
    }

    public function validateForPublish(Course $course, CourseStudioService $studio)
    {
        $errors = $studio->validateCourseStructure($course);

        return ['valid' => $errors === [], 'errors' => $errors];
    }

    public function studio(Course $course, CourseStructureService $structure)
    {
        return app(CourseStudioService::class)->getStudioPayload($course);
    }

    public function publishChecklist(Course $course, CourseStudioService $studio)
    {
        return ApiResponse::success($studio->validatePublishChecklist($course), 'Publish checklist completed.');
    }

    public function storeSection(Request $request, Course $course, CourseStructureService $structure)
    {
        return response()->json($structure->createSection($course, $request->validate([
            'parent_id' => ['nullable', 'integer'],
            'type' => ['required', 'in:section,subsection,unit'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:draft,published,locked'],
            'release_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'settings' => ['nullable', 'array'],
        ])), 201);
    }

    public function updateSection(Request $request, CourseSection $section, CourseStructureService $structure)
    {
        return $structure->updateSection($section, $request->all());
    }

    public function deleteSection(CourseSection $section)
    {
        $section->delete();

        return response()->noContent();
    }

    public function reorderSections(Request $request, CourseStructureService $structure)
    {
        $structure->reorderSections($request->validate(['items' => ['required', 'array'], 'items.*.id' => ['required', 'integer'], 'items.*.parent_id' => ['nullable', 'integer'], 'items.*.sort_order' => ['required', 'integer']])['items']);

        return ['status' => 'ok'];
    }

    public function storeComponent(Request $request, CourseStructureService $structure)
    {
        return response()->json(app(CourseStudioService::class)->createComponent($request->validate([
            'section_id' => ['required', 'integer'],
            'component_type' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'content_id' => ['nullable', 'integer'],
            'config' => ['nullable', 'array'],
            'sort_order' => ['nullable', 'integer'],
            'required' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:draft,published,locked'],
        ])), 201);
    }

    public function updateComponent(Request $request, CourseComponent $component, CourseStructureService $structure)
    {
        return app(CourseStudioService::class)->updateComponent($component, $request->all());
    }

    public function deleteComponent(CourseComponent $component)
    {
        app(CourseStudioService::class)->deleteComponent($component);

        return response()->noContent();
    }

    public function duplicateComponent(CourseComponent $component)
    {
        return ApiResponse::success(app(CourseStudioService::class)->duplicateComponent($component), 'Đã nhân bản component.');
    }

    public function reorderComponents(Request $request, CourseStructureService $structure)
    {
        $structure->reorderComponents($request->validate(['items' => ['required', 'array'], 'items.*.id' => ['required', 'integer'], 'items.*.section_id' => ['nullable', 'integer'], 'items.*.sort_order' => ['required', 'integer']])['items']);

        return ['status' => 'ok'];
    }

    public function repositorySelectOptions(Request $request, TenantContext $tenantContext)
    {
        return ContentRepositoryItem::query()
            ->where('tenant_id', $tenantContext->id())
            ->where('item_type', '!=', 'folder')
            ->when($request->filled('types'), fn ($query) => $query->whereIn('item_type', explode(',', (string) $request->query('types'))))
            ->orderBy('title')
            ->limit(50)
            ->get(['id', 'title', 'item_type', 'mime_type', 'status']);
    }

    public function examSelectOptions(Request $request, TenantContext $tenantContext)
    {
        return Exam::query()
            ->where('tenant_id', $tenantContext->id())
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['id', 'title', 'exam_type', 'status', 'duration_minutes', 'pass_score', 'total_score']);
    }

    public function assignmentSelectOptions(Request $request, TenantContext $tenantContext)
    {
        return Assignment::query()
            ->where('tenant_id', $tenantContext->id())
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['id', 'title', 'status', 'due_at', 'max_score', 'pass_score']);
    }

    public function learningOutcomeSelectOptions(Request $request, TenantContext $tenantContext)
    {
        return LearningOutcome::query()
            ->where('tenant_id', $tenantContext->id())
            ->orderBy('code')
            ->limit(100)
            ->get(['id', 'code', 'title', 'outcome_type', 'status']);
    }
}
