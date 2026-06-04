<?php

namespace App\Services;

use App\Models\ContentApproval;
use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflowService
{
    public function submitReview(Model $entity, int $requestedBy, ?string $note = null): ContentApproval
    {
        return $this->transition($entity, $requestedBy, null, 'draft', 'review', 'pending', $note);
    }

    public function approve(Model $entity, int $reviewedBy, ?string $note = null): ContentApproval
    {
        return $this->transition($entity, 0, $reviewedBy, $entity->status ?? 'review', 'approved', 'approved', $note);
    }

    public function reject(Model $entity, int $reviewedBy, ?string $note = null): ContentApproval
    {
        return $this->transition($entity, 0, $reviewedBy, $entity->status ?? 'review', 'draft', 'rejected', $note);
    }

    public function returnForEdit(Model $entity, int $reviewedBy, ?string $note = null): ContentApproval
    {
        return $this->transition($entity, 0, $reviewedBy, $entity->status ?? 'review', 'draft', 'returned', $note);
    }

    private function transition(Model $entity, int $requestedBy, ?int $reviewedBy, string $from, string $to, string $decision, ?string $note): ContentApproval
    {
        $entity->forceFill(['status' => $to])->save();

        return ContentApproval::query()->create([
            'tenant_id' => $entity->tenant_id,
            'entity_type' => class_basename($entity),
            'entity_id' => $entity->id,
            'from_status' => $from,
            'to_status' => $to,
            'requested_by' => $requestedBy ?: ($entity->owner_id ?? 0),
            'reviewed_by' => $reviewedBy,
            'decision' => $decision,
            'note' => $note,
            'decided_at' => $reviewedBy ? now() : null,
        ]);
    }
}
