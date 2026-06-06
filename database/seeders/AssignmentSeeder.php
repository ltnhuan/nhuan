<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\AssignmentGrade;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\LmsUser;
use App\Models\Rubric;
use App\Models\RubricCriterion;
use App\Models\RubricLevel;
use App\Models\Tenant;
use App\Services\AssignmentService;
use Illuminate\Database\Seeder;

class AssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('code', 'VABIS')->firstOrFail();
        $courses = Course::query()->where('tenant_id', $tenant->id)->take(10)->get();
        $students = LmsUser::query()->where('tenant_id', $tenant->id)->where('user_type', 'student')->take(1000)->get();
        $teachers = LmsUser::query()->where('tenant_id', $tenant->id)->where('user_type', 'teacher')->take(20)->get();
        if ($courses->isEmpty() || $students->isEmpty()) {
            return;
        }

        $rubrics = collect();
        for ($i = 1; $i <= 10; $i++) {
            $rubric = Rubric::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'title' => 'Rubric bài tập mẫu '.$i],
                ['course_id' => $courses[($i - 1) % $courses->count()]->id, 'description' => 'Rubric động dùng chấm bài tập thực hành/project.', 'max_score' => 10, 'status' => 'published', 'created_by' => $teachers->first()?->id ?? 1]
            );
            foreach (['Độ đúng yêu cầu', 'Chất lượng triển khai', 'Trình bày và minh chứng'] as $idx => $title) {
                $criterion = RubricCriterion::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'rubric_id' => $rubric->id, 'title' => $title],
                    ['description' => 'Tiêu chí '.$title, 'max_score' => $idx === 0 ? 4 : 3, 'sort_order' => $idx + 1, 'metadata' => []]
                );
                foreach ([['Xuất sắc', $criterion->max_score], ['Đạt', round($criterion->max_score * 0.7, 2)], ['Cần cải thiện', round($criterion->max_score * 0.4, 2)]] as $levelIdx => [$name, $score]) {
                    RubricLevel::query()->updateOrCreate(
                        ['tenant_id' => $tenant->id, 'criterion_id' => $criterion->id, 'level_name' => $name],
                        ['description' => $name.' cho '.$title, 'score' => $score, 'sort_order' => $levelIdx + 1]
                    );
                }
            }
            $rubrics->push($rubric);
        }

        $types = ['individual','group','class','project','practice'];
        $submissionTypes = ['file','text','url','video','mixed'];
        $assignments = collect();
        for ($i = 1; $i <= 30; $i++) {
            $course = $courses[($i - 1) % $courses->count()];
            $component = CourseComponent::query()->where('tenant_id', $tenant->id)->where('course_id', $course->id)->where('component_type', 'assignment')->first();
            $deadlineEngine = $this->deadlineEngine($i, $students);
            $assignments->push(Assignment::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'course_id' => $course->id, 'title' => 'Bài tập mẫu '.$i],
                [
                    'component_id' => $component?->id,
                    'description' => 'Hoàn thành bài tập theo yêu cầu, có thể nộp text/file/link/video.',
                    'assignment_type' => $types[$i % count($types)],
                    'submission_type' => $submissionTypes[$i % count($submissionTypes)],
                    'status' => $i % 10 === 0 ? 'draft' : 'published',
                    'open_at' => now()->subDays(15),
                    'due_at' => ($deadlineEngine['deadline_mode'] ?? 'absolute') === 'relative' ? null : ($deadlineEngine['due_at'] ?? now()->addDays(($i % 8) - 3)),
                    'allow_late' => (bool) ($deadlineEngine['allow_late'] ?? ($i % 3 !== 0)),
                    'late_penalty_config' => ['type' => 'percent_per_day', 'value' => 5],
                    'max_score' => 10,
                    'pass_score' => 5,
                    'max_submissions' => 3,
                    'rubric_id' => $rubrics[($i - 1) % $rubrics->count()]->id,
                    'settings' => [
                        'audience' => ['type' => $types[$i % count($types)], 'class_ids' => [1], 'group_ids' => [1, 2], 'user_ids' => $students->take(5)->pluck('id')->all()],
                        'deadline_engine' => $deadlineEngine,
                        'sis_sync_ready' => true,
                    ],
                    'created_by' => $teachers[$i % max(1, $teachers->count())]?->id ?? 1,
                ]
            ));
        }

        $created = 0;
        foreach ($students as $studentIndex => $student) {
            foreach ($assignments as $assignmentIndex => $assignment) {
                if ($created >= 1000) {
                    break 2;
                }
                $submittedAt = $this->sampleSubmittedAt($assignmentIndex, $studentIndex);
                $deadlineWindow = app(AssignmentService::class)->deadlineWindow($assignment, $student->id, $submittedAt);
                $isLate = app(AssignmentService::class)->isLate($deadlineWindow, $submittedAt);
                $state = $isLate ? 'late_submitted' : ['submitted','submitted','submitted','graded'][($studentIndex + $assignmentIndex) % 4];
                $submission = AssignmentSubmission::query()->updateOrCreate(
                    ['tenant_id'=>$tenant->id,'assignment_id'=>$assignment->id,'user_id'=>$student->id,'submission_no'=>1],
                    [
                        'group_id' => $assignment->assignment_type === 'group' ? (($studentIndex % 8) + 1) : null,
                        'status' => $state,
                        'submitted_at' => $submittedAt,
                        'content_text' => 'Bài làm mẫu của '.$student->full_name.' cho '.$assignment->title,
                        'content_url' => $assignment->submission_type === 'url' ? 'https://example.edu/submission/'.$created : null,
                        'metadata' => [
                            'sis_export_status' => $state === 'graded' ? 'ready' : 'pending',
                            'deadline_window' => $deadlineWindow,
                            'late_submission' => $isLate,
                        ],
                    ]
                );
                if ($state === 'graded') {
                    $score = rand(45, 100) / 10;
                    AssignmentGrade::query()->updateOrCreate(
                        ['tenant_id'=>$tenant->id,'submission_id'=>$submission->id],
                        ['assignment_id'=>$assignment->id,'user_id'=>$student->id,'score'=>$score,'max_score'=>10,'feedback'=>'Nhận xét mẫu: bài làm đã được chấm.', 'rubric_breakdown'=>['sample'=>true], 'ai_suggested_score'=>$score, 'ai_feedback'=>'Gợi ý AI mẫu.', 'grading_status'=>'final', 'graded_by'=>$teachers->first()?->id ?? 1]
                    );
                    $submission->forceFill(['total_score'=>$score,'feedback'=>'Nhận xét mẫu: bài làm đã được chấm.','graded_by'=>$teachers->first()?->id ?? 1,'graded_at'=>now()])->save();
                }
                $created++;
            }
        }
    }

    private function deadlineEngine(int $index, $students): array
    {
        $base = [
            'open_at' => now()->subDays(15)->toISOString(),
            'allow_late' => $index % 3 !== 0,
        ];

        if ($index % 5 === 0) {
            $starts = [];
            foreach ($students->take(50) as $offset => $student) {
                $starts[$student->id] = now()->subDays(($offset % 4) + 1)->toISOString();
            }

            return $base + [
                'deadline_mode' => 'relative',
                'relative_duration_days' => 3 + ($index % 3),
                'relative_duration_hours' => 4,
                'relative_duration_minutes' => 0,
                'user_starts' => $starts,
            ];
        }

        return $base + [
            'deadline_mode' => 'absolute',
            'due_at' => now()->addDays(($index % 8) - 3)->toISOString(),
            'grace_period_ends_at' => $index % 4 === 0 ? now()->addDays(($index % 8) - 1)->toISOString() : null,
            'individual_deadlines' => $students->take(3)->map(fn ($student, $offset) => [
                'user_id' => $student->id,
                'due_at' => now()->addDays($offset + 2)->toISOString(),
                'note' => 'Gia hạn mẫu theo ILIAS parity',
            ])->values()->all(),
        ];
    }

    private function sampleSubmittedAt(int $assignmentIndex, int $studentIndex)
    {
        return ($assignmentIndex + $studentIndex) % 4 === 0
            ? now()->addDay()
            : now()->subDays(rand(0, 5));
    }
}
