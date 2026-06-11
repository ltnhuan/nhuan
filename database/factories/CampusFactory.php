<?php

namespace Database\Factories;

use App\Models\Campus;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampusFactory extends Factory
{
    protected $model = Campus::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'code' => strtoupper($this->faker->unique()->bothify('CS##')),
            'name' => $this->faker->city().' Campus',
            'address' => $this->faker->address(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->safeEmail(),
            'status' => 'active',
        ];
    }
}
