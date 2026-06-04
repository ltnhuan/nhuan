<?php

namespace Database\Factories;

use App\Models\AcademicUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicUnitFactory extends Factory
{
    protected $model = AcademicUnit::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'organization_id' => 1,
            'code' => strtoupper($this->faker->unique()->bothify('UNIT##')),
            'name' => 'Khoa '.$this->faker->word(),
            'type' => 'faculty',
            'status' => 'active',
        ];
    }
}
