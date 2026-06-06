<?php

namespace Tests\Feature;

use App\Models\ClassSection;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LmsUser;
use App\Models\TeacherAssignment;
use Database\Seeders\CoreSeeder;
use Database\Seeders\CourseStudioSeeder;
use Database\Seeders\EnrollmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->seed(CourseStudioSeeder::class);
    }

    public function test_enrollment_seed_creates_50000_records_for_virtual_table(): void
    {
        $this->seed(EnrollmentSeeder::class);

        $this->assertSame(50000, Enrollment::query()->count());

        $response = $this->adminRequest()->getJson('/api/v1/enrollment/records?virtual=1&per_page=200');

        $response->assertOk();
        $this->assertCount(200, $response->json('data'));
        $this->assertNotEmpty($response->json('next_cursor'));
    }

    public function test_manual_bulk_invite_self_and_sis_enrollment_sources_are_supported(): void
    {
        $section = $this->section();
        $students = LmsUser::query()->where('user_type', 'student')->limit(4)->get();

        $this->adminRequest()->postJson('/api/v1/enrollment/records', [
            'class_section_id' => $section->id,
            'user_id' => $students[0]->id,
            'source' => 'manual',
        ])->assertCreated();

        $this->adminRequest()->postJson('/api/v1/enrollment/records/bulk', [
            'source' => 'bulk',
            'rows' => [
                ['class_section_id' => $section->id, 'user_id' => $students[1]->id],
                ['class_section_id' => $section->id, 'user_id' => $students[2]->id, 'source' => 'sis', 'sis_enrollment_id' => 'SIS-100'],
            ],
        ])->assertOk()->assertJsonPath('success', 2);

        $this->adminRequest()->postJson("/api/v1/enrollment/sections/{$section->id}/invite", [
            'user_ids' => [$students[3]->id],
        ])->assertOk()->assertJsonPath('success', 1);

        $this->withHeader('X-Tenant-Code', 'VABIS')
            ->withHeader('X-Demo-User-Email', $students[0]->email)
            ->postJson("/api/v1/enrollment/sections/{$section->id}/self-enroll")
            ->assertCreated();

        $this->assertDatabaseHas('enrollments', ['class_section_id' => $section->id, 'user_id' => $students[0]->id, 'source' => 'self']);
        $this->assertDatabaseHas('enrollments', ['class_section_id' => $section->id, 'user_id' => $students[2]->id, 'source' => 'sis']);
        $this->assertDatabaseHas('enrollments', ['class_section_id' => $section->id, 'user_id' => $students[3]->id, 'status' => 'pending']);
    }

    public function test_bulk_action_and_analytics_cover_lifecycle(): void
    {
        $section = $this->section();
        $students = LmsUser::query()->where('user_type', 'student')->limit(3)->get();
        foreach ($students as $student) {
            $this->adminRequest()->postJson('/api/v1/enrollment/records', ['class_section_id' => $section->id, 'user_id' => $student->id])->assertCreated();
        }

        $ids = Enrollment::query()->where('class_section_id', $section->id)->pluck('id')->all();
        $this->adminRequest()->postJson('/api/v1/enrollment/records/bulk-action', ['ids' => $ids, 'action' => 'complete'])
            ->assertOk()
            ->assertJsonPath('updated', 3);

        $analytics = $this->adminRequest()->getJson("/api/v1/enrollment/analytics?class_section_id={$section->id}");
        $analytics->assertOk();
        $analytics->assertJsonPath('total_learners', 3);
        $analytics->assertJsonPath('completion_rate', 100);
    }

    public function test_api_import_and_teacher_assignment_are_tracked(): void
    {
        $section = $this->section();
        $student = LmsUser::query()->where('user_type', 'student')->firstOrFail();
        $teacher = LmsUser::query()->where('user_type', 'teacher')->firstOrFail();

        $this->adminRequest()->postJson('/api/v1/enrollment/imports', [
            'rows' => [['section_code' => $section->code, 'user_code' => $student->code, 'source' => 'api']],
        ])->assertAccepted()->assertJsonPath('status', 'completed');

        $this->adminRequest()->postJson("/api/v1/enrollment/sections/{$section->id}/teachers", [
            'user_id' => $teacher->id,
            'role' => 'main_teacher',
        ])->assertCreated();

        $this->assertDatabaseHas('enrollments', ['class_section_id' => $section->id, 'user_id' => $student->id, 'source' => 'api']);
        $this->assertSame(1, TeacherAssignment::query()->where('class_section_id', $section->id)->where('role', 'main_teacher')->count());
    }

    private function section(): ClassSection
    {
        $course = Course::query()->firstOrFail();
        return ClassSection::query()->create([
            'tenant_id' => 1,
            'course_id' => $course->id,
            'code' => 'TST-SEC-001',
            'name' => 'Test Class Section',
            'section_type' => 'class_section',
            'status' => 'active',
        ]);
    }

    private function adminRequest(): self
    {
        return $this->withHeader('X-Tenant-Code', 'VABIS')->withHeader('X-Demo-User-Email', 'admin.lms@vabis.edu.vn');
    }
}
