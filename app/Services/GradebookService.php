<?php

namespace App\Services;

use App\Models\Gradebook;

class GradebookService
{
    public function create(array $data): Gradebook
    {
        return Gradebook::query()->create($data + ['grading_scheme' => 'weighted', 'status' => 'draft', 'settings' => []]);
    }

    public function update(Gradebook $gradebook, array $data): Gradebook
    {
        $this->ensureEditable($gradebook);
        unset($data['id'], $data['tenant_id'], $data['created_by'], $data['locked_by'], $data['locked_at']);
        $gradebook->fill($data)->save();
        return $gradebook->fresh();
    }

    public function activate(Gradebook $gradebook): Gradebook
    {
        $gradebook->forceFill(['status' => 'active'])->save();
        return $gradebook->fresh();
    }

    public function lock(Gradebook $gradebook, int $actorId): Gradebook
    {
        $gradebook->forceFill(['status' => 'locked', 'locked_by' => $actorId, 'locked_at' => now()])->save();
        $gradebook->summaries()->update(['status' => 'locked', 'locked_by' => $actorId, 'locked_at' => now()]);
        $gradebook->grades()->update(['source_status' => 'locked']);
        return $gradebook->fresh();
    }

    public function ensureEditable(Gradebook $gradebook, bool $allowOverrideLocked = false): void
    {
        if ($gradebook->status === 'locked' && ! $allowOverrideLocked) {
            throw new \RuntimeException('Gradebook đã khóa, không thể chỉnh sửa.');
        }
    }
}
