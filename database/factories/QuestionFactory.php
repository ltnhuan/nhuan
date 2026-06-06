<?php

namespace Database\Factories;

use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return ['tenant_id' => 1, 'question_bank_id' => 1, 'code' => 'Q-'.$this->faker->unique()->numberBetween(1000, 9999), 'question_type' => 'single_choice', 'title' => 'Câu hỏi mẫu', 'stem' => 'Nội dung câu hỏi mẫu', 'difficulty' => 'medium', 'bloom_level' => 'understand', 'default_score' => 1, 'status' => 'draft', 'owner_id' => 1, 'metadata' => []];
    }
}
