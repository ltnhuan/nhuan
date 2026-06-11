<?php

namespace App\Services;

use App\Models\AttendanceEvent;
use App\Models\AttendanceSession;
use Illuminate\Support\Str;

class AttendanceSessionService
{
    public function create(array $data): AttendanceSession
    {
        return AttendanceSession::query()->create($data + ['attendance_type' => 'qr', 'status' => 'draft', 'settings' => []]);
    }

    public function open(AttendanceSession $session): AttendanceSession
    {
        if ($session->status === 'locked') throw new \RuntimeException('Phiên điểm danh đã khóa.');
        $payload = ['status' => 'open'];
        if (in_array($session->attendance_type, ['qr','auto'], true) && ! $session->qr_token) $payload['qr_token'] = Str::random(40);
        if (in_array($session->attendance_type, ['otp','auto'], true) && ! $session->otp_code) $payload['otp_code'] = (string) random_int(100000, 999999);
        $session->forceFill($payload)->save();
        return $session->fresh();
    }

    public function close(AttendanceSession $session): AttendanceSession
    {
        if ($session->status === 'locked') throw new \RuntimeException('Phiên điểm danh đã khóa.');
        $session->forceFill(['status' => 'closed'])->save();
        return $session->fresh();
    }

    public function lock(AttendanceSession $session, int $actorId): AttendanceSession
    {
        $session->forceFill(['status' => 'locked', 'locked_by' => $actorId, 'locked_at' => now()])->save();
        AttendanceEvent::query()->create(['tenant_id' => $session->tenant_id, 'attendance_session_id' => $session->id, 'user_id' => $actorId, 'event_type' => 'locked', 'metadata' => ['actor_id' => $actorId], 'created_at' => now()]);
        return $session->fresh();
    }

    public function ensureEditable(AttendanceSession $session, bool $allowLockedOverride = false): void
    {
        if ($session->status === 'locked' && ! $allowLockedOverride) throw new \RuntimeException('Phiên điểm danh đã khóa, không thể chỉnh sửa.');
    }

    public function ensureOpen(AttendanceSession $session): void
    {
        if ($session->status !== 'open') throw new \RuntimeException('Phiên điểm danh chưa mở.');
        $now = now();
        if ($session->open_at && $now->lt($session->open_at)) throw new \RuntimeException('Chưa đến giờ điểm danh.');
        if ($session->close_at && $now->gt($session->close_at)) throw new \RuntimeException('Phiên điểm danh đã hết hạn.');
    }
}
