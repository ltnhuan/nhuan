<?php

namespace App\Services;

use App\Models\ContentRepositoryItem;
use App\Models\ContentVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class RepositoryService
{
    public function createFolder(array $data): ContentRepositoryItem
    {
        return ContentRepositoryItem::query()->create($data + ['item_type' => 'folder', 'status' => 'draft']);
    }

    public function uploadFile(array $data, UploadedFile $file): ContentRepositoryItem
    {
        $checksum = hash_file('sha256', $file->getRealPath());
        $path = $file->store("eralms/tenant-{$data['tenant_id']}/repository");

        $item = ContentRepositoryItem::query()->create($data + [
            'item_type' => $this->guessItemType($file->getMimeType()),
            'storage_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'checksum' => $checksum,
            'status' => 'draft',
        ]);

        $this->newVersion($item, $file, $data['owner_id'] ?? 0, 'Phiên bản đầu tiên');
        return $item;
    }

    public function newVersion(ContentRepositoryItem $item, UploadedFile $file, int $createdBy, ?string $note = null): ContentVersion
    {
        $checksum = hash_file('sha256', $file->getRealPath());
        $path = $file->store("eralms/tenant-{$item->tenant_id}/repository/versions");
        $item->forceFill(['storage_path' => $path, 'checksum' => $checksum, 'file_size' => $file->getSize(), 'mime_type' => $file->getMimeType()])->save();

        return ContentVersion::query()->create([
            'tenant_id' => $item->tenant_id,
            'content_item_id' => $item->id,
            'version' => (int) ContentVersion::query()->where('content_item_id', $item->id)->max('version') + 1,
            'storage_path' => $path,
            'checksum' => $checksum,
            'file_size' => $file->getSize(),
            'change_note' => $note,
            'created_by' => $createdBy,
        ]);
    }

    public function move(ContentRepositoryItem $item, ?int $parentId): ContentRepositoryItem
    {
        $item->forceFill(['parent_id' => $parentId])->save();
        return $item;
    }

    public function copy(ContentRepositoryItem $item, int $ownerId): ContentRepositoryItem
    {
        $copy = $item->replicate(['checksum']);
        $copy->title = $item->title.' (bản sao)';
        $copy->owner_id = $ownerId;
        if ($item->storage_path && Storage::exists($item->storage_path)) {
            $copy->storage_path = dirname($item->storage_path).'/copy-'.basename($item->storage_path);
            Storage::copy($item->storage_path, $copy->storage_path);
        }
        $copy->save();
        return $copy;
    }

    private function guessItemType(?string $mime): string
    {
        return match (true) {
            str_contains((string) $mime, 'video') => 'video',
            str_contains((string) $mime, 'audio') => 'audio',
            str_contains((string) $mime, 'pdf') => 'pdf',
            default => 'file',
        };
    }
}
