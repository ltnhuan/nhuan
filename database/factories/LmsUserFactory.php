<?php

namespace Database\Factories;

use App\Models\LmsUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class LmsUserFactory extends Factory
{
    protected $model = LmsUser::class;

    public function definition(): array
    {
        return ['tenant_id' => 1, 'code' => 'SV'.$this->faker->unique()->numerify('#####'), 'full_name' => $this->faker->name(), 'email' => $this->faker->unique()->safeEmail(), 'user_type' => 'student', 'status' => 'active', 'metadata' => []];
    }
}
