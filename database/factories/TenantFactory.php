<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('???')),
            'name' => $this->faker->company().' LMS',
            'legal_name' => $this->faker->company(),
            'domain' => $this->faker->unique()->domainName(),
            'status' => 'active',
            'primary_color' => '#0f4c81',
            'secondary_color' => '#f59e0b',
            'locale' => 'vi',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'settings' => [],
        ];
    }
}
