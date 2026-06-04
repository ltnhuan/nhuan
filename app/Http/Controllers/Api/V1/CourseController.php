<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\CourseSection;
use App\Services\ApprovalWorkflowService;
use App\Services\CourseStudioService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        return Course::query()->where('tenant_id', $request->attributes->get('tenant')?->id)->with('sections.components')->latest()->paginate(25);
    }

    public function store(Request $request, CourseStudioService $studio)
    {
        return $studio->createCourse($request->all() + ['tenant_id' => $request->attributes->get('tenant')->id, 'owner_id' => $request->user()?->id ?? 1]);
    }

    public function show(Course $course)
    {
        return $course->load('sections.components');
    }

    public function update(Request $request, Course $course)
    {
        $course->fill($request->all())->save();
        return $course;
    }

    public function clone(Course $course, CourseStudioService $studio, Request $request)
    {
        return $studio->cloneCourse($course, $request->user()?->id ?? 1);
    }

    public function submitReview(Course $course, ApprovalWorkflowService $approval, Request $request)
    {
        return $approval->submitReview($course, $request->user()?->id ?? 1, $request->input('note'));
    }

    public function approve(Course $course, ApprovalWorkflowService $approval, Request $request)
    {
        return $approval->approve($course, $request->user()?->id ?? 1, $request->input('note'));
    }

    public function publish(Course $course, CourseStudioService $studio, Request $request)
    {
        return $studio->publishCourse($course, $request->user()?->id ?? 1, $request->input('note'));
    }

    public function archive(Course $course, CourseStudioService $studio, Request $request)
    {
        return $studio->archiveCourse($course, $request->user()?->id ?? 1, $request->input('note'));
    }

    public function studio(Course $course)
    {
        return $course->load(['sections' => fn ($q) => $q->orderBy('sort_order'), 'sections.components' => fn ($q) => $q->orderBy('sort_order')]);
    }

    public function storeSection(Request $request, Course $course)
    {
        return CourseSection::query()->create($request->all() + ['tenant_id' => $course->tenant_id, 'course_id' => $course->id]);
    }

    public function updateSection(Request $request, CourseSection $section)
    {
        $section->fill($request->all())->save(); return $section;
    }

    public function deleteSection(CourseSection $section)
    {
        $section->delete(); return response()->noContent();
    }

    public function reorderSections(Request $request)
    {
        foreach ($request->input('items', []) as $item) { CourseSection::query()->whereKey($item['id'])->update(['sort_order' => $item['sort_order']]); }
        return ['status' => 'ok'];
    }

    public function storeComponent(Request $request)
    {
        return CourseComponent::query()->create($request->all());
    }

    public function updateComponent(Request $request, CourseComponent $component)
    {
        $component->fill($request->all())->save(); return $component;
    }

    public function deleteComponent(CourseComponent $component)
    {
        $component->delete(); return response()->noContent();
    }

    public function reorderComponents(Request $request)
    {
        foreach ($request->input('items', []) as $item) { CourseComponent::query()->whereKey($item['id'])->update(['sort_order' => $item['sort_order']]); }
        return ['status' => 'ok'];
    }
}
