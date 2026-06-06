<?php

namespace App\Jobs;

use App\Models\VideoAsset;
use App\Services\VideoProcessingService;
use App\Support\PerformanceQueues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessVideoAsset implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $videoAssetId)
    {
        $this->onQueue(PerformanceQueues::VIDEO_PROCESSING);
    }

    public function handle(VideoProcessingService $processing): void
    {
        $asset = VideoAsset::query()->findOrFail($this->videoAssetId);

        try {
            $processing->convertToHlsIfFfmpegAvailable($asset);
        } catch (\Throwable $exception) {
            $processing->handleProcessingFailure($asset, $exception->getMessage());
        }
    }
}
