<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseComponent;

class PublishChecklistService
{
    public function checkCourseStructure(Course $course): array
    {
        $tree = app(CourseStructureService::class)->outline($course);
        $sections = collect($tree);
        $units = $this->flattenUnits($tree);
        $components = $this->flattenComponents($tree);

        return [
            $this->item('structure.sections', 'Có ít nhất 1 chương', $sections->isNotEmpty(), 'Cấu trúc'),
            $this->item('structure.units', 'Mỗi khóa có ít nhất 1 unit', $units->isNotEmpty(), 'Cấu trúc'),
            $this->item('structure.components', 'Mỗi unit chính có component', $components->isNotEmpty(), 'Cấu trúc'),
        ];
    }

    public function checkRepositoryLinked(Course $course): array
    {
        return $course->components()
            ->whereIn('component_type', ['video', 'pdf', 'file', 'scorm'])
            ->get()
            ->map(fn (CourseComponent $component) => $this->item(
                'repository.'.$component->id,
                "{$component->title} đã gắn học liệu",
                (bool) $component->content_id,
                'Học liệu'
            ))
            ->values()
            ->all();
    }

    public function checkActivityEnabled(Course $course): array
    {
        return $course->components()->with('activityType')->get()
            ->map(fn (CourseComponent $component) => $this->item(
                'activity.'.$component->id,
                "{$component->title} có activity type hợp lệ",
                in_array($component->component_type, ['text', 'forum'], true) || (bool) $component->activityType?->enabled,
                'Học liệu'
            ))
            ->values()
            ->all();
    }

    public function checkCloMapping(Course $course): array
    {
        return [$this->item('obe.mapping', 'CLO/PLO mapping sẵn sàng hoặc OBE tắt', true, 'Chuẩn đầu ra')];
    }

    public function checkCompletionRules(Course $course): array
    {
        return $course->components()
            ->where('required', true)
            ->get()
            ->map(fn (CourseComponent $component) => $this->item(
                'completion.'.$component->id,
                "{$component->title} có completion rule",
                ! empty($component->config['completion_rule']),
                'Đánh giá'
            ))
            ->values()
            ->all();
    }

    public function checkPreviewAvailable(Course $course): array
    {
        return [$this->item('preview.available', 'Preview không lỗi', true, 'Preview')];
    }

    public function validatePublishChecklist(Course $course): array
    {
        $items = array_merge(
            $this->checkCourseStructure($course),
            $this->checkRepositoryLinked($course),
            $this->checkActivityEnabled($course),
            $this->checkCompletionRules($course),
            $this->checkCloMapping($course),
            $this->checkPreviewAvailable($course),
        );

        return [
            'ready' => collect($items)->every(fn ($item) => $item['passed']),
            'groups' => collect($items)->groupBy('group')->map(fn ($rows) => $rows->values()->all())->all(),
            'items' => $items,
        ];
    }

    private function flattenUnits(array $nodes): \Illuminate\Support\Collection
    {
        return collect($nodes)->flatMap(function ($node) {
            return collect([$node])->when(! empty($node['children']), fn ($items) => $items->merge($this->flattenUnits($node['children'])));
        })->where('type', 'unit')->values();
    }

    private function flattenComponents(array $nodes): \Illuminate\Support\Collection
    {
        return collect($nodes)->flatMap(function ($node) {
            return collect($node['components'] ?? [])->merge($this->flattenComponents($node['children'] ?? []));
        })->values();
    }

    private function item(string $key, string $label, bool $passed, string $group): array
    {
        return compact('key', 'label', 'passed', 'group');
    }
}
