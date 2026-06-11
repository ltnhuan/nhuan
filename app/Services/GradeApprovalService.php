<?php

namespace App\Services;

use App\Contracts\SISGradeSyncContract;
use App\Models\GradeApprovalBatch;
use App\Models\Gradebook;

class GradeApprovalService
{
    public function __construct(private GradeFormulaService $formula, private GradebookService $gradebooks, private SISGradeSyncContract $sis) {}

    public function submit(Gradebook $gradebook, int $actorId, ?string $title = null): GradeApprovalBatch
    {
        $this->formula->recalculate($gradebook);
        $gradebook->summaries()->update(['status' => 'pending_approval']);
        return GradeApprovalBatch::query()->create(['tenant_id' => $gradebook->tenant_id, 'gradebook_id' => $gradebook->id, 'title' => $title ?: $gradebook->title.' - Batch '.now()->format('YmdHis'), 'status' => 'submitted', 'submitted_by' => $actorId, 'submitted_at' => now(), 'metadata' => ['summary_count' => $gradebook->summaries()->count()]]);
    }

    public function approve(Gradebook $gradebook, int $actorId): GradeApprovalBatch
    {
        $batch = $this->batch($gradebook);
        $batch->forceFill(['status' => 'approved', 'approved_by' => $actorId, 'approved_at' => now()])->save();
        $gradebook->summaries()->update(['status' => 'approved', 'approved_by' => $actorId, 'approved_at' => now()]);
        return $batch->fresh();
    }

    public function reject(Gradebook $gradebook, int $actorId, ?string $reason = null): GradeApprovalBatch
    {
        $batch = $this->batch($gradebook);
        $batch->forceFill(['status' => 'rejected', 'approved_by' => $actorId, 'approved_at' => now(), 'metadata' => array_merge($batch->metadata ?? [], ['reject_reason' => $reason])])->save();
        $gradebook->summaries()->update(['status' => 'draft']);
        return $batch->fresh();
    }

    public function lock(Gradebook $gradebook, int $actorId): GradeApprovalBatch
    {
        $batch = $this->batch($gradebook);
        $batch->forceFill(['status' => 'locked', 'locked_by' => $actorId, 'locked_at' => now()])->save();
        $this->gradebooks->lock($gradebook, $actorId);
        return $batch->fresh();
    }

    public function syncToSis(Gradebook $gradebook): GradeApprovalBatch
    {
        $batch = $this->batch($gradebook);
        $result = $this->sis->syncGradebook($gradebook, $batch);
        $batch->forceFill(['status' => 'synced_to_sis', 'sync_status' => 'synced', 'metadata' => array_merge($batch->metadata ?? [], ['sis_result' => $result])])->save();
        return $batch->fresh();
    }

    private function batch(Gradebook $gradebook): GradeApprovalBatch
    {
        return GradeApprovalBatch::query()->where('gradebook_id', $gradebook->id)->latest('id')->first()
            ?: GradeApprovalBatch::query()->create(['tenant_id' => $gradebook->tenant_id, 'gradebook_id' => $gradebook->id, 'title' => $gradebook->title.' - Lock batch', 'status' => 'draft', 'metadata' => []]);
    }
}
