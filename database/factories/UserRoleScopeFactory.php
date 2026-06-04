<?php

namespace Database\Factories;

use App\Models\UserRoleScope;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserRoleScopeFactory extends Factory
{
    protected $model = UserRoleScope::class;

    public function definition(): array
    {
        return [
            'user_id' => 1,
            'role_id' => 1,
            'tenant_id' => 1,
            'campus_id' => null,
            'academic_unit_id' => null,
            'course_id' => null,
            'class_id' => null,
        ];
    }
}
