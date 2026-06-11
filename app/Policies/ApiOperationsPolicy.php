<?php

namespace App\Policies;

use App\Models\LmsUser;
use App\Services\Core\CorePermissionService;

class ApiOperationsPolicy
{
    public function view(LmsUser $user): bool
    {
        return app(CorePermissionService::class)->can($user, 'api_ops.view', ['tenant_id' => $user->tenant_id]);
    }

    public function manageSystems(LmsUser $user): bool
    {
        return app(CorePermissionService::class)->can($user, 'api_ops.system.manage', ['tenant_id' => $user->tenant_id]);
    }

    public function viewPayload(LmsUser $user): bool
    {
        return app(CorePermissionService::class)->can($user, 'api_ops.payload.view', ['tenant_id' => $user->tenant_id]);
    }

    public function useConsole(LmsUser $user): bool
    {
        return app(CorePermissionService::class)->can($user, 'api_ops.console.use', ['tenant_id' => $user->tenant_id]);
    }
}
