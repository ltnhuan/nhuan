<?php

namespace Database\Factories;

use App\Models\CourseVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseVersionFactory extends Factory
{
    protected $model = CourseVersion::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'course_id' => 1,
            'version' => '1',
            'title_snapshot' => 'Khóa học mẫu',
            'structure_snapshot' => [],
            'change_note' => 'Snapshot kiểm thử',
            'created_by' => 1,
        ];
    }
}
