<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\CourseSection;
use App\Models\LearningCompletion;
use App\Models\LmsUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Rubric;
use App\Services\AssignmentGradingService;
use App\Services\AssignmentService;
use App\Services\Core\CorePermissionService;
use App\Services\RubricService;
use App\Services\SubmissionService;
use Carbon\Carbon;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssignmentManagementFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
    }

    public function test_create_assignment(): void
    {
        [$course] = $this->courseContext();
        $assignment = app(AssignmentService::class)->create([
            'tenant_id' => 1,
            'course_id' => $course->id,
            'title' => 'Bài tập kiểm thử',
            'max_score' => 10,
            'pass_score' => 5,
            'created_by' => 1,
        ]);

        $this->assertSame('draft', $assignment->status);
        $this->assertDatabaseHas('assignments', ['id' => $assignment->id, 'title' => 'Bài tập kiểm thử']);
    }

    public function test_submit_assignment(): void
    {
        [$assignment, $student] = $this->publishedAssignment();
        $submission = app(SubmissionService::class)->submit($assignment, $student->id, ['content_text' => 'Bài làm text']);

        $this->assertSame('submitted', $submission->status);
        $this->assertSame(1, $submission->submission_no);
    }

    public function test_late_submission_is_marked_late(): void
    {
        [$assignment, $student] = $this->publishedAssignment(['due_at' => now()->subDay(), 'allow_late' => true]);
        $submission = app(SubmissionService::class)->submit($assignment, $student->id, ['content_url' => 'https://example.edu/video']);

        $this->assertSame('late_submitted', $submission->status);
        $this->assertDatabaseHas('assignment_events', ['submission_id' => $submission->id, 'event_type' => 'late']);
    }

    public function test_assignment_schedule_supports_individual_deadline_and_grace_period(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-06 09:00:00'));
        [$assignment, $student] = $this->publishedAssignment([
            'due_at' => now()->subDay(),
            'allow_late' => true,
        ]);

        app(AssignmentService::class)->schedule($assignment, [
            'deadline_mode' => 'absolute',
            'open_at' => now()->subDays(3),
            'due_at' => now()->subDay(),
            'allow_late' => true,
            'grace_period_ends_at' => now()->addDay(),
            'individual_deadlines' => [
                ['user_id' => $student->id, 'due_at' => now()->addDays(2)],
            ],
        ]);

        $window = app(AssignmentService::class)->deadlineWindow($assignment->fresh(), $student->id);

        $this->assertSame('open', $window['phase']);
        $this->assertSame(now()->addDays(2)->toISOString(), $window['official_due_at']);
        $this->assertSame(now()->addDays(2)->toISOString(), $window['effective_due_at']);
        Carbon::setTestNow();
    }

    public function test_relative_deadline_starts_on_first_submission_and_is_audited(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-06 10:00:00'));
        [$assignment, $student] = $this->publishedAssignment([
            'due_at' => null,
            'allow_late' => false,
        ]);

        app(AssignmentService::class)->schedule($assignment, [
            'deadline_mode' => 'relative',
            'open_at' => now()->subHour(),
            'relative_duration_hours' => 2,
        ]);

        $submission = app(SubmissionService::class)->submit($assignment->fresh(), $student->id, ['content_text' => 'Bài làm relative']);
        $window = $submission->metadata['deadline_window'];

        $this->assertSame('submitted', $submission->status);
        $this->assertSame('relative', $window['deadline_mode']);
        $this->assertSame(now()->toISOString(), $window['relative_started_at']);
        $this->assertSame(now()->addHours(2)->toISOString(), $window['official_due_at']);
        Carbon::setTestNow();
    }

    public function test_grace_period_cutoff_blocks_submission_after_effective_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-06 12:00:00'));
        [$assignment, $student] = $this->publishedAssignment([
            'due_at' => now()->subDays(2),
            'allow_late' => true,
        ]);

        app(AssignmentService::class)->schedule($assignment, [
            'deadline_mode' => 'absolute',
            'due_at' => now()->subDays(2),
            'allow_late' => true,
            'grace_period_ends_at' => now()->subDay(),
        ]);

        try {
            app(SubmissionService::class)->submit($assignment->fresh(), $student->id, ['content_text' => 'Quá grace']);
            $this->fail('Submission after grace cutoff should be blocked.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('Bài tập đã quá hạn nộp.', $exception->getMessage());
        }

        Carbon::setTestNow();
    }

    public function test_cannot_exceed_max_submissions(): void
    {
        [$assignment, $student] = $this->publishedAssignment(['max_submissions' => 1]);
        $service = app(SubmissionService::class);
        $service->submit($assignment, $student->id, ['content_text' => 'Lần 1']);

        $this->expectException(\InvalidArgumentException::class);
        $service->resubmit(AssignmentSubmission::query()->firstOrFail(), ['content_text' => 'Lần 2']);
    }

    public function test_rubric_grading_calculates_total_score(): void
    {
        [$assignment, $student, $rubric] = $this->publishedAssignmentWithRubric();
        $submission = app(SubmissionService::class)->submit($assignment, $student->id, ['content_text' => 'Bài làm rubric']);
        $criteria = $rubric->criteria()->get();

        $grade = app(AssignmentGradingService::class)->gradeSubmission($submission, [
            'rubric_breakdown' => [
                $criteria[0]->id => 3.5,
                $criteria[1]->id => 3,
                $criteria[2]->id => 2,
            ],
            'feedback' => 'Đạt yêu cầu.',
        ], 1);

        $this->assertSame(8.5, (float) $grade->score);
        $this->assertDatabaseHas('assignment_submissions', ['id' => $submission->id, 'status' => 'graded']);
    }

    public function test_pass_score_completes_learning_path_component(): void
    {
        [$assignment, $student] = $this->publishedAssignment(['pass_score' => 5], withComponent: true);
        $submission = app(SubmissionService::class)->submit($assignment, $student->id, ['content_text' => 'Bài đạt']);
        app(AssignmentGradingService::class)->gradeSubmission($submission, ['score' => 7, 'feedback' => 'Đạt'], 1);

        $this->assertDatabaseHas('learning_completions', [
            'tenant_id' => 1,
            'user_id' => $student->id,
            'course_id' => $assignment->course_id,
            'component_id' => $assignment->component_id,
            'status' => 'completed',
        ]);
    }

    public function test_student_assignment_detail_only_contains_own_submissions(): void
    {
        [$assignment, $student] = $this->publishedAssignment();
        $other = LmsUser::query()->where('user_type', 'student')->where('id', '!=', $student->id)->firstOrFail();
        app(SubmissionService::class)->submit($assignment, $student->id, ['content_text' => 'Của tôi']);
        app(SubmissionService::class)->submit($assignment, $other->id, ['content_text' => 'Của người khác']);

        $response = $this->withHeader('X-Tenant-Code', 'VABIS')
            ->withHeader('X-Demo-User-Email', $student->email)
            ->getJson("/api/v1/assignments/{$assignment->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('submissions'));
        $this->assertSame($student->id, $response->json('submissions.0.user_id'));
    }

    public function test_teacher_can_view_assigned_course_scope(): void
    {
        [$course] = $this->courseContext();
        $teacher = LmsUser::query()->where('user_type', 'teacher')->firstOrFail();
        $role = Role::query()->create(['tenant_id'=>1,'name'=>'assignment_course_teacher','guard_name'=>'web','display_name'=>'Assignment course teacher','scope'=>'course']);
        $permissionId = Permission::query()->where('key', 'assignment.grade')->value('id');
        DB::table('role_permission')->insert(['role_id'=>$role->id,'permission_id'=>$permissionId]);
        DB::table('user_role_scope')->insert(['user_id'=>$teacher->id,'role_id'=>$role->id,'tenant_id'=>1,'course_id'=>$course->id]);
        app(CorePermissionService::class)->clearUserCache($teacher->id, 1);

        $this->assertTrue(app(CorePermissionService::class)->can($teacher, 'assignment.grade', ['tenant_id'=>1,'course_id'=>$course->id]));
    }

    private function publishedAssignment(array $overrides = [], bool $withComponent = false): array
    {
        [$course, $section, $component] = $this->courseContext($withComponent);
        $student = LmsUser::query()->where('user_type', 'student')->firstOrFail();
        $assignment = Assignment::query()->create(array_replace([
            'tenant_id' => 1,
            'course_id' => $course->id,
            'component_id' => $withComponent ? $component->id : null,
            'title' => 'Assignment '.uniqid(),
            'description' => 'Yêu cầu bài tập',
            'assignment_type' => 'individual',
            'submission_type' => 'mixed',
            'status' => 'published',
            'open_at' => now()->subDay(),
            'due_at' => now()->addDay(),
            'allow_late' => false,
            'max_score' => 10,
            'pass_score' => 5,
            'max_submissions' => 3,
            'settings' => [],
            'created_by' => 1,
        ], $overrides));
        return [$assignment, $student];
    }

    private function publishedAssignmentWithRubric(): array
    {
        [$assignment, $student] = $this->publishedAssignment();
        $rubric = app(RubricService::class)->create(['tenant_id'=>1,'course_id'=>$assignment->course_id,'title'=>'Rubric test','max_score'=>10,'status'=>'published','created_by'=>1]);
        foreach ([4, 3, 3] as $index => $maxScore) {
            app(RubricService::class)->addCriterion($rubric, ['title'=>'Tiêu chí '.($index + 1),'max_score'=>$maxScore,'sort_order'=>$index + 1]);
        }
        $assignment->forceFill(['rubric_id' => $rubric->id])->save();
        return [$assignment->fresh('rubric.criteria'), $student, $rubric->fresh('criteria')];
    }

    private function courseContext(bool $withComponent = false): array
    {
        $course = Course::query()->create(['tenant_id'=>1,'code'=>'ASSIGN-'.uniqid(),'title'=>'Khóa assignment','slug'=>'khoa-assignment-'.uniqid(),'level'=>'college','course_type'=>'blended','status'=>'published','visibility'=>'internal','language'=>'vi','estimated_hours'=>10,'owner_id'=>1,'settings'=>[]]);
        $section = CourseSection::query()->create(['tenant_id'=>1,'course_id'=>$course->id,'type'=>'unit','title'=>'Chương 1','sort_order'=>1,'status'=>'published','settings'=>[]]);
        $component = CourseComponent::query()->create(['tenant_id'=>1,'course_id'=>$course->id,'section_id'=>$section->id,'component_type'=>'assignment','title'=>'Component assignment','config'=>['min_score'=>5],'sort_order'=>1,'required'=>true,'status'=>'published']);
        return [$course, $section, $component];
    }
}
