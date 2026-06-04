<?php

namespace Database\Factories;

use App\Models\ContentRepositoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContentRepositoryItemFactory extends Factory
{
    protected $model = ContentRepositoryItem::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'parent_id' => null,
            'item_type' => 'file',
            'title' => 'Học liệu '.$this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'storage_path' => 'eralms/demo/file.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'checksum' => hash('sha256', $this->faker->uuid()),
            'owner_id' => 1,
            'visibility' => 'faculty',
            'status' => 'draft',
            'metadata' => [],
        ];
    }
}
