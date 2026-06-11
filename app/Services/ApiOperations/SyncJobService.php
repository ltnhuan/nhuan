<?php

namespace App\Services\ApiOperations;

use App\Jobs\ApiOperations\RunApiSyncJob;
use App\Models\ApiSyncJob;
use App\Models\ApiSyncJobItem;

class SyncJobService
{
    public function createFullSync(int $tenantId, int $systemId, string $entityType, ?int $createdBy = null, bool $dispatch = false): ApiSyncJob
    {
        return $this->createJob($tenantId, $systemId, 'full_sync', $entityType, $createdBy, $dispatch);
    }

    public function createIncrementalSync(int $tenantId, int $systemId, string $entityType, ?int $createdBy = null, bool $dispatch = false): ApiSyncJob
    {
        return $this->createJob($tenantId, $systemId, 'incremental_sync', $entityType, $createdBy, $dispatch);
    }

    public function createJob(int $tenantId, int $systemId, string $jobType, string $entityType, ?int $createdBy = null, bool $dispatch = false): ApiSyncJob
    {
        $job = ApiSyncJob::query()->create([
            'tenant_id' => $tenantId,
            'system_id' => $systemId,
            'job_type' => $jobType,
            'entity_type' => $entityType,
            'status' => 'pending',
            'created_by' => $createdBy,
            'error_report' => [],
        ]);

        if ($dispatch) {
            RunApiSyncJob::dispatch($job->id)->onQueue('api-sync');
        }

        return $job;
    }

    public function runSyncJob(ApiSyncJob|int $job): ApiSyncJob
    {
        $job = $job instanceof ApiSyncJob ? $job : ApiSyncJob::query()->findOrFail($job);
        if ($job->status === 'cancelled') {
            return $job;
        }

        $job->forceFill(['status' => 'running', 'started_at' => now()])->save();

        if ($job->items()->count() === 0) {
            foreach (range(1, 10) as $index) {
                ApiSyncJobItem::query()->create([
                    'tenant_id' => $job->tenant_id,
                    'sync_job_id' => $job->id,
                    'entity_type' => $job->entity_type,
                    'entity_id' => (string) $index,
                    'external_id' => strtoupper($job->entity_type).'-'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                    'status' => 'pending',
                    'payload' => ['sample' => true, 'index' => $index],
                ]);
            }
        }

        $success = 0;
        $failed = 0;
        foreach ($job->items()->whereIn('status', ['pending', 'failed'])->get() as $item) {
            $processed = $this->processSyncItem($item);
            $processed->status === 'success' ? $success++ : $failed++;
        }

        $job->forceFill([
            'status' => $failed > 0 ? 'failed' : 'success',
            'total_count' => $success + $failed,
            'success_count' => $success,
            'failed_count' => $failed,
            'finished_at' => now(),
            'error_report' => $this->generateErrorReport($job),
        ])->save();

        return $job->fresh();
    }

    public function processSyncItem(ApiSyncJobItem $item): ApiSyncJobItem
    {
        $shouldFail = (bool) ($item->payload['force_fail'] ?? false);
        $item->forceFill([
            'status' => $shouldFail ? 'failed' : 'success',
            'error_message' => $shouldFail ? 'Forced sync item failure.' : null,
        ])->save();

        return $item->fresh();
    }

    public function retryFailedItems(ApiSyncJob|int $job): ApiSyncJob
    {
        $job = $job instanceof ApiSyncJob ? $job : ApiSyncJob::query()->findOrFail($job);
        $job->items()->where('status', 'failed')->update(['status' => 'pending', 'error_message' => null]);

        return $this->runSyncJob($job);
    }

    public function cancelJob(ApiSyncJob|int $job): ApiSyncJob
    {
        $job = $job instanceof ApiSyncJob ? $job : ApiSyncJob::query()->findOrFail($job);
        $job->forceFill(['status' => 'cancelled', 'finished_at' => now()])->save();

        return $job->fresh();
    }

    public function generateErrorReport(ApiSyncJob $job): array
    {
        return $job->items()
            ->where('status', 'failed')
            ->get(['id', 'entity_type', 'entity_id', 'external_id', 'error_message'])
            ->toArray();
    }
}
