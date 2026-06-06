<?php

namespace App\Services;

use App\Models\VideoWatchEvent;
use App\Models\VideoWatchSession;

class VideoAntiFakeService
{
    public function detectLargeJump(VideoWatchSession $session, float $positionSeconds, array $metadata = []): bool
    {
        $elapsed = max(1, (int) ($metadata['elapsed_seconds'] ?? 1));
        $jump = $positionSeconds - (float) $session->last_position_seconds;

        return $jump > 120 && $elapsed < 10;
    }

    public function detectImpossibleWatchSpeed(float $deltaSeconds, float $playbackRate): bool
    {
        return $playbackRate > 2.0 || $deltaSeconds > 20;
    }

    public function detectDuplicateHeartbeat(VideoWatchSession $session, float $positionSeconds): bool
    {
        return VideoWatchEvent::query()
            ->where('session_id', $session->id)
            ->where('event_type', 'heartbeat')
            ->where('position_seconds', $positionSeconds)
            ->where('created_at', '>=', now()->subSeconds(15))
            ->exists();
    }

    public function detectTooManyTabHiddenEvents(VideoWatchSession $session): bool
    {
        return VideoWatchEvent::query()
            ->where('session_id', $session->id)
            ->where('event_type', 'tab_hidden')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count() >= 5;
    }

    public function calculateSuspiciousScore(array $signals): float
    {
        return collect($signals)->filter()->count() * 25.0;
    }

    public function markSessionSuspicious(VideoWatchSession $session, float $score, array $signals): void
    {
        $session->forceFill([
            'status' => 'suspicious',
            'metadata' => array_replace($session->metadata ?? [], ['suspicious_score' => $score, 'signals' => $signals]),
        ])->save();
    }
}
