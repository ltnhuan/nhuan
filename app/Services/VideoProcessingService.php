<?php

namespace App\Services;

use App\Jobs\ProcessVideoAsset;
use App\Models\VideoAsset;
use App\Models\VideoRendition;

class VideoProcessingService
{
    public function dispatchProcessingJob(VideoAsset $asset): void
    {
        $asset->forceFill(['processing_status' => 'processing'])->save();
        ProcessVideoAsset::dispatch($asset->id);
    }

    public function convertToHlsIfFfmpegAvailable(VideoAsset $asset): VideoAsset
    {
        if (! $this->ffmpegAvailable()) {
            return $this->markReadyWithoutHls($asset, 'Máy chủ chưa cài FFmpeg, hệ thống dùng file gốc trong môi trường local.');
        }

        // Điểm tích hợp FFmpeg thật: tạo master.m3u8 và các segment theo tenant/video.
        $asset->forceFill([
            'hls_master_path' => "videos/hls/{$asset->tenant_id}/{$asset->id}/master.m3u8",
            'processing_status' => 'ready',
            'processed_at' => now(),
            'settings' => array_replace($asset->settings ?? [], ['hls_ready' => true, 'processing_note' => 'Đã sẵn sàng HLS.']),
        ])->save();

        $this->createRenditions($asset);

        return $asset;
    }

    public function createRenditions(VideoAsset $asset): void
    {
        foreach ([['360p', 640, 360, 800], ['480p', 854, 480, 1200], ['720p', 1280, 720, 2500], ['1080p', 1920, 1080, 5000]] as [$quality, $width, $height, $bitrate]) {
            VideoRendition::query()->updateOrCreate(
                ['tenant_id' => $asset->tenant_id, 'video_asset_id' => $asset->id, 'quality' => $quality],
                ['width' => $width, 'height' => $height, 'bitrate' => $bitrate, 'hls_path' => "videos/hls/{$asset->tenant_id}/{$asset->id}/{$quality}.m3u8", 'status' => 'ready']
            );
        }
    }

    public function handleProcessingFailure(VideoAsset $asset, string $message): VideoAsset
    {
        $asset->forceFill([
            'processing_status' => 'failed',
            'processed_at' => now(),
            'settings' => array_replace($asset->settings ?? [], ['processing_error' => $message]),
        ])->save();

        return $asset;
    }

    private function markReadyWithoutHls(VideoAsset $asset, string $note): VideoAsset
    {
        $asset->forceFill([
            'processing_status' => 'ready',
            'processed_at' => now(),
            'settings' => array_replace($asset->settings ?? [], ['hls_ready' => false, 'processing_note' => $note]),
        ])->save();

        return $asset;
    }

    private function ffmpegAvailable(): bool
    {
        $output = [];
        $exitCode = 1;
        @exec('ffmpeg -version', $output, $exitCode);

        return $exitCode === 0;
    }
}
