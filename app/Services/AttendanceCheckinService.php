<?php

namespace App\Services;

use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;

class AttendanceCheckinService
{
    public function __construct(private AttendanceSessionService $sessions) {}

    public function checkinQr(string $token, int $userId): AttendanceRecord
    {
        $session = AttendanceSession::query()->where('qr_token', $token)->firstOrFail();
        $this->sessions->ensureOpen($session);
        return $this->writeRecord($session, $userId, 'present', 'qr', null, null, 'qr_scan');
    }

    public function checkinOtp(int $sessionId, string $otp, int $userId): AttendanceRecord
    {
        $session = AttendanceSession::query()->findOrFail($sessionId);
        $this->sessions->ensureOpen($session);
        if (! hash_equals((string) $session->otp_code, $otp)) throw new \RuntimeException('OTP không hợp lệ.');
        return $this->writeRecord($session, $userId, 'present', 'otp', null, null, 'otp_submit');
    }

    public function manualUpdate(AttendanceSession $session, int $userId, string $status, int $actorId, ?string $note = null, bool $allowLockedOverride = false): AttendanceRecord
    {
        $this->sessions->ensureEditable($session, $allowLockedOverride);
        return $this->writeRecord($session, $userId, $status, 'manual', $actorId, $note, 'manual_update');
    }

    public function writeRecord(AttendanceSession $session, int $userId, string $status, string $source, ?int $actorId = null, ?string $note = null, string $event = 'checkin', int $minutes = 0): AttendanceRecord
    {
        $now = now();
        $record = AttendanceRecord::query()->updateOrCreate(
            ['tenant_id' => $session->tenant_id, 'attendance_session_id' => $session->id, 'user_id' => $userId],
            ['live_session_id' => $session->live_session_id, 'status' => $status, 'checkin_at' => $status === 'absent' ? null : $now, 'attended_minutes' => $minutes, 'source' => $source, 'note' => $note, 'verified_by' => $actorId, 'metadata' => ['updated_by_source' => $source]]
        );
        AttendanceEvent::query()->create(['tenant_id' => $session->tenant_id, 'attendance_session_id' => $session->id, 'live_session_id' => $session->live_session_id, 'user_id' => $userId, 'event_type' => $event, 'metadata' => ['status' => $status, 'source' => $source], 'created_at' => $now]);
        return $record->fresh();
    }
}
