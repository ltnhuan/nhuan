<?php

namespace App\Services;

use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;

class AttendanceDurationService
{
    public function calculateForSession(AttendanceSession $session): array
    {
        $events = AttendanceEvent::query()->where('live_session_id', $session->live_session_id)->whereIn('event_type', ['joined','left'])->orderBy('created_at')->get()->groupBy('user_id');
        $updated = 0;
        foreach ($events as $userId => $userEvents) {
            $minutes = $this->minutesFromEvents($userEvents);
            $status = $minutes >= (int) ($session->settings['min_attendance_minutes'] ?? $session->liveSession?->min_attendance_minutes ?? 1) ? 'present' : ($minutes > 0 ? 'late' : 'absent');
            AttendanceRecord::query()->updateOrCreate(
                ['tenant_id' => $session->tenant_id, 'attendance_session_id' => $session->id, 'user_id' => (int) $userId],
                ['live_session_id' => $session->live_session_id, 'status' => $status, 'attended_minutes' => $minutes, 'source' => 'live_provider', 'metadata' => ['duration_calculated' => true]]
            );
            $updated++;
        }
        return ['updated' => $updated];
    }

    private function minutesFromEvents($events): int
    {
        $joinedAt = null; $seconds = 0;
        foreach ($events as $event) {
            if ($event->event_type === 'joined') $joinedAt = $event->created_at;
            if ($event->event_type === 'left' && $joinedAt) {
                $seconds += max(0, $joinedAt->diffInSeconds($event->created_at));
                $joinedAt = null;
            }
        }
        if ($joinedAt) $seconds += max(0, $joinedAt->diffInSeconds(now()));
        return (int) floor($seconds / 60);
    }
}
