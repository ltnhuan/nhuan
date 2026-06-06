<?php

namespace App\Services;

use App\Models\AttendanceEvent;
use App\Models\LiveSession;

class LiveSessionService
{
    public function create(array $data): LiveSession
    {
        return LiveSession::query()->create($data + ['provider' => 'manual', 'status' => 'scheduled', 'attendance_required' => true, 'settings' => []]);
    }

    public function update(LiveSession $session, array $data): LiveSession
    {
        if ($session->status === 'completed') throw new \RuntimeException('Buổi học đã hoàn tất, không thể chỉnh sửa.');
        $session->fill($data)->save();
        return $session->fresh();
    }

    public function start(LiveSession $session): LiveSession
    {
        $session->forceFill(['status' => 'live'])->save();
        return $session->fresh();
    }

    public function complete(LiveSession $session): LiveSession
    {
        $session->forceFill(['status' => 'completed'])->save();
        return $session->fresh();
    }

    public function cancel(LiveSession $session): LiveSession
    {
        $session->forceFill(['status' => 'cancelled'])->save();
        return $session->fresh();
    }

    public function recordProviderEvent(LiveSession $session, int $userId, string $eventType, array $metadata = []): AttendanceEvent
    {
        return AttendanceEvent::query()->create(['tenant_id' => $session->tenant_id, 'live_session_id' => $session->id, 'user_id' => $userId, 'event_type' => $eventType, 'metadata' => $metadata, 'created_at' => now()]);
    }
}
