<?php

namespace Database\Factories;

use App\Models\CourseSection;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseSectionFactory extends Factory
{
    protected $model = CourseSection::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'course_id' => 1,
            'parent_id' => null,
            'type' => 'section',
            'title' => 'Chương '.$this->faker->numberBetween(1, 9),
            'description' => $this->faker->sentence(),
            'sort_order' => 1,
            'status' => 'draft',
            'settings' => [],
        ];
    }
}
