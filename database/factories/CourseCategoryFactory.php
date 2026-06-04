<?php

namespace Database\Factories;

use App\Models\CourseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseCategoryFactory extends Factory
{
    protected $model = CourseCategory::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'parent_id' => null,
            'code' => strtoupper($this->faker->unique()->bothify('CAT##')),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'sort_order' => 1,
            'status' => 'active',
        ];
    }
}
