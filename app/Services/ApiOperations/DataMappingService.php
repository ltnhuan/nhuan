<?php

namespace App\Services\ApiOperations;

use App\Models\ApiDataMapping;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DataMappingService
{
    public function mapInboundPayload(int $tenantId, int $sourceSystemId, int $targetSystemId, string $entityType, array $payload): array
    {
        return $this->mapPayload($tenantId, $sourceSystemId, $targetSystemId, $entityType, $payload);
    }

    public function mapOutboundPayload(int $tenantId, int $sourceSystemId, int $targetSystemId, string $entityType, array $payload): array
    {
        return $this->mapPayload($tenantId, $sourceSystemId, $targetSystemId, $entityType, $payload);
    }

    public function mapPayload(int $tenantId, int $sourceSystemId, int $targetSystemId, string $entityType, array $payload): array
    {
        $output = [];
        $errors = [];
        $mappings = ApiDataMapping::query()
            ->where('tenant_id', $tenantId)
            ->where('source_system_id', $sourceSystemId)
            ->where('target_system_id', $targetSystemId)
            ->where('entity_type', $entityType)
            ->where('status', 'active')
            ->get();

        foreach ($mappings as $mapping) {
            $value = Arr::get($payload, $mapping->source_field, $mapping->default_value);
            if (($value === null || $value === '') && $mapping->is_required && $mapping->default_value === null) {
                $errors[] = "{$mapping->source_field} is required.";
                continue;
            }

            Arr::set($output, $mapping->target_field, $this->applyTransformRule($value, $mapping->transform_rule ?? []));
        }

        return [
            'mapped' => $output,
            'errors' => $errors,
            'valid' => count($errors) === 0,
            'mapping_count' => $mappings->count(),
        ];
    }

    public function applyTransformRule(mixed $value, array $rule = []): mixed
    {
        $type = $rule['type'] ?? 'copy';

        return match ($type) {
            'uppercase' => Str::upper((string) $value),
            'lowercase' => Str::lower((string) $value),
            'integer' => (int) $value,
            'float' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'prefix' => (string) ($rule['value'] ?? '').(string) $value,
            'suffix' => (string) $value.(string) ($rule['value'] ?? ''),
            'default' => blank($value) ? ($rule['value'] ?? null) : $value,
            default => $value,
        };
    }

    public function validateRequiredFields(int $tenantId, int $sourceSystemId, int $targetSystemId, string $entityType, array $payload): array
    {
        return $this->mapPayload($tenantId, $sourceSystemId, $targetSystemId, $entityType, $payload)['errors'];
    }

    public function detectMappingConflict(int $tenantId, int $sourceSystemId, int $targetSystemId, string $entityType): array
    {
        $mappings = ApiDataMapping::query()
            ->where('tenant_id', $tenantId)
            ->where('source_system_id', $sourceSystemId)
            ->where('target_system_id', $targetSystemId)
            ->where('entity_type', $entityType)
            ->where('status', 'active')
            ->get()
            ->groupBy('target_field');

        return $mappings
            ->filter(fn ($rows) => $rows->pluck('source_field')->unique()->count() > 1)
            ->map(fn ($rows, $target) => [
                'target_field' => $target,
                'source_fields' => $rows->pluck('source_field')->values()->all(),
                'severity' => 'medium',
            ])
            ->values()
            ->all();
    }
}
