<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\LmsUser;
use App\Models\Tenant;
use App\Models\VideoAsset;
use App\Models\VideoProgressSummary;
use App\Models\VideoRendition;
use App\Models\VideoWatchEvent;
use App\Models\VideoWatchSession;
use Illuminate\Database\Seeder;

class VideoPlatformSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('code', 'VABIS')->firstOrFail();
        $teacherId = LmsUser::query()->where('tenant_id', $tenant->id)->where('user_type', 'teacher')->value('id') ?: 1;
        $courses = Course::query()->where('tenant_id', $tenant->id)->take(20)->get();
        $components = CourseComponent::query()->where('tenant_id', $tenant->id)->whereIn('course_id', $courses->pluck('id'))->take(20)->get();

        for ($i = 1; $i <= 30; $i++) {
            $course = $courses[($i - 1) % max(1, $courses->count())] ?? null;
            $component = $components[($i - 1) % max(1, $components->count())] ?? null;
            $asset = VideoAsset::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'title' => "Video bài giảng {$i}"],
                [
                    'course_id' => $course?->id,
                    'component_id' => $i <= 20 ? $component?->id : null,
                    'description' => "Video đào tạo mẫu số {$i} dùng cho kiểm thử nền tảng video.",
                    'original_filename' => "video-bai-giang-{$i}.mp4",
                    'original_storage_path' => "videos/demo/video-bai-giang-{$i}.mp4",
                    'hls_master_path' => $i % 3 === 0 ? "videos/hls/{$tenant->id}/{$i}/master.m3u8" : null,
                    'duration_seconds' => 480 + ($i * 20),
                    'file_size' => 50_000_000 + ($i * 1_000_000),
                    'mime_type' => 'video/mp4',
                    'processing_status' => 'ready',
                    'visibility' => 'course',
                    'checksum' => hash('sha256', "eralms-video-demo-{$i}"),
                    'thumbnail_url' => 'https://placehold.co/1280x720/0f172a/ffffff?text='.rawurlencode("Video {$i}"),
                    'settings' => ['seed' => true, 'hls_ready' => $i % 3 === 0],
                    'uploaded_by' => $teacherId,
                    'processed_at' => now(),
                ]
            );

            if ($asset->hls_master_path) {
                foreach ([['360p', 640, 360, 800], ['720p', 1280, 720, 2500]] as [$quality, $width, $height, $bitrate]) {
                    VideoRendition::query()->updateOrCreate(
                        ['tenant_id' => $tenant->id, 'video_asset_id' => $asset->id, 'quality' => $quality],
                        ['width' => $width, 'height' => $height, 'bitrate' => $bitrate, 'hls_path' => "videos/hls/{$tenant->id}/{$asset->id}/{$quality}.m3u8", 'status' => 'ready']
                    );
                }
            }

            if ($asset->component_id) {
                $component?->forceFill([
                    'component_type' => 'video',
                    'config' => array_replace_recursive($component->config ?? [], [
                        'video_asset_id' => $asset->id,
                        'completion' => ['required' => true, 'min_watch_percent' => 90, 'allow_fast_forward' => false, 'max_playback_rate_for_completion' => 1.5],
                    ]),
                ])->save();
            }
        }

        $students = LmsUser::query()->where('tenant_id', $tenant->id)->where('user_type', 'student')->take(500)->get();
        $demoAssets = VideoAsset::query()->where('tenant_id', $tenant->id)->whereNotNull('component_id')->take(10)->get();

        foreach ($students as $index => $student) {
            foreach ($demoAssets->take(3) as $asset) {
                $duration = max(1, (int) $asset->duration_seconds);
                $mode = $index % 4;
                $percent = [0, 38, 95, 72][$mode];
                $suspicious = $mode === 3 ? 75 : 0;
                $watched = round($duration * $percent / 100, 2);

                $sessionUuid = sprintf('00000000-0000-4000-8000-%012d', ($student->id * 1000) + $asset->id);
                $session = VideoWatchSession::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'user_id' => $student->id, 'video_asset_id' => $asset->id],
                    [
                        'session_uuid' => $sessionUuid,
                        'course_id' => $asset->course_id,
                        'component_id' => $asset->component_id,
                        'started_at' => now()->subDays(rand(0, 10)),
                        'ended_at' => $percent > 0 ? now()->subHours(rand(1, 24)) : null,
                        'last_position_seconds' => $watched,
                        'max_position_seconds' => $watched,
                        'watched_seconds' => $watched,
                        'watch_percent' => $percent,
                        'playback_rate' => 1,
                        'status' => $suspicious > 0 ? 'suspicious' : 'ended',
                        'metadata' => ['seed' => true],
                    ]
                );

                VideoWatchEvent::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'session_id' => $session->id, 'event_type' => 'heartbeat', 'position_seconds' => $watched],
                    ['user_id' => $student->id, 'course_id' => $asset->course_id, 'component_id' => $asset->component_id, 'video_asset_id' => $asset->id, 'watched_delta_seconds' => min(15, $watched), 'playback_rate' => 1, 'metadata' => ['seed' => true]]
                );

                VideoProgressSummary::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'user_id' => $student->id, 'component_id' => $asset->component_id, 'video_asset_id' => $asset->id],
                    ['course_id' => $asset->course_id, 'total_duration_seconds' => $duration, 'watched_seconds' => $watched, 'max_position_seconds' => $watched, 'watch_percent' => $percent, 'is_completed' => $percent >= 90 && $suspicious < 50, 'completed_at' => $percent >= 90 && $suspicious < 50 ? now() : null, 'suspicious_score' => $suspicious, 'last_watched_at' => now(), 'metadata' => ['seed' => true]]
                );
            }
        }
    }
}
