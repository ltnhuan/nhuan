<?php

namespace Tests\Feature;

use App\Models\ClassSection;
use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\ExamEnrollment;
use App\Models\Enrollment;
use App\Models\LmsUser;
use App\Models\Tenant;
use Database\Seeders\CoreSeeder;
use Database\Seeders\DemoLearningContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DemoLearningContentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_populates_empty_demo_enrolled_course_with_lessons(): void
    {
        $this->seed(CoreSeeder::class);

        $tenant = Tenant::query()->where('code', 'VABIS')->firstOrFail();
        $student = LmsUser::query()->where('tenant_id', $tenant->id)->where('email', 'sv.lms@vabis.edu.vn')->firstOrFail();
        $teacher = LmsUser::query()->where('tenant_id', $tenant->id)->where('email', 'gv.lms@vabis.edu.vn')->firstOrFail();

        $course = Course::query()->create([
            'tenant_id' => $tenant->id,
            'code' => 'COURSE-HSK1',
            'title' => 'Chương trình HSK1',
            'slug' => Str::slug('Chương trình HSK1'),
            'short_description' => 'Khóa demo HSK rỗng cần backfill học liệu.',
            'description' => 'Khóa demo HSK rỗng cần backfill học liệu.',
            'level' => 'Ngoại ngữ',
            'course_type' => 'language',
            'status' => 'published',
            'visibility' => 'tenant',
            'language' => 'vi',
            'estimated_hours' => 90,
            'owner_id' => $teacher->id,
            'settings' => ['seed' => 'test'],
        ]);

        $class = ClassSection::query()->create([
            'tenant_id' => $tenant->id,
            'course_id' => $course->id,
            'code' => 'HSK1-DEMO-TEST',
            'name' => 'HSK1 Demo Test',
            'section_type' => 'class_section',
            'delivery_mode' => 'blended',
            'status' => 'active',
            'capacity' => 30,
            'metadata' => ['seed' => 'test'],
        ]);

        Enrollment::query()->create([
            'tenant_id' => $tenant->id,
            'course_id' => $course->id,
            'class_section_id' => $class->id,
            'user_id' => $student->id,
            'source' => 'seed',
            'status' => 'active',
            'completion_percent' => 0,
            'risk_score' => 12,
            'enrolled_at' => now(),
            'activated_at' => now(),
            'metadata' => ['seed' => 'test'],
        ]);

        $this->assertSame(0, CourseComponent::query()->where('course_id', $course->id)->count());

        $this->seed(DemoLearningContentSeeder::class);

        $this->assertSame(12, CourseComponent::query()->where('course_id', $course->id)->count());

        $quiz = CourseComponent::query()
            ->where('course_id', $course->id)
            ->where('component_type', 'quiz')
            ->firstOrFail();
        $examId = (int) $quiz->config['exam_id'];

        $this->assertGreaterThan(0, $examId);
        $this->assertTrue(
            ExamEnrollment::query()
                ->where('tenant_id', $tenant->id)
                ->where('exam_id', $examId)
                ->where('user_id', $student->id)
                ->where('status', 'assigned')
                ->exists()
        );

        $this->withHeaders([
            'X-Tenant-Code' => 'VABIS',
            'X-Demo-User-Email' => 'sv.lms@vabis.edu.vn',
        ])
            ->getJson("/api/v1/courses/{$course->id}/studio")
            ->assertOk()
            ->assertJsonCount(2, 'outline')
            ->assertJsonPath('outline.0.children.0.components.0.component_type', 'text')
            ->assertJsonPath('outline.0.children.0.components.1.component_type', 'video');

        $this->withHeaders([
            'X-Tenant-Code' => 'VABIS',
            'X-Demo-User-Email' => 'sv.lms@vabis.edu.vn',
        ])
            ->postJson("/api/v1/exams/{$examId}/attempts/start", ['source' => 'feature_test'])
            ->assertCreated()
            ->assertJsonPath('exam_id', $examId)
            ->assertJsonPath('user_id', $student->id);
    }
}
