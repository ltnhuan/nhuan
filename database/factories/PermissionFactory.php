<?php

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $module = $this->faker->randomElement(['core', 'course', 'report']);
        $action = $this->faker->unique()->word();

        return [
            'key' => "{$module}.{$action}",
            'module' => $module,
            'action' => $action,
            'description' => 'Quyền kiểm thử',
        ];
    }
}
