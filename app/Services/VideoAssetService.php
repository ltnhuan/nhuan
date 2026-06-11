<?php

namespace App\Services;

use App\Models\CourseComponent;
use App\Models\VideoAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VideoAssetService
{
    public function uploadVideo(array $data, UploadedFile $file): VideoAsset
    {
        $disk = config('eralms.video.storage_disk', 'public');
        $path = $file->store("videos/original/{$data['tenant_id']}", $disk);

        // Checksum được lưu để phát hiện file trùng và đối soát nội dung sau xử lý HLS.
        $checksum = hash_file('sha256', $file->getRealPath());

        return VideoAsset::query()->create([
            'tenant_id' => $data['tenant_id'],
            'course_id' => $data['course_id'] ?? null,
            'component_id' => $data['component_id'] ?? null,
            'repository_item_id' => $data['repository_item_id'] ?? null,
            'title' => $data['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'description' => $data['description'] ?? null,
            'original_filename' => $file->getClientOriginalName(),
            'original_storage_path' => $path,
            'file_size' => $file->getSize() ?: 0,
            'mime_type' => $file->getMimeType(),
            'processing_status' => 'pending',
            'visibility' => $data['visibility'] ?? 'private',
            'checksum' => $checksum,
            'thumbnail_url' => $this->generateThumbnailPlaceholder($data['title'] ?? $file->getClientOriginalName()),
            'settings' => $data['settings'] ?? ['hls_ready' => false],
            'uploaded_by' => $data['uploaded_by'] ?? null,
        ]);
    }

    public function attachVideoToComponent(VideoAsset $asset, CourseComponent $component): VideoAsset
    {
        $asset->forceFill([
            'course_id' => $component->course_id,
            'component_id' => $component->id,
            'visibility' => 'course',
        ])->save();

        $component->forceFill([
            'component_type' => 'video',
            'config' => array_replace_recursive($component->config ?? [], [
                'video_asset_id' => $asset->id,
                'completion' => [
                    'required' => true,
                    'min_watch_percent' => config('eralms.video.default_completion_percent', 90),
                    'allow_fast_forward' => false,
                    'max_playback_rate_for_completion' => 1.5,
                ],
            ]),
        ])->save();

        return $asset->fresh(['component']);
    }

    public function generateSignedPlaybackUrl(VideoAsset $asset): array
    {
        $path = $asset->hls_master_path ?: $asset->original_storage_path;
        $expiresAt = now()->addSeconds((int) config('eralms.video.signed_url_ttl', 1800));
        $baseUrl = rtrim((string) config('eralms.video.cdn_url'), '/');
        $url = $baseUrl !== ''
            ? "{$baseUrl}/".ltrim($path, '/')
            : Storage::disk(config('eralms.video.storage_disk', 'public'))->url($path);

        // URL được ký bằng HMAC để sẵn sàng chuyển sang CDN có kiểm tra token ở edge.
        $signature = hash_hmac('sha256', "{$asset->id}|{$path}|{$expiresAt->timestamp}", config('app.key', 'eralms'));

        return [
            'url' => $url.'?expires='.$expiresAt->timestamp.'&signature='.$signature,
            'expires_at' => $expiresAt->toISOString(),
            'delivery' => $asset->hls_master_path ? 'hls' : 'original',
            'cdn_ready' => $baseUrl !== '',
        ];
    }

    public function generateThumbnailPlaceholder(string $title): string
    {
        return 'https://placehold.co/1280x720/0f172a/ffffff?text='.rawurlencode(Str::limit($title, 48, ''));
    }

    public function updateProcessingStatus(VideoAsset $asset, string $status, array $metadata = []): VideoAsset
    {
        $asset->forceFill([
            'processing_status' => $status,
            'processed_at' => in_array($status, ['ready', 'failed'], true) ? now() : null,
            'settings' => array_replace($asset->settings ?? [], $metadata),
        ])->save();

        return $asset;
    }

    public function getVideoForLesson(CourseComponent $component): ?VideoAsset
    {
        $assetId = $component->config['video_asset_id'] ?? null;

        return $assetId ? VideoAsset::query()->where('component_id', $component->id)->find($assetId) : null;
    }
}
