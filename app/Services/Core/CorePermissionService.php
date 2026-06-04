<?php

namespace App\Services\Core;

use App\Models\LmsUser;
use App\Models\Permission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CorePermissionService
{
    public function permissionsForUser(LmsUser $user, int $tenantId): Collection
    {
        return Cache::remember($this->cacheKey($user->id, $tenantId), 300, function () use ($user, $tenantId) {
            return DB::table('user_role_scope')
                ->join('roles', 'user_role_scope.role_id', '=', 'roles.id')
                ->join('role_permission', 'roles.id', '=', 'role_permission.role_id')
                ->join('permissions', 'role_permission.permission_id', '=', 'permissions.id')
                ->where('user_role_scope.user_id', $user->id)
                ->where(function ($query) use ($tenantId) {
                    $query->whereNull('user_role_scope.tenant_id')
                        ->orWhere('user_role_scope.tenant_id', $tenantId);
                })
                ->select([
                    'permissions.key',
                    'permissions.module',
                    'permissions.action',
                    'roles.scope',
                    'user_role_scope.tenant_id',
                    'user_role_scope.campus_id',
                    'user_role_scope.academic_unit_id',
                    'user_role_scope.course_id',
                    'user_role_scope.class_id',
                ])
                ->get();
        });
    }

    public function can(LmsUser $user, string $permissionKey, array $scope): bool
    {
        $tenantId = (int) ($scope['tenant_id'] ?? $user->tenant_id);

        return $this->permissionsForUser($user, $tenantId)
            ->where('key', $permissionKey)
            ->contains(fn ($permission) => $this->scopeMatches((array) $permission, $scope));
    }

    public function clearUserCache(int $userId, int $tenantId): void
    {
        Cache::forget($this->cacheKey($userId, $tenantId));
        Cache::forget("eralms:menu:{$tenantId}:{$userId}");
    }

    public function syncRolePermissions(int $roleId, array $permissionKeys): void
    {
        $permissionIds = Permission::query()->whereIn('key', $permissionKeys)->pluck('id')->all();

        DB::transaction(function () use ($roleId, $permissionIds) {
            DB::table('role_permission')->where('role_id', $roleId)->delete();
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        });
    }

    private function scopeMatches(array $permission, array $scope): bool
    {
        foreach (['tenant_id', 'campus_id', 'academic_unit_id', 'course_id', 'class_id'] as $key) {
            if (! array_key_exists($key, $scope) || $scope[$key] === null) {
                continue;
            }

            if ($permission[$key] !== null && (int) $permission[$key] !== (int) $scope[$key]) {
                return false;
            }
        }

        return true;
    }

    private function cacheKey(int $userId, int $tenantId): string
    {
        return "eralms:permissions:{$tenantId}:{$userId}";
    }
}
