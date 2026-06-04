<?php

namespace App\Services;

use App\Models\LearningProgressEvent;
use Illuminate\Http\Request;

class LearningEventService
{
    public function appendEvent(array $data, ?Request $request = null): LearningProgressEvent
    {
        $data['ip_address'] ??= $request?->ip();
        $data['user_agent'] ??= $request?->userAgent();
        $data['metadata'] = array_merge($data['metadata'] ?? [], ['suspicious' => $this->detectFakeProgress($data)]);
        return LearningProgressEvent::query()->create($data);
    }

    public function batchAppendEvents(array $events, ?Request $request = null): int
    {
        foreach ($events as $event) {
            $this->appendEvent($event, $request);
        }
        return count($events);
    }

    public function summarizeEventsToProgress(int $tenantId, int $userId, int $courseId): array
    {
        $completed = LearningProgressEvent::query()->where('tenant_id', $tenantId)->where('user_id', $userId)->where('course_id', $courseId)->where('event_type', 'component_completed')->distinct('component_id')->count('component_id');
        return ['completed_components_count' => $completed];
    }

    public function detectFakeProgress(array $event): bool
    {
        $metadata = $event['metadata'] ?? [];
        $jump = ($event['event_type'] ?? null) === 'video_progress'
            && (float) ($event['event_value'] ?? 0) >= 90
            && (float) ($metadata['previous_percent'] ?? 0) <= 10
            && (int) ($metadata['elapsed_seconds'] ?? 999) < 20;
        $duplicate = (int) ($metadata['duplicate_count'] ?? 0) > 5;
        return $jump || $duplicate;
    }
}
