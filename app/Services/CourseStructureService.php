<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\CourseSection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CourseStructureService
{
    public function createSection(Course $course, array $data): CourseSection
    {
        $type = $data['type'] ?? 'section';
        $parentId = $data['parent_id'] ?? null;
        $this->validateSectionParent($course, $type, $parentId);

        return CourseSection::query()->create($data + [
            'tenant_id' => $course->tenant_id,
            'course_id' => $course->id,
            'sort_order' => $data['sort_order'] ?? $this->nextSectionSortOrder($course->id, $parentId),
            'status' => $data['status'] ?? 'draft',
            'settings' => $data['settings'] ?? [],
        ]);
    }

    public function updateSection(CourseSection $section, array $data): CourseSection
    {
        if (array_key_exists('parent_id', $data) || array_key_exists('type', $data)) {
            $this->validateSectionParent($section->course, $data['type'] ?? $section->type, $data['parent_id'] ?? $section->parent_id, $section->id);
        }

        $section->fill($data)->save();

        return $section->fresh(['children', 'components']);
    }

    public function createComponent(array $data): CourseComponent
    {
        $section = CourseSection::query()->with('course')->findOrFail($data['section_id']);

        if (! $section->isUnit()) {
            throw new \InvalidArgumentException('Component chỉ được gắn vào unit.');
        }

        return CourseComponent::query()->create($data + [
            'tenant_id' => $section->tenant_id,
            'course_id' => $section->course_id,
            'sort_order' => $data['sort_order'] ?? $this->nextComponentSortOrder($section->id),
            'required' => $data['required'] ?? true,
            'status' => $data['status'] ?? 'draft',
            'config' => $data['config'] ?? [],
        ]);
    }

    public function updateComponent(CourseComponent $component, array $data): CourseComponent
    {
        $component->fill($data)->save();

        return $component->fresh(['contentItem', 'activityType']);
    }

    public function reorderSections(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                CourseSection::query()->whereKey($item['id'])->update([
                    'parent_id' => $item['parent_id'] ?? null,
                    'sort_order' => $item['sort_order'],
                ]);
            }
        });
    }

    public function reorderComponents(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                $payload = ['sort_order' => $item['sort_order']];
                if (array_key_exists('section_id', $item)) {
                    $payload['section_id'] = $item['section_id'];
                }
                CourseComponent::query()->whereKey($item['id'])->update($payload);
            }
        });
    }

    public function outline(Course $course): array
    {
        $sections = $course->sections()->with(['components.contentItem'])->get();

        return $this->buildTree($sections->whereNull('parent_id'), $sections);
    }

    private function buildTree(Collection $nodes, Collection $all): array
    {
        return $nodes->sortBy('sort_order')->values()->map(function (CourseSection $section) use ($all) {
            return [
                'id' => $section->id,
                'type' => $section->type,
                'title' => $section->title,
                'status' => $section->status,
                'sort_order' => $section->sort_order,
                'release_at' => $section->release_at,
                'due_at' => $section->due_at,
                'settings' => $section->settings ?? [],
                'components' => $section->components->map(fn (CourseComponent $component) => [
                    'id' => $component->id,
                    'component_type' => $component->component_type,
                    'title' => $component->title,
                    'required' => $component->required,
                    'status' => $component->status,
                    'sort_order' => $component->sort_order,
                    'content' => $component->contentItem?->only(['id', 'title', 'item_type', 'status', 'mime_type']),
                    'config' => $component->config ?? [],
                ])->values()->all(),
                'children' => $this->buildTree($all->where('parent_id', $section->id), $all),
            ];
        })->all();
    }

    private function validateSectionParent(Course $course, string $type, ?int $parentId, ?int $ignoreId = null): void
    {
        if ($type === 'section' && $parentId !== null) {
            throw new \InvalidArgumentException('Section gốc không được có parent.');
        }

        if ($type !== 'section' && $parentId === null) {
            throw new \InvalidArgumentException('Subsection/unit cần parent.');
        }

        if ($parentId === null) {
            return;
        }

        $parent = CourseSection::query()->where('course_id', $course->id)->findOrFail($parentId);

        if ($ignoreId !== null && $parent->id === $ignoreId) {
            throw new \InvalidArgumentException('Section không được làm parent của chính nó.');
        }

        if ($type === 'subsection' && $parent->type !== 'section') {
            throw new \InvalidArgumentException('Subsection phải nằm dưới section.');
        }

        if ($type === 'unit' && ! in_array($parent->type, ['section', 'subsection'], true)) {
            throw new \InvalidArgumentException('Unit phải nằm dưới section hoặc subsection.');
        }
    }

    private function nextSectionSortOrder(int $courseId, ?int $parentId): int
    {
        return (int) CourseSection::query()->where('course_id', $courseId)->where('parent_id', $parentId)->max('sort_order') + 1;
    }

    private function nextComponentSortOrder(int $sectionId): int
    {
        return (int) CourseComponent::query()->where('section_id', $sectionId)->max('sort_order') + 1;
    }
}
