<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->slug(2);

        return [
            'tenant_id' => 1,
            'name' => $name,
            'guard_name' => 'web',
            'display_name' => ucfirst(str_replace('-', ' ', $name)),
            'scope' => 'tenant',
            'description' => 'Vai trò kiểm thử',
        ];
    }
}
