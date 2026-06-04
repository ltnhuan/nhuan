<?php

namespace App\Http\Controllers\Api\V1\Core;

use App\Models\LmsUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UserRoleScope;
use App\Services\Core\CorePermissionService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RbacController extends Controller
{
    public function roles(TenantContext $tenantContext)
    {
        return Role::query()
            ->where(function ($query) use ($tenantContext) {
                $query->whereNull('tenant_id')->orWhere('tenant_id', $tenantContext->id());
            })
            ->with('permissions:id,key,module,action')
            ->orderBy('scope')
            ->orderBy('name')
            ->paginate(50);
    }

    public function permissions()
    {
        return Permission::query()->orderBy('module')->orderBy('action')->get()->groupBy('module');
    }

    public function assignRole(Request $request, LmsUser $user, CorePermissionService $permissions)
    {
        $data = $request->validate([
            'role_id' => ['required', 'integer'],
            'tenant_id' => ['required', 'integer'],
            'campus_id' => ['nullable', 'integer'],
            'academic_unit_id' => ['nullable', 'integer'],
            'course_id' => ['nullable', 'integer'],
            'class_id' => ['nullable', 'integer'],
        ]);

        $scope = UserRoleScope::query()->updateOrCreate([
            'user_id' => $user->id,
            'role_id' => $data['role_id'],
            'tenant_id' => $data['tenant_id'],
            'campus_id' => $data['campus_id'] ?? null,
            'academic_unit_id' => $data['academic_unit_id'] ?? null,
            'course_id' => $data['course_id'] ?? null,
            'class_id' => $data['class_id'] ?? null,
        ], $data + ['user_id' => $user->id]);

        $permissions->clearUserCache($user->id, (int) $data['tenant_id']);

        return $scope->load('role');
    }

    public function syncRolePermissions(Request $request, Role $role, CorePermissionService $permissions)
    {
        $data = $request->validate(['permissions' => ['required', 'array'], 'permissions.*' => ['string']]);
        $permissions->syncRolePermissions($role->id, $data['permissions']);

        return $role->load('permissions');
    }
}
