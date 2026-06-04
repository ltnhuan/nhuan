<?php

namespace App\Policies;

use App\Models\ContentRepositoryItem;
use App\Models\LmsUser;
use App\Services\Core\CorePermissionService;

class ContentRepositoryItemPolicy
{
    public function __construct(private readonly CorePermissionService $permissions)
    {
    }

    public function view(LmsUser $user, ContentRepositoryItem $item): bool
    {
        if ($item->visibility === 'public' || $item->owner_id === $user->id) {
            return true;
        }

        return $this->permissions->can($user, 'repository.view', ['tenant_id' => $item->tenant_id, 'academic_unit_id' => $item->academic_unit_id]);
    }

    public function upload(LmsUser $user, ContentRepositoryItem $item): bool
    {
        return $item->owner_id === $user->id || $this->permissions->can($user, 'repository.upload', ['tenant_id' => $item->tenant_id, 'academic_unit_id' => $item->academic_unit_id]);
    }

    public function manage(LmsUser $user, ContentRepositoryItem $item): bool
    {
        return $item->owner_id === $user->id || $this->permissions->can($user, 'repository.manage', ['tenant_id' => $item->tenant_id, 'academic_unit_id' => $item->academic_unit_id]);
    }

    public function approve(LmsUser $user, ContentRepositoryItem $item): bool
    {
        return $this->permissions->can($user, 'repository.approve', ['tenant_id' => $item->tenant_id, 'academic_unit_id' => $item->academic_unit_id]);
    }
}
