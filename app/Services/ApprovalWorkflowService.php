<?php

namespace App\Services;

use App\Models\ContentApproval;
use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflowService
{
    public function submitReview(Model $entity, int $requestedBy, ?string $note = null): ContentApproval
    {
        return $this->transition($entity, $requestedBy, null, $entity->status ?? 'draft', 'review', 'pending', $note);
    }

    public function approve(Model $entity, int $reviewedBy, ?string $note = null): ContentApproval
    {
        return $this->transition($entity, (int) ($entity->owner_id ?? 0), $reviewedBy, $entity->status ?? 'review', 'approved', 'approved', $note);
    }

    public function reject(Model $entity, int $reviewedBy, ?string $note = null): ContentApproval
    {
        return $this->transition($entity, (int) ($entity->owner_id ?? 0), $reviewedBy, $entity->status ?? 'review', 'draft', 'rejected', $note);
    }

    public function returnForEdit(Model $entity, int $reviewedBy, ?string $note = null): ContentApproval
    {
        return $this->transition($entity, (int) ($entity->owner_id ?? 0), $reviewedBy, $entity->status ?? 'review', 'draft', 'returned', $note);
    }

    private function transition(Model $entity, int $requestedBy, ?int $reviewedBy, string $from, string $to, string $decision, ?string $note): ContentApproval
    {
        if (in_array($decision, ['approved', 'rejected', 'returned'], true) && $from !== 'review') {
            throw new \InvalidArgumentException('Chỉ entity đang review mới được duyệt/từ chối/trả về.');
        }

        $payload = ['status' => $to];
        if (property_exists($entity, 'approved_by') || array_key_exists('approved_by', $entity->getAttributes())) {
            $payload['approved_by'] = $decision === 'approved' ? $reviewedBy : null;
            $payload['approved_at'] = $decision === 'approved' ? now() : null;
        }
        $entity->forceFill($payload)->save();

        return ContentApproval::query()->create([
            'tenant_id' => $entity->tenant_id,
            'entity_type' => class_basename($entity),
            'entity_id' => $entity->id,
            'from_status' => $from,
            'to_status' => $to,
            'requested_by' => $requestedBy,
            'reviewed_by' => $reviewedBy,
            'decision' => $decision,
            'note' => $note,
            'created_at' => now(),
            'decided_at' => $reviewedBy ? now() : null,
        ]);
    }
}
