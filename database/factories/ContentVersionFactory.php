<?php

namespace Database\Factories;

use App\Models\ContentVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContentVersionFactory extends Factory
{
    protected $model = ContentVersion::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'content_item_id' => 1,
            'version' => '1',
            'storage_path' => 'eralms/demo/file.pdf',
            'checksum' => hash('sha256', $this->faker->uuid()),
            'file_size' => 1024,
            'change_note' => 'Phiên bản kiểm thử',
            'created_by' => 1,
        ];
    }
}
