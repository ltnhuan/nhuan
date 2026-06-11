<?php

namespace App\Services\Core;

use App\Models\Tenant;
use App\Services\SettingService;

class WhiteLabelService
{
    public function __construct(private readonly SettingService $settings)
    {
    }

    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->fill([
            'logo_url' => $data['logo_url'] ?? $tenant->logo_url,
            'primary_color' => $data['primary_color'] ?? $tenant->primary_color,
            'secondary_color' => $data['secondary_color'] ?? $tenant->secondary_color,
            'locale' => $data['locale'] ?? $tenant->locale,
            'timezone' => $data['timezone'] ?? $tenant->timezone,
            'settings' => array_merge($tenant->settings ?? [], $data['settings'] ?? []),
        ])->save();

        $this->settings->set($tenant->id, 'ui', 'white_label', [
            'logo_url' => $tenant->logo_url,
            'primary_color' => $tenant->primary_color,
            'secondary_color' => $tenant->secondary_color,
            'locale' => $tenant->locale,
            'timezone' => $tenant->timezone,
        ]);

        return $tenant;
    }
}
