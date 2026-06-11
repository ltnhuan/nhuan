<?php

namespace Tests\Feature;

use App\Models\ContentRepositoryItem;
use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\LmsUser;
use App\Models\Tenant;
use Database\Seeders\CoreSeeder;
use Database\Seeders\CourseStudioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseStudioCommercialFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(CourseStudioSeeder::class);
    }

    public function test_commercial_course_studio_action_flow(): void
    {
        $tenant = Tenant::query()->where('code', 'VABIS')->firstOrFail();
        $owner = LmsUser::query()->where('tenant_id', $tenant->id)->where('email', 'admin.lms@vabis.edu.vn')->firstOrFail();

        $video = ContentRepositoryItem::query()->create([
            'tenant_id' => $tenant->id,
            'title' => 'Video an toàn',
            'item_type' => 'video',
            'mime_type' => 'video/mp4',
            'storage_disk' => 'local',
            'storage_path' => 'videos/demo.mp4',
            'file_size' => 1024,
            'visibility' => 'tenant',
            'status' => 'published',
            'owner_id' => $owner->id,
            'metadata' => [],
        ]);

        $replacement = ContentRepositoryItem::query()->create([
            'tenant_id' => $tenant->id,
            'title' => 'Video thay thế',
            'item_type' => 'video',
            'mime_type' => 'video/mp4',
            'storage_disk' => 'local',
            'storage_path' => 'videos/replacement.mp4',
            'file_size' => 2048,
            'visibility' => 'tenant',
            'status' => 'published',
            'owner_id' => $owner->id,
            'metadata' => [],
        ]);

        $courseId = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/courses', [
                'code' => 'COMM-STUDIO',
                'title' => 'Commercial Studio Course',
                'level' => 'college',
                'course_type' => 'blended',
            ])
            ->assertCreated()
            ->json('id');

        $chapterId = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v1/courses/{$courseId}/sections", ['type' => 'section', 'title' => 'Chương 1'])
            ->assertCreated()
            ->json('id');

        $unitId = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v1/courses/{$courseId}/sections", ['parent_id' => $chapterId, 'type' => 'unit', 'title' => 'Bài 1'])
            ->assertCreated()
            ->json('id');

        $textId = $this->createComponent($unitId, 'text', 'Text lesson');
        $videoId = $this->createComponent($unitId, 'video', 'Video lesson', $video->id);
        $quizId = $this->createComponent($unitId, 'quiz', 'Quiz lesson');
        $assignmentId = $this->createComponent($unitId, 'assignment', 'Assignment lesson');

        $this->withHeaders($this->adminHeaders())
            ->putJson("/api/v1/course-components/{$videoId}", ['content_id' => $replacement->id])
            ->assertOk();

        $duplicateId = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v1/course-components/{$textId}/duplicate")
            ->assertOk()
            ->json('data.id');

        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/course-components/reorder', [
                'items' => [
                    ['id' => $videoId, 'section_id' => $unitId, 'sort_order' => 1],
                    ['id' => $textId, 'section_id' => $unitId, 'sort_order' => 2],
                    ['id' => $quizId, 'section_id' => $unitId, 'sort_order' => 3],
                    ['id' => $assignmentId, 'section_id' => $unitId, 'sort_order' => 4],
                    ['id' => $duplicateId, 'section_id' => $unitId, 'sort_order' => 5],
                ],
            ])
            ->assertOk();

        $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/v1/course-components/{$duplicateId}")
            ->assertNoContent();

        $checklist = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/v1/courses/{$courseId}/publish-checklist")
            ->assertOk()
            ->json('data');

        $this->assertTrue($checklist['ready']);

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v1/courses/{$courseId}/submit-review")
            ->assertOk();

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v1/courses/{$courseId}/approve")
            ->assertOk();

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v1/courses/{$courseId}/publish")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('published', Course::query()->findOrFail($courseId)->status);
        $this->assertSame($replacement->id, CourseComponent::query()->findOrFail($videoId)->content_id);
    }

    public function test_publish_checklist_detects_missing_video_content(): void
    {
        $courseId = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/courses', [
                'code' => 'MISS-VIDEO',
                'title' => 'Missing Video Content',
                'level' => 'college',
                'course_type' => 'online',
            ])
            ->assertCreated()
            ->json('id');

        $chapterId = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v1/courses/{$courseId}/sections", ['type' => 'section', 'title' => 'Chương 1'])
            ->assertCreated()
            ->json('id');

        $unitId = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v1/courses/{$courseId}/sections", ['parent_id' => $chapterId, 'type' => 'unit', 'title' => 'Bài 1'])
            ->assertCreated()
            ->json('id');

        $this->createComponent($unitId, 'video', 'Video thiếu học liệu');

        $checklist = $this->withHeaders($this->adminHeaders())
            ->getJson("/api/v1/courses/{$courseId}/publish-checklist")
            ->assertOk()
            ->json('data');

        $this->assertFalse($checklist['ready']);
    }

    public function test_studio_activity_advanced_quiz_settings_are_persisted(): void
    {
        $unitId = $this->createCourseUnit('ADV-QUIZ', 'Advanced Quiz Course');

        $payload = [
            'section_id' => $unitId,
            'component_type' => 'quiz',
            'title' => 'Kiểm tra chuẩn QTI',
            'required' => true,
            'status' => 'draft',
            'config' => [
                'estimated_minutes' => 45,
                'completion_rule' => ['type' => 'score', 'mastery_score' => 70],
                'release' => ['mode' => 'scheduled', 'prerequisite_mode' => 'previous_required'],
                'grading' => [
                    'total_score' => 100,
                    'pass_score' => 70,
                    'weight' => 20,
                    'max_attempts' => 2,
                    'show_feedback' => 'after_submit',
                ],
                'assessment' => [
                    'shuffle_questions' => true,
                    'shuffle_options' => true,
                    'question_delivery' => 'one_by_one',
                    'prevent_backtracking' => false,
                ],
                'accessibility' => [
                    'captions_required' => false,
                    'transcript_required' => false,
                    'alt_text_required' => true,
                ],
                'interoperability' => [
                    'standard_profile' => 'qti_3',
                    'tracking_profile' => 'answered_scored_completed',
                    'secure_launch' => false,
                    'open_in_new_window' => false,
                ],
                'source_policy' => [
                    'must_load_from_repository' => true,
                    'accepted_sources' => ['question_bank', 'qti'],
                ],
                'clo_mapping' => [],
            ],
        ];

        $componentId = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/course-components', $payload)
            ->assertCreated()
            ->assertJsonPath('component_type', 'quiz')
            ->assertJsonPath('config.completion_rule.type', 'score')
            ->assertJsonPath('config.grading.max_attempts', 2)
            ->assertJsonPath('config.assessment.shuffle_questions', true)
            ->assertJsonPath('config.interoperability.standard_profile', 'qti_3')
            ->json('id');

        $component = CourseComponent::query()->findOrFail($componentId);

        $this->assertTrue($component->required);
        $this->assertSame(45, $component->config['estimated_minutes']);
        $this->assertSame('scheduled', $component->config['release']['mode']);
        $this->assertSame(70, $component->config['grading']['pass_score']);
        $this->assertSame('one_by_one', $component->config['assessment']['question_delivery']);
        $this->assertSame(['question_bank', 'qti'], $component->config['source_policy']['accepted_sources']);
    }

    public function test_studio_activity_advanced_settings_survive_component_update(): void
    {
        $unitId = $this->createCourseUnit('ADV-UPDATE', 'Advanced Update Course');
        $componentId = $this->createComponent($unitId, 'scorm', 'SCORM package');

        $config = [
            'estimated_minutes' => 30,
            'completion_rule' => ['type' => 'package_status', 'mastery_score' => 80],
            'release' => ['mode' => 'manual', 'prerequisite_mode' => 'unit_required'],
            'accessibility' => ['captions_required' => true, 'transcript_required' => true, 'alt_text_required' => true],
            'interoperability' => [
                'standard_profile' => 'cmi5',
                'tracking_profile' => 'launched_suspended_completed',
                'secure_launch' => true,
                'open_in_new_window' => true,
            ],
            'source_policy' => [
                'must_load_from_repository' => true,
                'accepted_sources' => ['scorm', 'xapi', 'cmi5'],
            ],
            'clo_mapping' => [['outcome_id' => 10, 'weight' => 1]],
        ];

        $this->withHeaders($this->adminHeaders())
            ->putJson("/api/v1/course-components/{$componentId}", [
                'title' => 'SCORM package updated',
                'required' => false,
                'config' => $config,
            ])
            ->assertOk()
            ->assertJsonPath('title', 'SCORM package updated')
            ->assertJsonPath('required', false)
            ->assertJsonPath('config.interoperability.standard_profile', 'cmi5')
            ->assertJsonPath('config.source_policy.accepted_sources.2', 'cmi5');

        $component = CourseComponent::query()->findOrFail($componentId);

        $this->assertFalse($component->required);
        $this->assertSame('manual', $component->config['release']['mode']);
        $this->assertTrue($component->config['interoperability']['secure_launch']);
        $this->assertSame([['outcome_id' => 10, 'weight' => 1]], $component->config['clo_mapping']);
    }

    private function createComponent(int $unitId, string $type, string $title, ?int $contentId = null): int
    {
        return $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/course-components', [
                'section_id' => $unitId,
                'component_type' => $type,
                'title' => $title,
                'content_id' => $contentId,
                'required' => true,
                'config' => [
                    'estimated_minutes' => 10,
                    'completion_rule' => ['type' => 'view'],
                    'clo_mapping' => [],
                ],
            ])
            ->assertCreated()
            ->json('id');
    }

    private function createCourseUnit(string $code, string $title): int
    {
        $courseId = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/courses', [
                'code' => $code,
                'title' => $title,
                'level' => 'college',
                'course_type' => 'online',
            ])
            ->assertCreated()
            ->json('id');

        $chapterId = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v1/courses/{$courseId}/sections", ['type' => 'section', 'title' => 'Chương 1'])
            ->assertCreated()
            ->json('id');

        return $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v1/courses/{$courseId}/sections", ['parent_id' => $chapterId, 'type' => 'unit', 'title' => 'Bài 1'])
            ->assertCreated()
            ->json('id');
    }

    private function adminHeaders(): array
    {
        return [
            'X-Tenant-Code' => 'VABIS',
            'X-Demo-User-Email' => 'admin.lms@vabis.edu.vn',
        ];
    }
}
