<?php

namespace App\Services;

use App\Models\CourseComponent;
use App\Models\CourseSection;

class ComponentFactoryService
{
    public function createTextComponent(CourseSection $unit, array $data): CourseComponent
    {
        return $this->create($unit, $data + ['component_type' => 'text']);
    }

    public function createVideoComponent(CourseSection $unit, array $data): CourseComponent
    {
        return $this->create($unit, $data + ['component_type' => 'video']);
    }

    public function createPdfComponent(CourseSection $unit, array $data): CourseComponent
    {
        return $this->create($unit, $data + ['component_type' => $data['component_type'] ?? 'pdf']);
    }

    public function createQuizComponent(CourseSection $unit, array $data): CourseComponent
    {
        return $this->create($unit, $data + ['component_type' => 'quiz']);
    }

    public function createAssignmentComponent(CourseSection $unit, array $data): CourseComponent
    {
        return $this->create($unit, $data + ['component_type' => 'assignment']);
    }

    public function createForumComponent(CourseSection $unit, array $data): CourseComponent
    {
        return $this->create($unit, $data + ['component_type' => 'forum']);
    }

    public function createScormComponent(CourseSection $unit, array $data): CourseComponent
    {
        return $this->create($unit, $data + ['component_type' => 'scorm']);
    }

    public function validateComponentConfig(array $data): void
    {
        if (empty($data['title'])) {
            throw new \InvalidArgumentException('Component cần tiêu đề.');
        }

        if (($data['required'] ?? true) && empty($data['config']['completion_rule'])) {
            $data['config']['completion_rule'] = ['type' => 'view'];
        }
    }

    private function create(CourseSection $unit, array $data): CourseComponent
    {
        if (! $unit->isUnit()) {
            throw new \InvalidArgumentException('Component chỉ được tạo trong unit.');
        }

        $data['config'] = array_replace_recursive([
            'estimated_minutes' => 10,
            'completion_rule' => ['type' => 'view'],
            'clo_mapping' => [],
        ], $data['config'] ?? []);

        return app(CourseStructureService::class)->createComponent([
            ...$data,
            'section_id' => $unit->id,
            'status' => $data['status'] ?? 'configured',
        ]);
    }
}
