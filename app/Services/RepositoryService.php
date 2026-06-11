<?php

namespace App\Services;

use App\Models\ContentRepositoryItem;
use App\Models\ContentVersion;
use Illuminate\Support\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RepositoryService
{
    public function createFolder(array $data): ContentRepositoryItem
    {
        $this->assertParentFolder($data['parent_id'] ?? null, (int) $data['tenant_id']);

        return ContentRepositoryItem::query()->create($data + [
            'item_type' => 'folder',
            'visibility' => $data['visibility'] ?? 'faculty',
            'status' => $data['status'] ?? 'draft',
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    public function uploadFile(array $data, UploadedFile $file): ContentRepositoryItem
    {
        $tenantId = (int) $data['tenant_id'];
        $this->assertParentFolder($data['parent_id'] ?? null, $tenantId);

        $checksum = hash_file('sha256', $file->getRealPath());
        $existing = ContentRepositoryItem::query()
            ->where('tenant_id', $tenantId)
            ->where('checksum', $checksum)
            ->where('file_size', $file->getSize())
            ->first();

        $path = $existing?->storage_path ?: $file->store("eralms/tenant-{$tenantId}/repository");

        $item = ContentRepositoryItem::query()->create($data + [
            'item_type' => $data['item_type'] ?? $this->guessItemType($file->getClientOriginalExtension(), $file->getMimeType()),
            'title' => $data['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'storage_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'checksum' => $checksum,
            'visibility' => $data['visibility'] ?? 'private',
            'status' => 'draft',
            'metadata' => array_merge($data['metadata'] ?? [], [
                'original_name' => $file->getClientOriginalName(),
                'deduplicated_from_id' => $existing?->id,
            ]),
        ]);

        $this->createVersionRecord($item, $path, $checksum, (int) $file->getSize(), (int) ($data['owner_id'] ?? 0), 'Phiên bản đầu tiên');

        return $item;
    }

    public function newVersion(ContentRepositoryItem $item, UploadedFile $file, int $createdBy, ?string $note = null): ContentVersion
    {
        if ($item->isFolder()) {
            throw new \InvalidArgumentException('Folder không hỗ trợ version file.');
        }

        $checksum = hash_file('sha256', $file->getRealPath());
        $path = $file->store("eralms/tenant-{$item->tenant_id}/repository/versions");
        $item->forceFill([
            'storage_path' => $path,
            'checksum' => $checksum,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'metadata' => array_merge($item->metadata ?? [], ['latest_original_name' => $file->getClientOriginalName()]),
        ])->save();

        return $this->createVersionRecord($item, $path, $checksum, (int) $file->getSize(), $createdBy, $note);
    }

    public function move(ContentRepositoryItem $item, ?int $parentId): ContentRepositoryItem
    {
        $this->assertNotTrashed($item);

        if ($parentId === $item->id) {
            throw new \InvalidArgumentException('Không thể di chuyển item vào chính nó.');
        }

        $this->assertParentFolder($parentId, $item->tenant_id);
        $item->forceFill(['parent_id' => $parentId])->save();

        return $item->fresh(['parent']);
    }

    public function copy(ContentRepositoryItem $item, int $ownerId, ?int $parentId = null): ContentRepositoryItem
    {
        $this->assertNotTrashed($item);
        $this->assertParentFolder($parentId, $item->tenant_id);

        $copy = $item->replicate(['checksum']);
        $copy->title = $item->title.' (bản sao)';
        $copy->parent_id = $parentId ?? $item->parent_id;
        $copy->owner_id = $ownerId;
        $copy->status = 'draft';
        $copy->metadata = array_merge($item->metadata ?? [], ['copied_from_id' => $item->id]);

        if ($item->storage_path && Storage::exists($item->storage_path)) {
            $copy->storage_path = dirname($item->storage_path).'/copy-'.Str::uuid().'-'.basename($item->storage_path);
            Storage::copy($item->storage_path, $copy->storage_path);
        }

        $copy->save();

        foreach ($item->versions as $version) {
            $newVersion = $version->replicate(['content_item_id']);
            $newVersion->content_item_id = $copy->id;
            $newVersion->created_by = $ownerId;
            $newVersion->created_at = now();
            $newVersion->save();
        }

        return $copy;
    }

    public function share(ContentRepositoryItem $item, array $share): ContentRepositoryItem
    {
        $this->assertNotTrashed($item);

        $item->forceFill([
            'visibility' => $share['visibility'] ?? $item->visibility,
            'metadata' => array_merge($item->metadata ?? [], [
                'shared_with' => [
                    'tenant_id' => $share['tenant_id'] ?? null,
                    'academic_unit_id' => $share['academic_unit_id'] ?? null,
                    'course_id' => $share['course_id'] ?? null,
                ],
            ]),
        ])->save();

        return $item;
    }

    public function trash(ContentRepositoryItem $item, int $actorId): ContentRepositoryItem
    {
        return $this->trashItem($item, $actorId, true);
    }

    private function trashItem(ContentRepositoryItem $item, int $actorId, bool $detachFromParent): ContentRepositoryItem
    {
        if ($item->status === 'trashed') {
            return $item;
        }

        $metadata = $item->metadata ?? [];
        $metadata['trash'] = [
            'previous_status' => $item->status,
            'trashed_parent_id' => $item->parent_id,
            'trashed_at' => now()->toISOString(),
            'trashed_by' => $actorId,
        ];

        $item->forceFill([
            'parent_id' => $detachFromParent ? null : $item->parent_id,
            'status' => 'trashed',
            'metadata' => $metadata,
        ])->save();

        foreach ($item->children()->where('status', '!=', 'trashed')->get() as $child) {
            $this->trashItem($child, $actorId, false);
        }

        return $item->fresh(['children']);
    }

    public function restore(ContentRepositoryItem $item): ContentRepositoryItem
    {
        if ($item->status !== 'trashed') {
            return $item;
        }

        $metadata = $item->metadata ?? [];
        $trash = $metadata['trash'] ?? [];
        $parentId = $trash['trashed_parent_id'] ?? null;

        if ($parentId !== null) {
            $parent = ContentRepositoryItem::query()
                ->where('tenant_id', $item->tenant_id)
                ->where('status', '!=', 'trashed')
                ->find($parentId);
            $parentId = $parent?->id;
        }

        unset($metadata['trash']);

        $item->forceFill([
            'parent_id' => $parentId,
            'status' => $trash['previous_status'] ?? 'draft',
            'metadata' => $metadata,
        ])->save();

        foreach ($item->children()->where('status', 'trashed')->get() as $child) {
            $this->restore($child);
        }

        return $item->fresh(['parent', 'children']);
    }

    public function permanentDelete(ContentRepositoryItem $item): void
    {
        foreach ($item->children()->get() as $child) {
            $this->permanentDelete($child);
        }

        foreach ($item->versions as $version) {
            $this->deleteStorageIfUnreferenced($version->storage_path, $item->id);
            $version->delete();
        }

        $path = $item->storage_path;
        $itemId = $item->id;
        $item->delete();

        if ($path) {
            $this->deleteStorageIfUnreferenced($path, $itemId);
        }
    }

    public function bulkAction(array $itemIds, string $action, int $tenantId, int $actorId, ?int $parentId = null): array
    {
        $items = ContentRepositoryItem::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $itemIds)
            ->orderBy('id')
            ->get();

        return match ($action) {
            'trash' => $this->bulkTrash($items, $actorId),
            'restore' => $this->bulkRestore($items),
            'delete' => $this->bulkPermanentDelete($items),
            'move' => $this->bulkMove($items, $parentId),
            'copy' => $this->bulkCopy($items, $actorId, $parentId),
            default => throw new \InvalidArgumentException('Repository bulk action không hợp lệ.'),
        };
    }

    public function signedDownloadUrl(ContentRepositoryItem $item, int $ttlMinutes = 30): ?string
    {
        if (! $item->storage_path) {
            return null;
        }

        $disk = Storage::disk(config('eralms.repository_disk', 'local'));

        if (method_exists($disk, 'temporaryUrl')) {
            return $disk->temporaryUrl($item->storage_path, now()->addMinutes($ttlMinutes));
        }

        return $disk->url($item->storage_path);
    }

    private function createVersionRecord(ContentRepositoryItem $item, string $path, string $checksum, int $fileSize, int $createdBy, ?string $note): ContentVersion
    {
        return ContentVersion::query()->create([
            'tenant_id' => $item->tenant_id,
            'content_item_id' => $item->id,
            'version' => (int) ContentVersion::query()->where('content_item_id', $item->id)->max('version') + 1,
            'storage_path' => $path,
            'checksum' => $checksum,
            'file_size' => $fileSize,
            'change_note' => $note,
            'created_by' => $createdBy,
            'created_at' => now(),
        ]);
    }

    private function assertParentFolder(?int $parentId, int $tenantId): void
    {
        if ($parentId === null) {
            return;
        }

        $parent = ContentRepositoryItem::query()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'trashed')
            ->findOrFail($parentId);
        if (! $parent->isFolder()) {
            throw new \InvalidArgumentException('Parent phải là folder repository.');
        }
    }

    private function assertNotTrashed(ContentRepositoryItem $item): void
    {
        if ($item->status === 'trashed') {
            throw new \InvalidArgumentException('Không thể thao tác trên item trong thùng rác.');
        }
    }

    private function bulkTrash(Collection $items, int $actorId): array
    {
        $items->each(fn (ContentRepositoryItem $item) => $this->trash($item, $actorId));

        return ['action' => 'trash', 'affected' => $items->count()];
    }

    private function bulkRestore(Collection $items): array
    {
        $items->each(fn (ContentRepositoryItem $item) => $this->restore($item));

        return ['action' => 'restore', 'affected' => $items->count()];
    }

    private function bulkPermanentDelete(Collection $items): array
    {
        $items->each(fn (ContentRepositoryItem $item) => $this->permanentDelete($item));

        return ['action' => 'delete', 'affected' => $items->count()];
    }

    private function bulkMove(Collection $items, ?int $parentId): array
    {
        foreach ($items as $item) {
            $this->move($item, $parentId);
        }

        return ['action' => 'move', 'affected' => $items->count()];
    }

    private function bulkCopy(Collection $items, int $ownerId, ?int $parentId): array
    {
        $copies = [];

        foreach ($items as $item) {
            $copies[] = $this->copy($item, $ownerId, $parentId)->id;
        }

        return ['action' => 'copy', 'affected' => count($copies), 'copy_ids' => $copies];
    }

    private function deleteStorageIfUnreferenced(?string $path, int $currentItemId): void
    {
        if (! $path) {
            return;
        }

        $isUsedByItem = ContentRepositoryItem::query()
            ->where('id', '!=', $currentItemId)
            ->where('storage_path', $path)
            ->exists();

        $isUsedByVersion = ContentVersion::query()
            ->whereHas('contentItem', fn ($query) => $query->where('id', '!=', $currentItemId))
            ->where('storage_path', $path)
            ->exists();

        if (! $isUsedByItem && ! $isUsedByVersion && Storage::exists($path)) {
            Storage::delete($path);
        }
    }

    private function guessItemType(?string $extension, ?string $mime): string
    {
        $extension = strtolower((string) $extension);

        return match (true) {
            in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true) || str_contains((string) $mime, 'image') => 'image',
            in_array($extension, ['mp4', 'mov', 'm3u8'], true) || str_contains((string) $mime, 'video') => 'video',
            in_array($extension, ['mp3', 'wav'], true) || str_contains((string) $mime, 'audio') => 'audio',
            $extension === 'pdf' || str_contains((string) $mime, 'pdf') => 'pdf',
            in_array($extension, ['doc', 'docx'], true) => 'docx',
            in_array($extension, ['ppt', 'pptx'], true) => 'pptx',
            in_array($extension, ['zip', 'scorm'], true) => 'scorm',
            default => 'file',
        };
    }
}
