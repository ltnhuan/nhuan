<?php

namespace Database\Factories;

use App\Models\VideoAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

class VideoAssetFactory extends Factory
{
    protected $model = VideoAsset::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'title' => 'Video đào tạo '.$this->faker->numberBetween(1, 999),
            'description' => 'Video mẫu phục vụ kiểm thử nền tảng video.',
            'original_filename' => 'video-demo.mp4',
            'original_storage_path' => 'videos/demo/video-demo.mp4',
            'duration_seconds' => 600,
            'file_size' => 52_428_800,
            'mime_type' => 'video/mp4',
            'processing_status' => 'ready',
            'visibility' => 'course',
            'checksum' => $this->faker->sha256(),
            'settings' => ['factory' => true],
            'uploaded_by' => 1,
        ];
    }
}
