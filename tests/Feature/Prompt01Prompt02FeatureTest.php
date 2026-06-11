<?php

namespace Tests\Feature;

use App\Models\ContentRepositoryItem;
use App\Models\Course;
use App\Models\CourseVersion;
use App\Models\Tenant;
use Database\Seeders\CoreSeeder;
use Database\Seeders\CourseStudioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Prompt01Prompt02FeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(CourseStudioSeeder::class);
    }

    public function test_core_me_resolves_tenant_and_demo_user(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/core/me')
            ->assertOk()
            ->assertJsonPath('tenant.code', 'VABIS')
            ->assertJsonPath('user.email', 'admin.lms@vabis.edu.vn');
    }

    public function test_rbac_blocks_student_from_tenant_management(): void
    {
        $this->withHeaders([
            'X-Tenant-Code' => 'VABIS',
            'X-Demo-User-Email' => 'sv.lms@vabis.edu.vn',
        ])->getJson('/api/v1/core/tenants')->assertForbidden();
    }

    public function test_course_studio_can_create_outline_and_publish_snapshot(): void
    {
        $courseId = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/courses', [
                'code' => 'QA101',
                'title' => 'Khóa kiểm thử Prompt 02',
                'level' => 'college',
                'course_type' => 'online',
            ])
            ->assertCreated()
            ->json('id');

        $sectionId = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v1/courses/{$courseId}/sections", [
                'type' => 'section',
                'title' => 'Chương 1',
            ])
            ->assertCreated()
            ->json('id');

        $unitId = $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v1/courses/{$courseId}/sections", [
                'parent_id' => $sectionId,
                'type' => 'unit',
                'title' => 'Bài 1',
            ])
            ->assertCreated()
            ->json('id');

        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/course-components', [
                'section_id' => $unitId,
                'component_type' => 'text',
                'title' => 'Nội dung bài học',
                'config' => ['body' => 'Demo'],
            ])
            ->assertCreated();

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v1/courses/{$courseId}/submit-review", ['note' => 'Ready for review'])
            ->assertOk();

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v1/courses/{$courseId}/approve", ['note' => 'Approved'])
            ->assertOk();

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v1/courses/{$courseId}/publish", ['note' => 'Feature test'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('published', Course::query()->findOrFail($courseId)->status);
        $this->assertSame(1, CourseVersion::query()->where('course_id', $courseId)->count());
    }

    public function test_repository_upload_creates_item_and_version(): void
    {
        Storage::fake('local');

        $folderId = ContentRepositoryItem::query()
            ->where('tenant_id', Tenant::query()->where('code', 'VABIS')->value('id'))
            ->where('item_type', 'folder')
            ->value('id');

        $itemId = $this->withHeaders($this->adminHeaders())
            ->post('/api/v1/repository/upload', [
                'parent_id' => $folderId,
                'title' => 'Tài liệu kiểm thử',
                'file' => UploadedFile::fake()->create('tailieu.pdf', 64, 'application/pdf'),
            ], $this->adminHeaders())
            ->assertCreated()
            ->json('id');

        $item = ContentRepositoryItem::query()->with('versions')->findOrFail($itemId);

        $this->assertSame('pdf', $item->item_type);
        $this->assertCount(1, $item->versions);
    }

    public function test_repository_trash_restore_and_permanent_delete_workflow(): void
    {
        $folderId = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/repository/folders', [
                'title' => 'Thư mục có thể khôi phục',
                'visibility' => 'tenant',
            ])
            ->assertCreated()
            ->json('id');

        $childId = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/repository/folders', [
                'parent_id' => $folderId,
                'title' => 'Thư mục con',
                'visibility' => 'tenant',
            ])
            ->assertCreated()
            ->json('id');

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v1/repository/items/{$folderId}/trash")
            ->assertOk()
            ->assertJsonPath('status', 'trashed');

        $this->assertSame('trashed', ContentRepositoryItem::query()->findOrFail($folderId)->status);
        $this->assertSame('trashed', ContentRepositoryItem::query()->findOrFail($childId)->status);

        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/repository/items')
            ->assertOk()
            ->assertJsonMissing(['title' => 'Thư mục có thể khôi phục']);

        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/repository/items?trash=1')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Thư mục có thể khôi phục']);

        $this->withHeaders($this->adminHeaders())
            ->postJson("/api/v1/repository/items/{$folderId}/restore")
            ->assertOk()
            ->assertJsonPath('status', 'draft');

        $this->assertNull(ContentRepositoryItem::query()->findOrFail($folderId)->parent_id);

        $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/v1/repository/items/{$childId}/permanent")
            ->assertNoContent();

        $this->assertDatabaseMissing('content_repository_items', ['id' => $childId]);
    }

    public function test_repository_bulk_actions_can_trash_and_restore_items(): void
    {
        $firstId = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/repository/folders', ['title' => 'Bulk A'])
            ->assertCreated()
            ->json('id');

        $secondId = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/repository/folders', ['title' => 'Bulk B'])
            ->assertCreated()
            ->json('id');

        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/repository/bulk-actions', [
                'action' => 'trash',
                'item_ids' => [$firstId, $secondId],
            ])
            ->assertOk()
            ->assertJsonPath('affected', 2);

        $this->assertSame(2, ContentRepositoryItem::query()->whereIn('id', [$firstId, $secondId])->where('status', 'trashed')->count());

        $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/repository/bulk-actions', [
                'action' => 'restore',
                'item_ids' => [$firstId, $secondId],
            ])
            ->assertOk()
            ->assertJsonPath('affected', 2);

        $this->assertSame(0, ContentRepositoryItem::query()->whereIn('id', [$firstId, $secondId])->where('status', 'trashed')->count());
    }

    public function test_editor_media_upload_returns_insertable_media_payload(): void
    {
        Storage::fake('local');

        $payload = $this->withHeaders($this->adminHeaders())
            ->post('/api/v1/editor/media-upload', [
                'file' => UploadedFile::fake()->image('question-image.png', 640, 360),
            ], $this->adminHeaders())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->json('data');

        $this->assertSame('image', $payload['type']);
        $this->assertSame('image/png', $payload['mime_type']);
        $this->assertNotEmpty($payload['url']);
    }

    private function adminHeaders(): array
    {
        return [
            'X-Tenant-Code' => 'VABIS',
            'X-Demo-User-Email' => 'admin.lms@vabis.edu.vn',
        ];
    }
}
