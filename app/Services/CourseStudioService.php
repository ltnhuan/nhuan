<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseComponent;
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

    public function getStudioPayload(Course $course): array
    {
        return [
            'course' => $course->load(['category', 'academicUnit', 'owner']),
            'outline' => $this->structure->outline($course),
            'versions' => $course->versions()->limit(10)->get(),
            'publish_logs' => $course->publishLogs()->limit(20)->get(),
            'checklist' => $this->validatePublishChecklist($course),
        ];
    }

    public function createSection(Course $course, array $data): CourseSection
    {
        return $this->structure->createSection($course, $data + ['type' => 'section']);
    }

    public function createUnit(Course $course, array $data): CourseSection
    {
        return $this->structure->createSection($course, $data + ['type' => 'unit']);
    }

    public function reorderSections(array $items): void
    {
        $this->structure->reorderSections($items);
    }

    public function createComponent(array $data): CourseComponent
    {
        $unit = CourseSection::query()->findOrFail($data['section_id']);
        $factory = app(ComponentFactoryService::class);

        return match ($data['component_type']) {
            'text' => $factory->createTextComponent($unit, $data),
            'video' => $factory->createVideoComponent($unit, $data),
            'pdf', 'file' => $factory->createPdfComponent($unit, $data),
            'quiz' => $factory->createQuizComponent($unit, $data),
            'assignment' => $factory->createAssignmentComponent($unit, $data),
            'forum' => $factory->createForumComponent($unit, $data),
            'scorm' => $factory->createScormComponent($unit, $data),
            default => $this->structure->createComponent($data),
        };
    }

    public function updateComponent(CourseComponent $component, array $data): CourseComponent
    {
        return $this->structure->updateComponent($component, $data);
    }

    public function deleteComponent(CourseComponent $component): void
    {
        $component->delete();
    }

    public function duplicateComponent(CourseComponent $component): CourseComponent
    {
        $copy = $component->replicate(['sort_order', 'status']);
        $copy->title = $component->title.' (bản sao)';
        $copy->sort_order = (int) CourseComponent::query()->where('section_id', $component->section_id)->max('sort_order') + 1;
        $copy->status = 'draft';
        $copy->save();

        return $copy->fresh(['contentItem', 'activityType']);
    }

    public function validatePublishChecklist(Course $course): array
    {
        return app(PublishChecklistService::class)->validatePublishChecklist($course);
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
            app(AuditLogService::class)->record('clone', 'course', $copy, [], $copy->toArray(), (object) ['id' => $actorId]);

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
        if ($course->status !== 'draft') {
            throw new \InvalidArgumentException('Chỉ khóa học draft mới được gửi review.');
        }

        $before = $course->toArray();
        $course->forceFill(['status' => 'review'])->save();
        CoursePublishLog::query()->create(['tenant_id' => $course->tenant_id, 'course_id' => $course->id, 'action' => 'submit_review', 'actor_id' => $actorId, 'note' => $note]);
        app(AuditLogService::class)->record('submit_review', 'course', $course, $before, $course->fresh()->toArray(), (object) ['id' => $actorId]);

        return $course;
    }

    public function approveCourse(Course $course, int $actorId, ?string $note = null): Course
    {
        if ($course->status !== 'review') {
            throw new \InvalidArgumentException('Chỉ khóa học đang review mới được approve.');
        }

        $before = $course->toArray();
        $course->forceFill(['status' => 'approved', 'approved_by' => $actorId, 'approved_at' => now()])->save();
        CoursePublishLog::query()->create(['tenant_id' => $course->tenant_id, 'course_id' => $course->id, 'action' => 'approve', 'actor_id' => $actorId, 'note' => $note]);
        app(AuditLogService::class)->record('approve', 'course', $course, $before, $course->fresh()->toArray(), (object) ['id' => $actorId]);

        return $course;
    }

    public function publishCourse(Course $course, int $actorId, ?string $note = null): CourseVersion
    {
        if ($course->status !== 'approved') {
            throw new \InvalidArgumentException('Chỉ khóa học đã approved mới được publish.');
        }

        $errors = $this->validateCourseStructure($course);
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }

        $before = $course->toArray();
        $version = $this->createVersionSnapshot($course, $actorId, $note);
        $course->forceFill(['status' => 'published', 'published_at' => now()])->save();
        CourseSection::query()->where('course_id', $course->id)->where('status', 'draft')->update(['status' => 'published']);
        $course->components()->where('status', 'draft')->update(['status' => 'published']);
        CoursePublishLog::query()->create(['tenant_id' => $course->tenant_id, 'course_id' => $course->id, 'version_id' => $version->id, 'action' => 'publish', 'actor_id' => $actorId, 'note' => $note]);
        Cache::put($this->publishedOutlineCacheKey($course), $version->structure_snapshot, 3600);
        app(AuditLogService::class)->record('publish', 'course', $course, $before, $course->fresh()->toArray(), (object) ['id' => $actorId]);

        return $version;
    }

    public function archiveCourse(Course $course, int $actorId, ?string $note = null): Course
    {
        if (! in_array($course->status, ['published', 'approved'], true)) {
            throw new \InvalidArgumentException('Chỉ khóa học approved hoặc published mới được archive.');
        }

        $before = $course->toArray();
        $course->forceFill(['status' => 'archived'])->save();
        CoursePublishLog::query()->create(['tenant_id' => $course->tenant_id, 'course_id' => $course->id, 'action' => 'archive', 'actor_id' => $actorId, 'note' => $note]);
        Cache::forget($this->publishedOutlineCacheKey($course));
        app(AuditLogService::class)->record('archive', 'course', $course, $before, $course->fresh()->toArray(), (object) ['id' => $actorId]);

        return $course;
    }

    private function publishedOutlineCacheKey(Course $course): string
    {
        return "eralms:course:outline:{$course->tenant_id}:{$course->id}";
    }
}
