<?php

namespace Tests\Feature;

use App\Models\ContentRepositoryItem;
use App\Models\ContentVersion;
use Database\Seeders\CoreSeeder;
use Database\Seeders\SharedLearningRepositorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedLearningRepositorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_learning_repository_seed_creates_tree_metadata_and_versions(): void
    {
        $this->seed(CoreSeeder::class);
        $this->seed(SharedLearningRepositorySeeder::class);

        $root = ContentRepositoryItem::query()
            ->whereNull('parent_id')
            ->where('item_type', 'folder')
            ->where('title', 'Repository')
            ->firstOrFail();

        $this->assertEqualsCanonicalizing(
            ['CNTT', 'Du lịch', 'Marketing', 'THPT', 'Tiếng Anh', 'Tiếng Hàn', 'Tiếng Hoa', 'Văn hóa 9+'],
            $root->children()->pluck('title')->all()
        );

        $thpt = $root->children()->where('title', 'THPT')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            ['Assignment Template', 'Exam Blueprint', 'PDF', 'Quiz Template', 'Rubric Template', 'Slide', 'Video'],
            $thpt->children()->pluck('title')->all()
        );

        $sampleItems = ContentRepositoryItem::query()
            ->where('storage_path', 'like', 'eralms/shared-repository/%')
            ->where('item_type', '!=', 'folder')
            ->count();

        $this->assertSame(SharedLearningRepositorySeeder::SAMPLE_ITEM_COUNT, $sampleItems);

        $item = ContentRepositoryItem::query()
            ->with('versions')
            ->where('storage_path', 'like', 'eralms/shared-repository/%')
            ->where('item_type', 'exam_blueprint')
            ->firstOrFail();

        $this->assertSame('shared_learning_repository', $item->metadata['seed']);
        $this->assertTrue($item->metadata['sample_repository_item']);
        $this->assertArrayHasKey('cap_do', $item->metadata);
        $this->assertArrayHasKey('mon_hoc', $item->metadata);
        $this->assertArrayHasKey('khoa', $item->metadata);
        $this->assertArrayHasKey('CLO', $item->metadata);
        $this->assertArrayHasKey('PLO', $item->metadata);
        $this->assertArrayHasKey('thoi_luong_phut', $item->metadata);
        $this->assertCount(2, $item->versions);

        $this->assertSame(
            SharedLearningRepositorySeeder::SAMPLE_ITEM_COUNT * 2,
            ContentVersion::query()
                ->whereHas('contentItem', fn ($query) => $query->where('storage_path', 'like', 'eralms/shared-repository/%'))
                ->count()
        );
    }
}
