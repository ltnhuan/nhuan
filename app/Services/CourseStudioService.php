<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CoursePublishLog;
use App\Models\CourseVersion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CourseStudioService
{
    public function createCourse(array $data): Course
    {
        $data['slug'] ??= Str::slug($data['title'].'-'.$data['code']);
        $data['status'] ??= 'draft';
        return Course::query()->create($data);
    }

    public function cloneCourse(Course $course, int $actorId): Course
    {
        $copy = $course->replicate(['code', 'slug', 'status', 'published_at', 'approved_at', 'approved_by']);
        $copy->code = $course->code.'-CLONE-'.now()->format('His');
        $copy->slug = Str::slug($course->slug.' clone '.now()->timestamp);
        $copy->title = $course->title.' (bản sao)';
        $copy->status = 'draft';
        $copy->owner_id = $actorId;
        $copy->save();

        foreach ($course->sections()->with('components')->get() as $section) {
            $newSection = $section->replicate(['course_id']);
            $newSection->course_id = $copy->id;
            $newSection->save();
            foreach ($section->components as $component) {
                $newComponent = $component->replicate(['course_id', 'section_id']);
                $newComponent->course_id = $copy->id;
                $newComponent->section_id = $newSection->id;
                $newComponent->save();
            }
        }

        $this->createVersionSnapshot($copy, $actorId, 'Clone từ '.$course->code);
        return $copy;
    }

    public function createVersionSnapshot(Course $course, int $createdBy, ?string $note = null): CourseVersion
    {
        $structure = $course->loadMissing('sections.components')->sections->map(fn ($section) => [
            'id' => $section->id,
            'type' => $section->type,
            'title' => $section->title,
            'components' => $section->components->map->only(['id', 'component_type', 'title', 'required', 'status']),
        ]);

        return CourseVersion::query()->create([
            'tenant_id' => $course->tenant_id,
            'course_id' => $course->id,
            'version' => (int) CourseVersion::query()->where('course_id', $course->id)->max('version') + 1,
            'title_snapshot' => $course->title,
            'structure_snapshot' => $structure,
            'change_note' => $note,
            'created_by' => $createdBy,
        ]);
    }

    public function validateCourseStructure(Course $course): array
    {
        $course->loadMissing('sections.components');
        $errors = [];
        if ($course->sections->isEmpty()) {
            $errors[] = 'Khóa học cần ít nhất một chương/phần.';
        }
        if ($course->sections->flatMap->components->isEmpty()) {
            $errors[] = 'Khóa học cần ít nhất một học liệu hoặc hoạt động.';
        }
        return $errors;
    }

    public function publishCourse(Course $course, int $actorId, ?string $note = null): CourseVersion
    {
        $errors = $this->validateCourseStructure($course);
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }

        $version = $this->createVersionSnapshot($course, $actorId, $note);
        $course->forceFill(['status' => 'published', 'published_at' => now()])->save();
        CoursePublishLog::query()->create(['tenant_id' => $course->tenant_id, 'course_id' => $course->id, 'version_id' => $version->id, 'action' => 'publish', 'actor_id' => $actorId, 'note' => $note]);
        Cache::put("eralms:course:outline:{$course->tenant_id}:{$course->id}", $version->structure_snapshot, 3600);
        return $version;
    }

    public function archiveCourse(Course $course, int $actorId, ?string $note = null): Course
    {
        $course->forceFill(['status' => 'archived'])->save();
        CoursePublishLog::query()->create(['tenant_id' => $course->tenant_id, 'course_id' => $course->id, 'action' => 'archive', 'actor_id' => $actorId, 'note' => $note]);
        Cache::forget("eralms:course:outline:{$course->tenant_id}:{$course->id}");
        return $course;
    }
}
