<?php

namespace App\Policies;

use App\Models\LmsUser;
use App\Models\VideoAsset;

class VideoAssetPolicy
{
    public function view(LmsUser $user, VideoAsset $asset): bool
    {
        return $user->tenant_id === $asset->tenant_id;
    }

    public function upload(LmsUser $user): bool
    {
        return in_array($user->user_type, ['admin', 'staff', 'teacher'], true);
    }

    public function manage(LmsUser $user, VideoAsset $asset): bool
    {
        return $user->tenant_id === $asset->tenant_id && in_array($user->user_type, ['admin', 'staff', 'teacher'], true);
    }
}
