<?php

namespace Database\Factories;

use App\Models\QuestionBank;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionBankFactory extends Factory
{
    protected $model = QuestionBank::class;

    public function definition(): array
    {
        return ['tenant_id' => 1, 'code' => 'QB-'.$this->faker->unique()->numberBetween(100, 999), 'name' => 'Ngân hàng câu hỏi mẫu', 'visibility' => 'tenant', 'status' => 'draft', 'owner_id' => 1, 'settings' => []];
    }
}
