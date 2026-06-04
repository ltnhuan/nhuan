<?php

namespace Database\Factories;

use App\Models\CoursePublishLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class CoursePublishLogFactory extends Factory
{
    protected $model = CoursePublishLog::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'course_id' => 1,
            'version_id' => null,
            'action' => 'publish',
            'actor_id' => 1,
            'note' => 'Log kiểm thử',
        ];
    }
}
