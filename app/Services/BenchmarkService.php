<?php

namespace App\Services;

use App\Models\AnalyticsBenchmark;

class BenchmarkService
{
    public function compareWithInternalTarget(int $tenantId, string $key, string $scopeType, ?int $scopeId, float $currentValue, float $target): AnalyticsBenchmark
    {
        return $this->store($tenantId, $key, $scopeType, $scopeId, $currentValue, $target, null);
    }

    public function compareWithIndustryPlaceholder(int $tenantId, string $key, string $scopeType, ?int $scopeId, float $currentValue, ?float $industryValue = null): AnalyticsBenchmark
    {
        return $this->store($tenantId, $key, $scopeType, $scopeId, $currentValue, null, $industryValue);
    }

    public function generateStatus(float $currentValue, ?float $target = null, ?float $industryValue = null): string
    {
        $reference = $target ?? $industryValue;
        if ($reference === null) {
            return 'normal';
        }

        return match (true) {
            $currentValue < $reference * 0.85 => 'below',
            $currentValue < $reference => 'normal',
            $currentValue < $reference * 1.10 => 'good',
            default => 'excellent',
        };
    }

    private function store(int $tenantId, string $key, string $scopeType, ?int $scopeId, float $currentValue, ?float $target, ?float $industryValue): AnalyticsBenchmark
    {
        $status = $this->generateStatus($currentValue, $target, $industryValue);

        return AnalyticsBenchmark::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'benchmark_key' => $key, 'scope_type' => $scopeType, 'scope_id' => $scopeId],
            [
                'industry_value' => $industryValue,
                'internal_target' => $target,
                'current_value' => $currentValue,
                'status' => $status,
                'note' => 'Baseline benchmark từ snapshot analytics nội bộ.',
            ]
        );
    }
}
