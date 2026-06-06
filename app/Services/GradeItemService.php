<?php

namespace App\Services;

use App\Models\GradeCategory;
use App\Models\Gradebook;
use App\Models\GradeItem;
use Illuminate\Support\Facades\DB;

class GradeItemService
{
    public function __construct(private GradebookService $gradebooks) {}

    public function createCategory(Gradebook $gradebook, array $data): GradeCategory
    {
        $this->gradebooks->ensureEditable($gradebook);
        return GradeCategory::query()->create(array_replace($data, ['tenant_id' => $gradebook->tenant_id, 'gradebook_id' => $gradebook->id, 'sort_order' => $data['sort_order'] ?? $this->nextCategoryOrder($gradebook)]));
    }

    public function createItem(Gradebook $gradebook, array $data): GradeItem
    {
        $this->gradebooks->ensureEditable($gradebook);
        return GradeItem::query()->create(array_replace(['source_type' => 'manual', 'max_score' => 10, 'settings' => []], $data, ['tenant_id' => $gradebook->tenant_id, 'gradebook_id' => $gradebook->id, 'sort_order' => $data['sort_order'] ?? $this->nextItemOrder($gradebook)]));
    }

    public function reorder(Gradebook $gradebook, array $categoryIds = [], array $itemIds = []): void
    {
        $this->gradebooks->ensureEditable($gradebook);
        DB::transaction(function () use ($categoryIds, $itemIds) {
            foreach ($categoryIds as $index => $id) GradeCategory::query()->whereKey($id)->update(['sort_order' => $index + 1]);
            foreach ($itemIds as $index => $id) GradeItem::query()->whereKey($id)->update(['sort_order' => $index + 1]);
        });
    }

    private function nextCategoryOrder(Gradebook $gradebook): int
    {
        return ((int) GradeCategory::query()->where('gradebook_id', $gradebook->id)->max('sort_order')) + 1;
    }

    private function nextItemOrder(Gradebook $gradebook): int
    {
        return ((int) GradeItem::query()->where('gradebook_id', $gradebook->id)->max('sort_order')) + 1;
    }
}
