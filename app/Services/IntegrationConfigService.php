<?php

namespace App\Services;

use App\Contracts\SISAdapterContract;
use App\Models\IntegrationApiKey;
use App\Models\IntegrationSystem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class IntegrationConfigService
{
    public function __construct(private SISAdapterContract $adapter) {}

    public function createSystem(array $data): IntegrationSystem
    {
        if (isset($data['credentials'])) {
            $data['credentials_encrypted'] = Crypt::encryptString(json_encode($data['credentials']));
            unset($data['credentials']);
        }
        return IntegrationSystem::query()->create($data + ['system_type' => 'sis', 'auth_type' => 'api_key', 'status' => 'active', 'settings' => []]);
    }

    public function updateSystem(IntegrationSystem $system, array $data): IntegrationSystem
    {
        if (isset($data['credentials'])) {
            $data['credentials_encrypted'] = Crypt::encryptString(json_encode($data['credentials']));
            unset($data['credentials']);
        }
        $system->fill($data)->save();
        return $system->fresh();
    }

    public function rotateApiKey(IntegrationSystem $system, string $name, array $scopes = []): array
    {
        $plain = 'eralms_'.Str::random(48);
        $key = IntegrationApiKey::query()->create(['tenant_id' => $system->tenant_id, 'system_id' => $system->id, 'key_name' => $name, 'api_key_hash' => hash('sha256', $plain), 'scopes' => $scopes, 'status' => 'active']);
        return ['api_key' => $plain, 'record' => $key];
    }

    public function validateConnection(IntegrationSystem $system): array
    {
        try {
            $result = $this->adapter->healthCheck($system);
            $system->forceFill(['status' => ($result['ok'] ?? false) ? 'active' : 'error'])->save();
            return $result;
        } catch (\Throwable $e) {
            $system->forceFill(['status' => 'error'])->save();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
