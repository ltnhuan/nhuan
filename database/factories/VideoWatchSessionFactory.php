<?php

namespace Database\Factories;

use App\Models\VideoWatchSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VideoWatchSessionFactory extends Factory
{
    protected $model = VideoWatchSession::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'user_id' => 1,
            'course_id' => 1,
            'component_id' => 1,
            'video_asset_id' => 1,
            'session_uuid' => (string) Str::uuid(),
            'started_at' => now(),
            'last_position_seconds' => 0,
            'max_position_seconds' => 0,
            'watched_seconds' => 0,
            'watch_percent' => 0,
            'playback_rate' => 1,
            'status' => 'active',
            'metadata' => ['factory' => true],
        ];
    }
}
