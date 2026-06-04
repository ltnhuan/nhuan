<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $title = 'Khóa học '.$this->faker->unique()->words(3, true);

        return [
            'tenant_id' => 1,
            'code' => strtoupper($this->faker->unique()->bothify('CRS###')),
            'title' => $title,
            'slug' => Str::slug($title),
            'short_description' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'level' => 'college',
            'course_type' => 'blended',
            'status' => 'draft',
            'visibility' => 'internal',
            'language' => 'vi',
            'estimated_hours' => 30,
            'owner_id' => 1,
            'settings' => ['learning_mode' => 'free'],
        ];
    }
}
