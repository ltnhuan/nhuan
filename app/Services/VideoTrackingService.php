<?php

namespace App\Services;

use App\Models\CourseComponent;
use App\Models\VideoAsset;
use App\Models\VideoProgressSummary;
use App\Models\VideoWatchEvent;
use App\Models\VideoWatchSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VideoTrackingService
{
    public function __construct(
        private readonly VideoAntiFakeService $antiFake,
        private readonly LearningPathIntegrationService $learningPath,
    ) {
    }

    public function startSession(VideoAsset $asset, int $userId, ?Request $request = null): VideoWatchSession
    {
        return VideoWatchSession::query()->create([
            'tenant_id' => $asset->tenant_id,
            'user_id' => $userId,
            'course_id' => $asset->course_id,
            'component_id' => $asset->component_id,
            'video_asset_id' => $asset->id,
            'session_uuid' => (string) Str::uuid(),
            'started_at' => now(),
            'status' => 'active',
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'device_id' => $request?->input('device_id'),
            'metadata' => ['source' => 'server'],
        ]);
    }

    public function recordEvent(VideoWatchSession $session, string $eventType, float $positionSeconds, float $watchedDeltaSeconds = 0, float $playbackRate = 1, array $metadata = []): VideoWatchEvent
    {
        $signals = [
            'large_jump' => $eventType === 'seek' && $this->antiFake->detectLargeJump($session, $positionSeconds, $metadata),
            'impossible_speed' => $this->antiFake->detectImpossibleWatchSpeed($watchedDeltaSeconds, $playbackRate),
            'duplicate_heartbeat' => $eventType === 'heartbeat' && $this->antiFake->detectDuplicateHeartbeat($session, $positionSeconds),
            'too_many_tab_hidden' => $eventType === 'tab_hidden' && $this->antiFake->detectTooManyTabHiddenEvents($session),
            'tab_hidden_delta' => ($metadata['tab_hidden'] ?? false) && $watchedDeltaSeconds > 0,
        ];
        $suspiciousScore = $this->antiFake->calculateSuspiciousScore($signals);
        $safeDelta = $suspiciousScore > 0 ? 0 : max(0, min($watchedDeltaSeconds, 15));

        $event = VideoWatchEvent::query()->create([
            'tenant_id' => $session->tenant_id,
            'user_id' => $session->user_id,
            'course_id' => $session->course_id,
            'component_id' => $session->component_id,
            'video_asset_id' => $session->video_asset_id,
            'session_id' => $session->id,
            'event_type' => $suspiciousScore > 0 ? 'suspicious' : $eventType,
            'position_seconds' => max(0, $positionSeconds),
            'watched_delta_seconds' => $safeDelta,
            'playback_rate' => $playbackRate,
            'metadata' => $metadata + ['signals' => $signals, 'suspicious_score' => $suspiciousScore],
        ]);

        if ($suspiciousScore > 0) {
            $this->antiFake->markSessionSuspicious($session, $suspiciousScore, $signals);
        }

        $session->forceFill([
            'last_position_seconds' => max(0, $positionSeconds),
            'max_position_seconds' => max((float) $session->max_position_seconds, $positionSeconds),
            'watched_seconds' => (float) $session->watched_seconds + $safeDelta,
            'playback_rate' => $playbackRate,
        ])->save();

        $this->updateProgressSummary($session, $suspiciousScore);

        return $event;
    }

    public function recordHeartbeat(VideoWatchSession $session, float $positionSeconds, float $deltaSeconds, float $playbackRate = 1, array $metadata = []): VideoWatchEvent
    {
        return $this->recordEvent($session, 'heartbeat', $positionSeconds, $deltaSeconds, $playbackRate, $metadata);
    }

    public function recordSeek(VideoWatchSession $session, float $fromSeconds, float $toSeconds, array $metadata = []): VideoWatchEvent
    {
        return $this->recordEvent($session, 'seek', $toSeconds, 0, (float) $session->playback_rate, $metadata + ['from_seconds' => $fromSeconds]);
    }

    public function endSession(VideoWatchSession $session, float $positionSeconds): VideoWatchSession
    {
        $this->recordEvent($session, 'ended', $positionSeconds, 0, (float) $session->playback_rate);
        $session->forceFill(['ended_at' => now(), 'status' => $session->status === 'suspicious' ? 'suspicious' : 'ended'])->save();

        return $session;
    }

    public function updateProgressSummary(VideoWatchSession $session, float $additionalSuspiciousScore = 0): VideoProgressSummary
    {
        $asset = VideoAsset::query()->findOrFail($session->video_asset_id);
        $duration = max(1, (int) ($asset->duration_seconds ?: config('eralms.video.default_duration_seconds', 600)));
        $existing = VideoProgressSummary::query()->firstOrNew([
            'tenant_id' => $session->tenant_id,
            'user_id' => $session->user_id,
            'component_id' => $session->component_id,
            'video_asset_id' => $session->video_asset_id,
        ]);

        $watched = max((float) $existing->watched_seconds, (float) $session->watched_seconds);
        $score = min(100, (float) $existing->suspicious_score + $additionalSuspiciousScore);
        $percent = $this->calculateWatchPercent($watched, $duration);
        $isCompleted = $this->verifyVideoCompletion($session->component_id, $percent, $score, (float) $session->playback_rate);

        $existing->fill([
            'course_id' => $session->course_id,
            'total_duration_seconds' => $duration,
            'watched_seconds' => $watched,
            'max_position_seconds' => max((float) $existing->max_position_seconds, (float) $session->max_position_seconds),
            'watch_percent' => $percent,
            'is_completed' => $isCompleted,
            'completed_at' => $isCompleted && ! $existing->completed_at ? now() : $existing->completed_at,
            'suspicious_score' => $score,
            'last_watched_at' => now(),
            'metadata' => ['server_verified' => true],
        ])->save();

        $this->learningPath->completeVideoComponentIfEligible($existing);

        return $existing;
    }

    public function calculateWatchPercent(float $watchedSeconds, int $durationSeconds): float
    {
        return round(min(100, ($watchedSeconds / max(1, $durationSeconds)) * 100), 2);
    }

    public function verifyVideoCompletion(int $componentId, float $watchPercent, float $suspiciousScore, float $playbackRate): bool
    {
        $component = CourseComponent::query()->find($componentId);
        $completion = $component?->config['completion'] ?? [];

        return $watchPercent >= (float) ($completion['min_watch_percent'] ?? config('eralms.video.default_completion_percent', 90))
            && $suspiciousScore < (float) config('eralms.video.max_suspicious_score_for_completion', 50)
            && $playbackRate <= (float) ($completion['max_playback_rate_for_completion'] ?? 1.5);
    }
}
