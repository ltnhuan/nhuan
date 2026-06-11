<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'parent_id' => null,
            'code' => strtoupper($this->faker->unique()->bothify('ORG##')),
            'name' => $this->faker->company(),
            'type' => 'school',
            'status' => 'active',
            'settings' => [],
        ];
    }
}
