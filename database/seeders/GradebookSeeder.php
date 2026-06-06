<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\GradeApprovalBatch;
use App\Models\GradeCategory;
use App\Models\GradeItem;
use App\Models\GradeSummary;
use App\Models\Gradebook;
use App\Models\LearnerGrade;
use App\Models\LmsUser;
use App\Services\GradeFormulaService;
use Illuminate\Database\Seeder;

class GradebookSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 1;
        $courses = Course::query()->where('tenant_id', $tenantId)->limit(20)->get();
        if ($courses->isEmpty()) return;
        $students = LmsUser::query()->where('tenant_id', $tenantId)->where('user_type', 'student')->limit(63)->get();
        $formula = app(GradeFormulaService::class);

        foreach (range(1, 20) as $i) {
            $course = $courses[($i - 1) % $courses->count()];
            $status = $i % 5 === 0 ? 'locked' : 'active';
            $gradebook = Gradebook::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'course_id' => $course->id, 'title' => 'Sổ điểm '.$course->code.' - Lớp demo '.$i],
                ['class_id' => 1000 + $i, 'grading_scheme' => 'weighted', 'status' => $status, 'settings' => ['pass_percent' => 50, 'show_student_approval_status' => true], 'created_by' => 1, 'locked_by' => $status === 'locked' ? 1 : null, 'locked_at' => $status === 'locked' ? now() : null]
            );

            foreach ([['Quiz', 30], ['Assignment', 30], ['Attendance', 10], ['Final', 30]] as $order => [$title, $weight]) {
                $category = GradeCategory::query()->updateOrCreate(['tenant_id' => $tenantId, 'gradebook_id' => $gradebook->id, 'title' => $title], ['weight' => $weight, 'max_score' => 10, 'aggregation_method' => 'weighted', 'sort_order' => $order + 1]);
                GradeItem::query()->updateOrCreate(['tenant_id' => $tenantId, 'gradebook_id' => $gradebook->id, 'title' => $title], ['category_id' => $category->id, 'source_type' => strtolower($title) === 'final' ? 'manual' : strtolower($title), 'source_id' => null, 'max_score' => 10, 'weight' => $weight, 'required' => true, 'sort_order' => $order + 1, 'settings' => ['demo' => true]]);
            }

            $items = $gradebook->items()->get();
            foreach ($students as $student) {
                foreach ($items as $item) {
                    $score = round((55 + (crc32($gradebook->id.'-'.$item->id.'-'.$student->id) % 46)) / 10, 2);
                    LearnerGrade::query()->updateOrCreate(['tenant_id' => $tenantId, 'grade_item_id' => $item->id, 'user_id' => $student->id], ['gradebook_id' => $gradebook->id, 'raw_score' => $score, 'final_score' => $score, 'pass_status' => $score >= 5 ? 'passed' : 'failed', 'source_status' => $status === 'locked' ? 'locked' : 'final', 'feedback' => $score < 5 ? 'Cần bổ sung bài còn thiếu.' : null, 'updated_by' => 1]);
                }
                $formula->calculateLearner($gradebook, $items, $student->id);
            }

            $batchStatus = match ($i % 4) { 0 => 'locked', 1 => 'submitted', 2 => 'approved', default => 'draft' };
            GradeApprovalBatch::query()->updateOrCreate(['tenant_id' => $tenantId, 'gradebook_id' => $gradebook->id, 'title' => 'Batch duyệt điểm '.$i], ['status' => $batchStatus, 'submitted_by' => 1, 'approved_by' => in_array($batchStatus, ['approved','locked'], true) ? 1 : null, 'locked_by' => $batchStatus === 'locked' ? 1 : null, 'submitted_at' => $batchStatus !== 'draft' ? now()->subDays(3) : null, 'approved_at' => in_array($batchStatus, ['approved','locked'], true) ? now()->subDays(2) : null, 'locked_at' => $batchStatus === 'locked' ? now()->subDay() : null, 'metadata' => ['demo' => true]]);
            if ($batchStatus !== 'draft') GradeSummary::query()->where('gradebook_id', $gradebook->id)->update(['status' => $batchStatus === 'submitted' ? 'pending_approval' : ($batchStatus === 'locked' ? 'locked' : 'approved')]);
        }
    }
}
