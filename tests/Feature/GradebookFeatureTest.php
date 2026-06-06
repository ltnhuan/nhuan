<?php

namespace Tests\Feature;

use App\Contracts\SISGradeSyncContract;
use App\Models\AssignmentGrade;
use App\Models\Course;
use App\Models\ExamResult;
use App\Models\GradeChangeLog;
use App\Models\GradeItem;
use App\Models\Gradebook;
use App\Models\LearnerGrade;
use App\Models\LmsUser;
use App\Services\GradeApprovalService;
use App\Services\GradeFormulaService;
use App\Services\GradeImportService;
use App\Services\GradeItemService;
use App\Services\GradebookService;
use App\Services\LearnerGradeService;
use Database\Seeders\CoreSeeder;
use Database\Seeders\CourseStudioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradebookFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->seed(CourseStudioSeeder::class);
    }

    public function test_weighted_grade_calculation_is_correct(): void
    {
        [$gradebook, $student, $items] = $this->gradebookWithItems();
        LearnerGrade::query()->create(['tenant_id' => 1, 'gradebook_id' => $gradebook->id, 'grade_item_id' => $items[0]->id, 'user_id' => $student->id, 'raw_score' => 8, 'final_score' => 8, 'source_status' => 'final']);
        LearnerGrade::query()->create(['tenant_id' => 1, 'gradebook_id' => $gradebook->id, 'grade_item_id' => $items[1]->id, 'user_id' => $student->id, 'raw_score' => 6, 'final_score' => 6, 'source_status' => 'final']);

        $summary = app(GradeFormulaService::class)->calculateLearner($gradebook, collect($items), $student->id);

        $this->assertSame(70.0, (float) $summary->total_score);
        $this->assertSame(100.0, (float) $summary->max_score);
        $this->assertSame(70.0, (float) $summary->percent);
    }

    public function test_pull_quiz_scores_imports_exam_results(): void
    {
        [$gradebook, $student, $items] = $this->gradebookWithItems();
        $items[0]->forceFill(['source_type' => 'quiz', 'source_id' => 99])->save();
        $items[1]->forceFill(['source_type' => 'manual'])->save();
        ExamResult::query()->create(['tenant_id' => 1, 'exam_id' => 99, 'user_id' => $student->id, 'attempt_id' => 999, 'score' => 18, 'max_score' => 20, 'percent' => 90, 'pass_status' => 'passed']);

        $result = app(GradeImportService::class)->pullSources($gradebook, 1);

        $this->assertSame(1, $result['imported']);
        $this->assertDatabaseHas('learner_grades', ['grade_item_id' => $items[0]->id, 'user_id' => $student->id, 'final_score' => 9]);
    }

    public function test_pull_assignment_scores_imports_assignment_grades(): void
    {
        [$gradebook, $student, $items] = $this->gradebookWithItems();
        $items[0]->forceFill(['source_type' => 'manual'])->save();
        $items[1]->forceFill(['source_type' => 'assignment', 'source_id' => 77])->save();
        AssignmentGrade::query()->create(['tenant_id'=>1,'submission_id'=>123,'assignment_id'=>77,'user_id'=>$student->id,'score'=>16,'max_score'=>20,'feedback'=>'Đạt yêu cầu','grading_status'=>'approved','graded_by'=>1]);

        $result = app(GradeImportService::class)->pullSources($gradebook, 1);

        $this->assertSame(1, $result['imported']);
        $this->assertDatabaseHas('learner_grades', ['grade_item_id' => $items[1]->id, 'user_id' => $student->id, 'final_score' => 8, 'feedback' => 'Đạt yêu cầu']);
    }

    public function test_formula_gradebook_calculation_is_correct(): void
    {
        [$gradebook, $student, $items] = $this->gradebookWithItems();
        $gradebook->forceFill(['grading_scheme' => 'formula', 'settings' => ['formula' => '(quiz * 0.4) + (assignment * 0.6)', 'max_score' => 100, 'pass_percent' => 50]])->save();
        LearnerGrade::query()->create(['tenant_id' => 1, 'gradebook_id' => $gradebook->id, 'grade_item_id' => $items[0]->id, 'user_id' => $student->id, 'raw_score' => 8, 'final_score' => 8, 'source_status' => 'final']);
        LearnerGrade::query()->create(['tenant_id' => 1, 'gradebook_id' => $gradebook->id, 'grade_item_id' => $items[1]->id, 'user_id' => $student->id, 'raw_score' => 6, 'final_score' => 6, 'source_status' => 'final']);

        $summary = app(GradeFormulaService::class)->calculateLearner($gradebook->fresh(), collect($items), $student->id);

        $this->assertSame(68.0, (float) $summary->total_score);
        $this->assertSame(100.0, (float) $summary->max_score);
        $this->assertSame(68.0, (float) $summary->percent);
    }

    public function test_override_grade_writes_log(): void
    {
        [$gradebook, $student, $items] = $this->gradebookWithItems();
        $grade = LearnerGrade::query()->create(['tenant_id' => 1, 'gradebook_id' => $gradebook->id, 'grade_item_id' => $items[0]->id, 'user_id' => $student->id, 'raw_score' => 7, 'final_score' => 7, 'source_status' => 'final']);

        app(LearnerGradeService::class)->override($grade, 8.5, 1, 'Phúc khảo');

        $this->assertDatabaseHas('learner_grades', ['id' => $grade->id, 'final_score' => 8.5, 'source_status' => 'overridden']);
        $this->assertSame(1, GradeChangeLog::query()->where('gradebook_id', $gradebook->id)->count());
    }

    public function test_submit_approve_lock_and_locked_override_rule(): void
    {
        [$gradebook, $student, $items] = $this->gradebookWithItems();
        $grade = LearnerGrade::query()->create(['tenant_id' => 1, 'gradebook_id' => $gradebook->id, 'grade_item_id' => $items[0]->id, 'user_id' => $student->id, 'raw_score' => 7, 'final_score' => 7, 'source_status' => 'final']);
        app(GradeFormulaService::class)->recalculate($gradebook);

        $batch = app(GradeApprovalService::class)->submit($gradebook, 1);
        $this->assertSame('submitted', $batch->status);
        $this->assertSame('approved', app(GradeApprovalService::class)->approve($gradebook, 1)->status);
        $this->assertSame('locked', app(GradeApprovalService::class)->lock($gradebook, 1)->status);

        $this->expectException(\RuntimeException::class);
        app(LearnerGradeService::class)->override($grade->fresh(), 9, 1);
    }

    public function test_sync_sis_calls_contract(): void
    {
        [$gradebook] = $this->gradebookWithItems();
        app(GradeApprovalService::class)->submit($gradebook, 1);
        $this->app->bind(SISGradeSyncContract::class, fn () => new class implements SISGradeSyncContract {
            public function syncGradebook(Gradebook $gradebook, \App\Models\GradeApprovalBatch $batch): array { return ['called' => true]; }
        });

        $batch = app(GradeApprovalService::class)->syncToSis($gradebook);

        $this->assertSame('synced_to_sis', $batch->status);
        $this->assertTrue($batch->metadata['sis_result']['called']);
    }

    public function test_student_matrix_only_returns_own_row(): void
    {
        [$gradebook, $student, $items] = $this->gradebookWithItems();
        $other = LmsUser::query()->where('user_type', 'student')->where('id', '!=', $student->id)->firstOrFail();
        foreach ([$student, $other] as $learner) {
            LearnerGrade::query()->create(['tenant_id' => 1, 'gradebook_id' => $gradebook->id, 'grade_item_id' => $items[0]->id, 'user_id' => $learner->id, 'raw_score' => 8, 'final_score' => 8, 'source_status' => 'final']);
            app(GradeFormulaService::class)->calculateLearner($gradebook, collect($items), $learner->id);
        }

        $response = $this->withHeader('X-Tenant-Code', 'VABIS')
            ->withHeader('X-Demo-User-Email', $student->email)
            ->getJson("/api/v1/gradebooks/{$gradebook->id}/matrix");

        $response->assertOk();
        $this->assertCount(1, $response->json('rows'));
        $this->assertSame($student->id, $response->json('rows.0.user_id'));
    }

    private function gradebookWithItems(): array
    {
        $course = Course::query()->firstOrFail();
        $student = LmsUser::query()->where('user_type', 'student')->firstOrFail();
        $gradebook = app(GradebookService::class)->create(['tenant_id' => 1, 'course_id' => $course->id, 'title' => 'Gradebook test', 'grading_scheme' => 'weighted', 'created_by' => 1, 'settings' => ['pass_percent' => 50]]);
        $itemService = app(GradeItemService::class);
        $items = [
            $itemService->createItem($gradebook, ['title' => 'Quiz', 'source_type' => 'quiz', 'max_score' => 10, 'weight' => 50]),
            $itemService->createItem($gradebook, ['title' => 'Assignment', 'source_type' => 'assignment', 'max_score' => 10, 'weight' => 50]),
        ];
        return [$gradebook->fresh(), $student, $items];
    }
}
