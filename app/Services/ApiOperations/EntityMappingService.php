<?php

namespace App\Services\ApiOperations;

use App\Models\ApiEntityMapping;

class EntityMappingService
{
    public function findLocalByExternal(int $tenantId, int $systemId, string $entityType, string $externalId): ?string
    {
        return ApiEntityMapping::query()
            ->where('tenant_id', $tenantId)
            ->where('system_id', $systemId)
            ->where('entity_type', $entityType)
            ->where('external_id', $externalId)
            ->where('status', 'active')
            ->value('local_id');
    }

    public function findExternalByLocal(int $tenantId, int $systemId, string $entityType, string $localId): ?string
    {
        return ApiEntityMapping::query()
            ->where('tenant_id', $tenantId)
            ->where('system_id', $systemId)
            ->where('entity_type', $entityType)
            ->where('local_id', $localId)
            ->where('status', 'active')
            ->value('external_id');
    }

    public function createMapping(array $data): ApiEntityMapping
    {
        $duplicate = $this->detectDuplicateMapping((int) $data['tenant_id'], (int) $data['system_id'], (string) $data['entity_type'], (string) $data['local_id'], (string) $data['external_id']);
        $status = $duplicate ? 'conflict' : ($data['status'] ?? 'active');

        $mapping = ApiEntityMapping::query()->updateOrCreate([
            'tenant_id' => $data['tenant_id'],
            'system_id' => $data['system_id'],
            'entity_type' => $data['entity_type'],
            'local_id' => $data['local_id'],
        ], [
            'external_id' => $data['external_id'],
            'external_code' => $data['external_code'] ?? null,
            'status' => $status,
            'metadata' => $data['metadata'] ?? [],
        ]);

        if ($duplicate) {
            ApiEntityMapping::query()
                ->where('tenant_id', $data['tenant_id'])
                ->where('system_id', $data['system_id'])
                ->where('entity_type', $data['entity_type'])
                ->where('external_id', $data['external_id'])
                ->update(['status' => 'conflict']);
        }

        return $mapping->fresh();
    }

    public function resolveConflict(int $mappingId, string $action = 'activate', ?int $tenantId = null): ApiEntityMapping
    {
        $mapping = ApiEntityMapping::query()
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->findOrFail($mappingId);
        $mapping->forceFill(['status' => $action === 'ignore' ? 'inactive' : 'active'])->save();

        if ($action !== 'ignore') {
            ApiEntityMapping::query()
                ->where('tenant_id', $mapping->tenant_id)
                ->where('system_id', $mapping->system_id)
                ->where('entity_type', $mapping->entity_type)
                ->where('external_id', $mapping->external_id)
                ->where('id', '!=', $mapping->id)
                ->update(['status' => 'inactive']);
        }

        return $mapping->fresh();
    }

    public function detectDuplicateMapping(int $tenantId, int $systemId, string $entityType, string $localId, string $externalId): bool
    {
        return ApiEntityMapping::query()
            ->where('tenant_id', $tenantId)
            ->where('system_id', $systemId)
            ->where('entity_type', $entityType)
            ->where(function ($query) use ($localId, $externalId) {
                $query->where('local_id', '!=', $localId)->where('external_id', $externalId);
            })
            ->exists();
    }
}
