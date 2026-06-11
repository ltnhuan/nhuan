<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\LmsUser;
use App\Models\QuestionBank;
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
        $questionBanks = QuestionBank::query()->where('tenant_id', $tenant->id)->take(12)->get();
        $sourceTypes = ['uploaded_file', 'hls', 'external_url', 'youtube'];
        $encodingProfiles = ['adaptive-hls-720p', 'adaptive-hls-1080p', 'mp4-progressive-720p', 'secure-hls-low-latency'];

        for ($i = 1; $i <= 30; $i++) {
            $course = $courses[($i - 1) % max(1, $courses->count())] ?? null;
            $component = $components[($i - 1) % max(1, $components->count())] ?? null;
            $bank = $questionBanks->isNotEmpty() ? $questionBanks[($i - 1) % $questionBanks->count()] : null;
            $extraBank = $questionBanks->isNotEmpty() ? $questionBanks[$i % $questionBanks->count()] : null;
            $sourceType = $sourceTypes[($i - 1) % count($sourceTypes)];
            $encodingProfile = $encodingProfiles[($i - 1) % count($encodingProfiles)];
            $completionPercent = [80, 85, 90, 95][$i % 4];
            $sourceUrl = match ($sourceType) {
                'external_url' => "https://cdn-demo.eralms.local/video-demo-{$i}/master.m3u8",
                'youtube' => "https://www.youtube.com/watch?v=eralms-demo-{$i}",
                'hls' => "videos/hls/{$tenant->id}/{$i}/master.m3u8",
                default => null,
            };
            $asset = VideoAsset::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'title' => "Video học liệu nâng cao {$i}"],
                [
                    'course_id' => $course?->id,
                    'component_id' => $i <= 20 ? $component?->id : null,
                    'description' => "Video mẫu {$i} có cấu hình nguồn {$sourceType}, profile {$encodingProfile}, tracking hoàn thành {$completionPercent}% và mapping ngân hàng đề.",
                    'original_filename' => "video-hoc-lieu-nang-cao-{$i}.mp4",
                    'original_storage_path' => "videos/demo/video-hoc-lieu-nang-cao-{$i}.mp4",
                    'hls_master_path' => in_array($sourceType, ['hls', 'uploaded_file'], true) || str_contains($encodingProfile, 'hls') ? "videos/hls/{$tenant->id}/{$i}/master.m3u8" : null,
                    'duration_seconds' => 480 + ($i * 20),
                    'file_size' => 50_000_000 + ($i * 1_000_000),
                    'mime_type' => 'video/mp4',
                    'processing_status' => $i % 11 === 0 ? 'processing' : ($i % 13 === 0 ? 'failed' : 'ready'),
                    'visibility' => ['private', 'course', 'tenant', 'public'][$i % 4],
                    'checksum' => hash('sha256', "eralms-video-demo-{$i}"),
                    'thumbnail_url' => 'https://placehold.co/1280x720/0f172a/ffffff?text='.rawurlencode("Video LMS {$i}"),
                    'subtitle_path' => "videos/subtitles/{$tenant->id}/video-{$i}.vi.vtt",
                    'transcript_path' => "videos/transcripts/{$tenant->id}/video-{$i}.txt",
                    'settings' => [
                        'seed' => true,
                        'source_type' => $sourceType,
                        'source_url' => $sourceUrl,
                        'source_profile' => match ($sourceType) {
                            'youtube' => 'YouTube external embed',
                            'external_url' => 'CDN external HLS',
                            'hls' => 'Internal HLS master',
                            default => 'MP4 upload converted to HLS',
                        },
                        'encoding_profile' => $encodingProfile,
                        'drm_policy' => $i % 2 === 0 ? 'signed-url' : 'tenant-token',
                        'cdn_region' => $i % 2 === 0 ? 'ap-southeast' : 'vn-edge',
                        'min_watch_percent' => $completionPercent,
                        'max_playback_rate_for_completion' => $i % 3 === 0 ? 1.25 : 1.5,
                        'allow_seek' => $i % 5 !== 0,
                        'allow_download' => $i % 7 === 0,
                        'require_heartbeat' => true,
                        'heartbeat_interval_seconds' => 12,
                        'anti_fake_level' => $i % 4 === 0 ? 'strict' : 'standard',
                        'captions_required' => true,
                        'transcript_required' => $i % 2 === 0,
                        'question_bank_ids' => collect([$bank?->id, $extraBank?->id])->filter()->unique()->values()->all(),
                        'exam_blueprints' => ["MIDTERM-BP-".str_pad((string) (($i % 5) + 1), 2, '0', STR_PAD_LEFT)],
                        'learning_outcomes' => ["CLO".(($i % 4) + 1), "PLO".(($i % 3) + 1)],
                    ],
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
                        'completion' => ['required' => true, 'min_watch_percent' => $completionPercent, 'allow_fast_forward' => false, 'max_playback_rate_for_completion' => $asset->settings['max_playback_rate_for_completion'] ?? 1.5],
                        'question_bank_ids' => $asset->settings['question_bank_ids'] ?? [],
                        'video_source_type' => $sourceType,
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
