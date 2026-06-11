<?php

namespace Tests\Feature;

use App\Models\CourseComponent;
use App\Models\LmsUser;
use App\Models\Tenant;
use App\Models\VideoAsset;
use App\Models\VideoProgressSummary;
use App\Services\VideoAntiFakeService;
use App\Services\VideoAssetService;
use App\Services\VideoTrackingService;
use Database\Seeders\CoreSeeder;
use Database\Seeders\CourseStudioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoPlatformFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->seed(CourseStudioSeeder::class);
    }

    public function test_upload_video_creates_asset(): void
    {
        Storage::fake('public');

        $response = $this->withHeaders($this->adminHeaders())->post('/api/v1/videos/upload', [
            'title' => 'Video kiểm thử',
            'visibility' => 'course',
            'file' => UploadedFile::fake()->create('video.mp4', 1024, 'video/mp4'),
        ], $this->adminHeaders());

        $response->assertCreated()->assertJsonPath('title', 'Video kiểm thử');
        $this->assertSame(1, VideoAsset::query()->count());
    }

    public function test_generate_playback_url_returns_signed_storage_url(): void
    {
        $asset = $this->videoAsset();
        $url = app(VideoAssetService::class)->generateSignedPlaybackUrl($asset);

        $this->assertStringContainsString('signature=', $url['url']);
        $this->assertSame('original', $url['delivery']);
    }

    public function test_heartbeat_updates_progress_summary(): void
    {
        $asset = $this->videoAsset(['duration_seconds' => 100]);
        $student = LmsUser::query()->where('user_type', 'student')->firstOrFail();
        $tracking = app(VideoTrackingService::class);

        $session = $tracking->startSession($asset, $student->id);
        $tracking->recordHeartbeat($session, 15, 15, 1);
        $tracking->recordHeartbeat($session->fresh(), 30, 15, 1);

        $this->assertDatabaseHas('video_progress_summaries', ['user_id' => $student->id, 'video_asset_id' => $asset->id, 'watch_percent' => 30]);
    }

    public function test_large_seek_is_marked_suspicious(): void
    {
        $asset = $this->videoAsset(['duration_seconds' => 1000]);
        $student = LmsUser::query()->where('user_type', 'student')->firstOrFail();
        $tracking = app(VideoTrackingService::class);
        $session = $tracking->startSession($asset, $student->id);

        $event = $tracking->recordSeek($session, 10, 900, ['elapsed_seconds' => 3]);

        $this->assertSame('suspicious', $event->event_type);
    }

    public function test_video_completed_only_after_required_percent(): void
    {
        $asset = $this->videoAsset(['duration_seconds' => 100]);
        $student = LmsUser::query()->where('user_type', 'student')->firstOrFail();
        $tracking = app(VideoTrackingService::class);

        $session = $tracking->startSession($asset, $student->id);
        foreach ([15, 30, 45, 60, 75] as $position) {
            $tracking->recordHeartbeat($session->fresh(), $position, 15, 1);
        }

        $this->assertDatabaseHas('video_progress_summaries', ['video_asset_id' => $asset->id, 'is_completed' => false]);
        $tracking->recordHeartbeat($session->fresh(), 90, 15, 1);
        $this->assertDatabaseHas('video_progress_summaries', ['video_asset_id' => $asset->id, 'is_completed' => true]);
    }

    public function test_antifake_detects_impossible_speed(): void
    {
        $this->assertTrue(app(VideoAntiFakeService::class)->detectImpossibleWatchSpeed(10, 2.5));
    }

    public function test_video_analytics_endpoint_returns_operational_summary(): void
    {
        $asset = $this->videoAsset(['processing_status' => 'ready']);
        $student = LmsUser::query()->where('user_type', 'student')->firstOrFail();

        VideoProgressSummary::query()->create([
            'tenant_id' => $asset->tenant_id,
            'user_id' => $student->id,
            'course_id' => $asset->course_id,
            'component_id' => $asset->component_id,
            'video_asset_id' => $asset->id,
            'total_duration_seconds' => 100,
            'watched_seconds' => 95,
            'max_position_seconds' => 95,
            'watch_percent' => 95,
            'is_completed' => true,
            'suspicious_score' => 0,
            'last_watched_at' => now(),
            'metadata' => [],
        ]);

        $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/videos/analytics')
            ->assertOk()
            ->assertJsonPath('summary.videos', 1)
            ->assertJsonPath('summary.ready', 1)
            ->assertJsonPath('summary.completion_rate', 100)
            ->assertJsonPath('progress.0.video_asset_id', $asset->id)
            ->assertJsonPath('top_videos.0.video_asset_id', $asset->id);
    }

    public function test_update_and_delete_video_asset_from_management_screen(): void
    {
        $asset = $this->videoAsset();

        $this->withHeaders($this->adminHeaders())
            ->putJson("/api/v1/videos/{$asset->id}", [
                'title' => 'Video đã chỉnh',
                'description' => 'Mô tả mới',
                'duration_seconds' => 720,
                'processing_status' => 'ready',
                'visibility' => 'tenant',
                'settings' => ['min_watch_percent' => 85, 'allow_download' => false],
            ])
            ->assertOk()
            ->assertJsonPath('title', 'Video đã chỉnh')
            ->assertJsonPath('visibility', 'tenant')
            ->assertJsonPath('settings.min_watch_percent', 85);

        $this->assertDatabaseHas('video_assets', ['id' => $asset->id, 'title' => 'Video đã chỉnh', 'visibility' => 'tenant']);

        $this->withHeaders($this->adminHeaders())
            ->deleteJson("/api/v1/videos/{$asset->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('video_assets', ['id' => $asset->id]);
    }

    private function videoAsset(array $overrides = []): VideoAsset
    {
        $tenant = Tenant::query()->where('code', 'VABIS')->firstOrFail();
        $component = CourseComponent::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $component->forceFill(['component_type' => 'video', 'config' => ['completion' => ['min_watch_percent' => 90, 'max_playback_rate_for_completion' => 1.5]]])->save();

        return VideoAsset::query()->create($overrides + [
            'tenant_id' => $tenant->id,
            'course_id' => $component->course_id,
            'component_id' => $component->id,
            'title' => 'Video kiểm thử',
            'original_filename' => 'video.mp4',
            'original_storage_path' => 'videos/demo/video.mp4',
            'duration_seconds' => 600,
            'file_size' => 1024,
            'mime_type' => 'video/mp4',
            'processing_status' => 'ready',
            'visibility' => 'course',
        ]);
    }

    private function adminHeaders(): array
    {
        return ['X-Tenant-Code' => 'VABIS', 'X-Demo-User-Email' => 'admin.lms@vabis.edu.vn'];
    }
}
