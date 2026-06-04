<?php

namespace Database\Factories;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class SystemSettingFactory extends Factory
{
    protected $model = SystemSetting::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'group' => 'ui',
            'key' => $this->faker->unique()->slug(),
            'value' => ['enabled' => true],
        ];
    }
}
