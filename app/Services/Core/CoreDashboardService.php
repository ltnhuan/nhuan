<?php

namespace App\Services\Core;

use App\Models\AcademicUnit;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\LmsUser;
use App\Models\Tenant;

class CoreDashboardService
{
    public function summary(int $tenantId): array
    {
        return [
            'tenant' => Tenant::query()->find($tenantId),
            'campuses_count' => Campus::query()->where('tenant_id', $tenantId)->count(),
            'academic_units_count' => AcademicUnit::query()->where('tenant_id', $tenantId)->count(),
            'students_count' => LmsUser::query()->where('tenant_id', $tenantId)->where('user_type', 'student')->count(),
            'teachers_staff_count' => LmsUser::query()->where('tenant_id', $tenantId)->whereIn('user_type', ['teacher', 'staff', 'admin'])->count(),
            'locked_users_count' => LmsUser::query()->where('tenant_id', $tenantId)->where('status', 'locked')->count(),
            'recent_audit_logs' => AuditLog::query()->where('tenant_id', $tenantId)->latest('created_at')->limit(10)->get(),
        ];
    }
}
