<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\CourseSection;
use App\Models\DataIntegrityIssue;
use App\Models\Gradebook;
use App\Models\LmsUser;
use App\Models\Tenant;
use App\Services\CourseStudioService;
use App\Services\DataIntegrityService;
use App\Services\DigitalCredentialService;
use App\Services\GradebookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DataIntegrityFeatureTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private LmsUser $learner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::query()->create([
            'code' => 'VABIS',
            'name' => 'VABIS',
            'status' => 'active',
            'settings' => [],
        ]);

        $this->learner = LmsUser::query()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'SV001',
            'full_name' => 'Learner',
            'email' => 'learner@example.test',
            'user_type' => 'student',
            'status' => 'active',
            'metadata' => [],
        ]);
    }

    public function test_integrity_check_detects_course_section_without_unit(): void
    {
        $course = $this->course('COURSE-NO-UNIT');
        CourseSection::query()->create([
            'tenant_id' => $this->tenant->id,
            'course_id' => $course->id,
            'type' => 'section',
            'title' => 'Chương 1',
            'sort_order' => 1,
            'status' => 'draft',
            'settings' => [],
        ]);

        app(DataIntegrityService::class)->runModuleChecks('course', $this->tenant->id);

        $this->assertDatabaseHas('data_integrity_issues', [
            'tenant_id' => $this->tenant->id,
            'issue_key' => 'section_without_unit',
            'status' => 'open',
        ]);
    }

    public function test_integrity_check_detects_quiz_exam_without_questions(): void
    {
        $course = $this->course('QUIZ-NO-QUESTIONS');
        DB::table('exams')->insert([
            'tenant_id' => $this->tenant->id,
            'course_id' => $course->id,
            'code' => 'EX-NO-Q',
            'title' => 'Quiz thiếu câu hỏi',
            'exam_type' => 'quiz',
            'delivery_mode' => 'self_paced',
            'status' => 'published',
            'total_score' => 10,
            'max_attempts' => 1,
            'shuffle_questions' => true,
            'shuffle_options' => true,
            'show_result_mode' => 'after_close',
            'show_correct_answers' => false,
            'settings' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(DataIntegrityService::class)->runModuleChecks('exam', $this->tenant->id);

        $this->assertDatabaseHas('data_integrity_issues', [
            'tenant_id' => $this->tenant->id,
            'issue_key' => 'exam_published_without_questions',
            'status' => 'open',
        ]);
    }

    public function test_integrity_check_detects_grade_exceeding_max_score(): void
    {
        $course = $this->course('GRADE-BAD');
        $gradebookId = DB::table('gradebooks')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'course_id' => $course->id,
            'title' => 'Gradebook',
            'grading_scheme' => 'weighted',
            'status' => 'active',
            'settings' => json_encode([]),
            'created_by' => $this->learner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $itemId = DB::table('grade_items')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'gradebook_id' => $gradebookId,
            'source_type' => 'manual',
            'title' => 'Manual',
            'max_score' => 10,
            'weight' => 100,
            'required' => true,
            'sort_order' => 1,
            'settings' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('learner_grades')->insert([
            'tenant_id' => $this->tenant->id,
            'gradebook_id' => $gradebookId,
            'grade_item_id' => $itemId,
            'user_id' => $this->learner->id,
            'raw_score' => 20,
            'final_score' => 20,
            'source_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(DataIntegrityService::class)->runModuleChecks('gradebook', $this->tenant->id);

        $this->assertDatabaseHas('data_integrity_issues', [
            'tenant_id' => $this->tenant->id,
            'issue_key' => 'learner_grade_exceeds_item_max_score',
            'status' => 'open',
        ]);
    }

    public function test_integrity_check_detects_invalid_certificate_issue(): void
    {
        $course = $this->course('CERT-BAD');
        $certificate = $this->certificate();
        DB::table('certificate_issues')->insert([
            'tenant_id' => $this->tenant->id,
            'certificate_id' => $certificate->id,
            'user_id' => $this->learner->id,
            'course_id' => $course->id,
            'issue_code' => 'CERT-BAD-001',
            'learner_name' => $this->learner->full_name,
            'certificate_title' => $certificate->title,
            'status' => 'issued',
            'issued_at' => now(),
            'qr_payload' => 'verify/CERT-BAD-001',
            'verification_hash' => hash('sha256', 'bad'),
            'verification_url' => 'verify/CERT-BAD-001',
            'language' => 'vi',
            'field_values' => json_encode([]),
            'sis_payload' => json_encode([]),
            'blockchain_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(DataIntegrityService::class)->runModuleChecks('certificate', $this->tenant->id);

        $this->assertDatabaseHas('data_integrity_issues', [
            'tenant_id' => $this->tenant->id,
            'issue_key' => 'certificate_issued_without_completion',
            'status' => 'open',
        ]);
    }

    public function test_integrity_check_detects_duplicate_sis_mapping_external_id(): void
    {
        $systemA = $this->integrationSystem('SIS-A');
        $systemB = $this->integrationSystem('SIS-B');

        foreach ([$systemA, $systemB] as $systemId) {
            DB::table('integration_mappings')->insert([
                'tenant_id' => $this->tenant->id,
                'system_id' => $systemId,
                'entity_type' => 'student',
                'local_id' => (string) Str::uuid(),
                'external_id' => 'SIS-STUDENT-001',
                'mapping_status' => 'active',
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(DataIntegrityService::class)->runModuleChecks('sis', $this->tenant->id);

        $this->assertDatabaseHas('data_integrity_issues', [
            'tenant_id' => $this->tenant->id,
            'issue_key' => 'sis_duplicate_external_id',
            'status' => 'open',
        ]);
    }

    public function test_auto_fix_adds_default_completion_rule_to_required_component(): void
    {
        $component = $this->requiredComponentWithoutCompletionRule();

        app(DataIntegrityService::class)->runModuleChecks('course', $this->tenant->id);
        $issue = DataIntegrityIssue::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('issue_key', 'component_required_without_completion_rule')
            ->where('entity_id', (string) $component->id)
            ->firstOrFail();

        app(DataIntegrityService::class)->autoFixIssue($issue, $this->learner->id);

        $this->assertSame('view', CourseComponent::query()->findOrFail($component->id)->config['completion_rule']['type']);
        $this->assertSame('fixed', $issue->fresh()->status);
    }

    public function test_published_course_cannot_be_hard_deleted(): void
    {
        $course = $this->course('PUBLISHED-DELETE', 'published');

        $this->withHeaders(['X-Tenant-Code' => 'VABIS'])
            ->deleteJson("/api/v1/courses/{$course->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'status' => 'published']);
    }

    public function test_locked_gradebook_cannot_be_updated(): void
    {
        $course = $this->course('LOCKED-GB');
        $gradebook = Gradebook::query()->create([
            'tenant_id' => $this->tenant->id,
            'course_id' => $course->id,
            'title' => 'Locked',
            'grading_scheme' => 'weighted',
            'status' => 'locked',
            'settings' => [],
            'created_by' => $this->learner->id,
            'locked_by' => $this->learner->id,
            'locked_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        app(GradebookService::class)->update($gradebook, ['title' => 'Changed']);
    }

    public function test_course_publish_is_blocked_when_checklist_fails(): void
    {
        $course = $this->course('BAD-PUBLISH', 'approved');

        $this->expectException(\InvalidArgumentException::class);
        app(CourseStudioService::class)->publishCourse($course, $this->learner->id);
    }

    public function test_certificate_issue_is_blocked_until_learner_is_eligible(): void
    {
        $course = $this->course('CERT-GUARD');
        $certificate = $this->certificate();

        $this->expectException(\RuntimeException::class);
        app(DigitalCredentialService::class)->issueCertificate($this->tenant->id, [
            'certificate_id' => $certificate->id,
            'user_id' => $this->learner->id,
            'course_id' => $course->id,
        ]);
    }

    private function course(string $code, string $status = 'draft'): Course
    {
        return Course::query()->create([
            'tenant_id' => $this->tenant->id,
            'code' => $code,
            'title' => $code,
            'slug' => Str::slug($code),
            'level' => 'college',
            'course_type' => 'online',
            'status' => $status,
            'visibility' => 'internal',
            'language' => 'vi',
            'settings' => [],
        ]);
    }

    private function certificate(): Certificate
    {
        return Certificate::query()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'CERT-'.Str::random(6),
            'title' => 'Certificate',
            'credential_type' => 'course_certificate',
            'issuer_name' => 'EraLMS',
            'rules' => [],
            'metadata' => [],
            'status' => 'active',
        ]);
    }

    private function integrationSystem(string $code): int
    {
        return DB::table('integration_systems')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'code' => $code,
            'name' => $code,
            'system_type' => 'sis',
            'base_url' => 'https://sis.example.test',
            'auth_type' => 'api_key',
            'status' => 'active',
            'settings' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function requiredComponentWithoutCompletionRule(): CourseComponent
    {
        $course = $this->course('AUTO-FIX');
        $section = CourseSection::query()->create([
            'tenant_id' => $this->tenant->id,
            'course_id' => $course->id,
            'type' => 'section',
            'title' => 'Chương 1',
            'sort_order' => 1,
            'status' => 'draft',
            'settings' => [],
        ]);
        $unit = CourseSection::query()->create([
            'tenant_id' => $this->tenant->id,
            'course_id' => $course->id,
            'parent_id' => $section->id,
            'type' => 'unit',
            'title' => 'Bài 1',
            'sort_order' => 1,
            'status' => 'draft',
            'settings' => [],
        ]);

        return CourseComponent::query()->create([
            'tenant_id' => $this->tenant->id,
            'course_id' => $course->id,
            'section_id' => $unit->id,
            'component_type' => 'text',
            'title' => 'Text',
            'config' => [],
            'sort_order' => 1,
            'required' => true,
            'status' => 'draft',
        ]);
    }
}
