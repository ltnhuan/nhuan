<?php

namespace App\Services;

use App\Contracts\SISAttendanceSyncContract;
use App\Models\AttendanceSession;

class NullSISAttendanceSyncService implements SISAttendanceSyncContract
{
    public function syncAttendanceSession(AttendanceSession $session): array
    {
        return ['provider' => 'null', 'attendance_session_id' => $session->id, 'synced' => true];
    }
}
