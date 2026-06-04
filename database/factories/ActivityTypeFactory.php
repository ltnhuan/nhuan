<?php

namespace Database\Factories;

use App\Models\ActivityType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityTypeFactory extends Factory
{
    protected $model = ActivityType::class;

    public function definition(): array
    {
        $key = $this->faker->unique()->slug();

        return [
            'key' => $key,
            'name' => ucfirst($key),
            'description' => 'Activity kiểm thử',
            'icon' => 'box',
            'enabled' => true,
            'config_schema' => ['type' => 'object'],
            'grading_supported' => false,
            'completion_supported' => true,
        ];
    }
}
