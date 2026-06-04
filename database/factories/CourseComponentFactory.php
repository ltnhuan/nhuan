<?php

namespace Database\Factories;

use App\Models\CourseComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseComponentFactory extends Factory
{
    protected $model = CourseComponent::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'course_id' => 1,
            'section_id' => 1,
            'component_type' => 'text',
            'title' => 'Học liệu '.$this->faker->numberBetween(1, 99),
            'config' => [],
            'sort_order' => 1,
            'required' => true,
            'status' => 'draft',
        ];
    }
}
