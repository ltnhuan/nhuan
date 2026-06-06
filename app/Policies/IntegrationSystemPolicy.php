<?php

namespace App\Policies;

use App\Models\IntegrationSystem;
use App\Models\LmsUser;
use App\Services\Core\CorePermissionService;

class IntegrationSystemPolicy
{
    public function __construct(private readonly CorePermissionService $permissions) {}
    public function view(LmsUser $user, IntegrationSystem $system): bool { return $this->permissions->can($user, 'integration.view', ['tenant_id'=>$system->tenant_id]); }
    public function manage(LmsUser $user, IntegrationSystem $system): bool { return $this->permissions->can($user, 'integration.manage', ['tenant_id'=>$system->tenant_id]); }
}
