<?php

namespace App\Services\Core;

use App\Models\LmsUser;
use Illuminate\Support\Facades\Cache;

class CoreMenuService
{
    public function __construct(private readonly CorePermissionService $permissions)
    {
    }

    public function forUser(LmsUser $user, int $tenantId): array
    {
        return Cache::remember("eralms:menu:{$tenantId}:{$user->id}", 300, function () use ($user, $tenantId) {
            $allowed = $this->permissions->permissionsForUser($user, $tenantId)->pluck('key')->unique()->all();

            return collect(config('eralms.menu', []))
                ->filter(fn (array $item) => empty($item['permission']) || in_array($item['permission'], $allowed, true))
                ->values()
                ->all();
        });
    }
}
