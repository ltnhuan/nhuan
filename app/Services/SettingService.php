<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    public function get(int $tenantId, string $group, string $key, mixed $default = null): mixed
    {
        return Cache::remember("eralms:settings:{$tenantId}:{$group}:{$key}", 600, function () use ($tenantId, $group, $key, $default) {
            return SystemSetting::query()
                ->where('tenant_id', $tenantId)
                ->where('group', $group)
                ->where('key', $key)
                ->value('value') ?? $default;
        });
    }

    public function set(int $tenantId, string $group, string $key, mixed $value): SystemSetting
    {
        $setting = SystemSetting::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'group' => $group, 'key' => $key],
            ['value' => $value]
        );

        Cache::forget("eralms:settings:{$tenantId}:{$group}:{$key}");

        return $setting;
    }
}
