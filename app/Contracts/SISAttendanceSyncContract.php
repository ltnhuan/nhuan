<?php

namespace App\Contracts;

use App\Models\AttendanceSession;

interface SISAttendanceSyncContract
{
    public function syncAttendanceSession(AttendanceSession $session): array;
}
