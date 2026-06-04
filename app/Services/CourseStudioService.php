<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CoursePublishLog;
use App\Models\CourseSection;
use App\Models\CourseVersion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CourseStudioService
{
    public function __construct(private readonly CourseStructureService $structure)
    {
    }

    public function createCourse(array $data): Course
    {
        $data['slug'] ??= Str::slug($data['title'].'-'.$data['code']);
        $data['status'] ??= 'draft';
        $data['visibility'] ??= 'internal';
        $data['language'] ??= 'vi';
        $data['settings'] = array_merge([
            'learning_mode' => 'free',
            'completion_policy' => 'required_components',
            'allow_preview' => true,
            'storage_disk' => config('eralms.repository_disk', 'local'),
        ], $data['settings'] ?? []);

        return Course::query()->create($data);
    }

    public function cloneCourse(Course $course, int $actorId): Course
    {
        return DB::transaction(function () use ($course, $actorId) {
            $copy = $course->replicate(['code', 'slug', 'status', 'published_at', 'approved_at', 'approved_by']);
            $copy->code = $course->code.'-CLONE-'.now()->format('His');
            $copy->slug = Str::slug($course->slug.' clone '.now()->timestamp);
            $copy->title = $course->title.' (bản sao)';
            $copy->status = 'draft';
            $copy->owner_id = $actorId;
            $copy->published_at = null;
            $copy->approved_at = null;
            $copy->approved_by = null;
            $copy->save();

            $sections = $course->sections()->with('components')->get();
            $cloneBranch = function (?int $parentId, ?int $newParentId) use (&$cloneBranch, $sections, $copy) {
                $sections->where('parent_id', $parentId)->sortBy('sort_order')->each(function ($section) use ($cloneBranch, $copy, $newParentId) {
                    $newSection = $section->replicate(['course_id', 'parent_id', 'status']);
                    $newSection->course_id = $copy->id;
                    $newSection->parent_id = $newParentId;
                    $newSection->status = 'draft';
                    $newSection->save();

                    foreach ($section->components as $component) {
                        $newComponent = $component->replicate(['course_id', 'section_id', 'status']);
                        $newComponent->course_id = $copy->id;
                        $newComponent->section_id = $newSection->id;
                        $newComponent->status = 'draft';
                        $newComponent->save();
                    }

                    $cloneBranch($section->id, $newSection->id);
                });
            };
            $cloneBranch(null, null);

            $this->createVersionSnapshot($copy, $actorId, 'Clone từ '.$course->code);
            CoursePublishLog::query()->create(['tenant_id' => $course->tenant_id, 'course_id' => $copy->id, 'action' => 'clone', 'actor_id' => $actorId, 'note' => 'Clone từ '.$course->code]);

            return $copy->fresh(['sections.components']);
        });
    }

    public function createVersionSnapshot(Course $course, int $createdBy, ?string $note = null): CourseVersion
    {
        return CourseVersion::query()->create([
            'tenant_id' => $course->tenant_id,
            'course_id' => $course->id,
            'version' => (int) CourseVersion::query()->where('course_id', $course->id)->max('version') + 1,
            'title_snapshot' => $course->title,
            'structure_snapshot' => $this->structure->outline($course),
            'change_note' => $note,
            'created_by' => $createdBy,
            'created_at' => now(),
        ]);
    }

    public function validateCourseStructure(Course $course): array
    {
        $sections = $course->sections()->with('components.activityType')->get();
        $errors = [];

        if ($sections->where('type', 'section')->isEmpty()) {
            $errors[] = 'Khóa học cần ít nhất một section/chương.';
        }

        if ($sections->where('type', 'unit')->isEmpty()) {
            $errors[] = 'Khóa học cần ít nhất một unit/bài học.';
        }

        $components = $sections->flatMap->components;
        if ($components->isEmpty()) {
            $errors[] = 'Khóa học cần ít nhất một component/học liệu hoặc hoạt động.';
        }

        foreach ($components as $component) {
            if (! $component->activityType || ! $component->activityType->enabled) {
                $errors[] = "Component {$component->title} dùng activity type chưa được bật.";
            }

            if (in_array($component->component_type, ['video', 'pdf', 'file', 'scorm'], true) && ! $component->content_id) {
                $errors[] = "Component {$component->title} cần liên kết học liệu trong repository.";
            }
        }

        if (! in_array($course->status, ['draft', 'review', 'approved', 'published'], true)) {
            $errors[] = 'Trạng thái khóa học không hợp lệ để publish.';
        }

        return array_values(array_unique($errors));
    }

    public function submitReview(Course $course, int $actorId, ?string $note = null): Course
    {
        $course->forceFill(['status' => 'review'])->save();
        CoursePublishLog::query()->create(['tenant_id' => $course->tenant_id, 'course_id' => $course->id, 'action' => 'submit_review', 'actor_id' => $actorId, 'note' => $note]);

        return $course;
    }

    public function approveCourse(Course $course, int $actorId, ?string $note = null): Course
    {
        $course->forceFill(['status' => 'approved', 'approved_by' => $actorId, 'approved_at' => now()])->save();
        CoursePublishLog::query()->create(['tenant_id' => $course->tenant_id, 'course_id' => $course->id, 'action' => 'approve', 'actor_id' => $actorId, 'note' => $note]);

        return $course;
    }

    public function publishCourse(Course $course, int $actorId, ?string $note = null): CourseVersion
    {
        $errors = $this->validateCourseStructure($course);
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }

        $version = $this->createVersionSnapshot($course, $actorId, $note);
        $course->forceFill(['status' => 'published', 'published_at' => now()])->save();
        CourseSection::query()->where('course_id', $course->id)->where('status', 'draft')->update(['status' => 'published']);
        $course->components()->where('status', 'draft')->update(['status' => 'published']);
        CoursePublishLog::query()->create(['tenant_id' => $course->tenant_id, 'course_id' => $course->id, 'version_id' => $version->id, 'action' => 'publish', 'actor_id' => $actorId, 'note' => $note]);
        Cache::put($this->publishedOutlineCacheKey($course), $version->structure_snapshot, 3600);

        return $version;
    }

    public function archiveCourse(Course $course, int $actorId, ?string $note = null): Course
    {
        $course->forceFill(['status' => 'archived'])->save();
        CoursePublishLog::query()->create(['tenant_id' => $course->tenant_id, 'course_id' => $course->id, 'action' => 'archive', 'actor_id' => $actorId, 'note' => $note]);
        Cache::forget($this->publishedOutlineCacheKey($course));

        return $course;
    }

    private function publishedOutlineCacheKey(Course $course): string
    {
        return "eralms:course:outline:{$course->tenant_id}:{$course->id}";
    }
}
