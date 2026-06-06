<?php

namespace Database\Seeders;

use App\Models\ClassSection;
use App\Models\Cohort;
use App\Models\CohortGroup;
use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamEnrollment;
use App\Models\ExamQuestion;
use App\Models\ExamResult;
use App\Models\LearningCompletion;
use App\Models\LmsUser;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\UserCourseProgress;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LearnerDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('code', 'VABIS')->firstOrFail();
        $teacher = LmsUser::query()->where('tenant_id', $tenant->id)->where('user_type', 'teacher')->first();

        $learner = LmsUser::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'email' => 'learner.test@vabis.edu.vn'],
            [
                'code' => 'SVTEST01',
                'full_name' => 'Thí sinh Test Learner',
                'user_type' => 'student',
                'status' => 'active',
                'metadata' => [
                    'demo' => true,
                    'demo_password' => 'learner123456',
                    'note' => 'Tài khoản test learner có đủ dữ liệu học và thi.',
                ],
            ]
        );

        $studentRole = Role::query()->where('tenant_id', $tenant->id)->where('name', 'student')->first();
        if ($studentRole) {
            DB::table('user_role_scope')->updateOrInsert([
                'user_id' => $learner->id,
                'role_id' => $studentRole->id,
                'tenant_id' => $tenant->id,
                'campus_id' => null,
                'academic_unit_id' => null,
                'course_id' => null,
                'class_id' => null,
            ]);
        }

        $course = Course::query()->where('tenant_id', $tenant->id)->where('code', 'COURSE001')->first()
            ?: Course::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $course->forceFill(['status' => 'published'])->save();

        $cohort = Cohort::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'K2026-TEST'],
            ['name' => 'Khóa test learner 2026', 'type' => 'academic', 'status' => 'active', 'metadata' => ['seed' => true]]
        );
        $group = CohortGroup::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'cohort_id' => $cohort->id, 'code' => 'LG-TEST-A'],
            ['name' => 'Nhóm test learner A', 'group_type' => 'learning_group', 'capacity' => 40, 'status' => 'active', 'metadata' => ['seed' => true]]
        );
        $class = ClassSection::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'TEST-LEARNER-CLASS'],
            [
                'course_id' => $course->id,
                'cohort_id' => $cohort->id,
                'cohort_group_id' => $group->id,
                'sis_section_id' => 'SIS-TEST-LEARNER',
                'name' => 'Lớp test learner - '.$course->title,
                'section_type' => 'class_section',
                'delivery_mode' => 'blended',
                'status' => 'active',
                'capacity' => 40,
                'starts_at' => now()->subWeek(),
                'ends_at' => now()->addMonths(2),
                'metadata' => ['seed' => true, 'purpose' => 'learner_dashboard_exam_results'],
            ]
        );

        Enrollment::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'class_section_id' => $class->id, 'user_id' => $learner->id],
            [
                'course_id' => $course->id,
                'cohort_id' => $cohort->id,
                'cohort_group_id' => $group->id,
                'source' => 'seed',
                'sis_enrollment_id' => 'SIS-ENR-SVTEST01',
                'status' => 'active',
                'completion_percent' => 66,
                'risk_score' => 8,
                'enrolled_at' => now()->subWeek(),
                'activated_at' => now()->subWeek(),
                'expires_at' => now()->addMonths(2),
                'metadata' => ['seed' => true],
            ]
        );

        $components = CourseComponent::query()
            ->where('tenant_id', $tenant->id)
            ->where('course_id', $course->id)
            ->orderBy('section_id')
            ->orderBy('sort_order')
            ->take(9)
            ->get();

        $bank = QuestionBank::query()->where('tenant_id', $tenant->id)->where('status', 'published')->first()
            ?: QuestionBank::query()->where('tenant_id', $tenant->id)->first();
        $exam = Exam::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'EXAM-LEARNER-TEST'],
            [
                'course_id' => $course->id,
                'question_bank_id' => $bank?->id,
                'title' => 'Bài thi mẫu cho thí sinh Test Learner',
                'description' => 'Dữ liệu mẫu để kiểm tra dashboard learner và trang kết quả thi theo lớp.',
                'exam_type' => 'quiz',
                'delivery_mode' => 'self_paced',
                'status' => 'published',
                'total_score' => 10,
                'pass_score' => 5,
                'duration_minutes' => 30,
                'max_attempts' => 3,
                'shuffle_questions' => false,
                'shuffle_options' => false,
                'show_result_mode' => 'immediately',
                'show_correct_answers' => true,
                'created_by' => $teacher?->id ?? 1,
                'approved_by' => $teacher?->id,
                'approved_at' => now()->subDays(3),
                'settings' => ['seed' => true],
            ]
        );

        $quizComponent = $components->firstWhere('component_type', 'quiz')
            ?: CourseComponent::query()->where('course_id', $course->id)->where('component_type', 'quiz')->first();
        if ($quizComponent) {
            $quizComponent->forceFill([
                'status' => 'published',
                'config' => array_merge($quizComponent->config ?? [], ['exam_id' => $exam->id, 'min_score' => 50]),
            ])->save();
            $exam->forceFill(['component_id' => $quizComponent->id])->save();
        }

        $completed = $components->take(6);
        foreach ($completed as $component) {
            $component->forceFill(['status' => 'published'])->save();
            LearningCompletion::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $learner->id, 'course_id' => $course->id, 'completion_type' => 'component', 'component_id' => $component->id],
                [
                    'section_id' => $component->section_id,
                    'status' => 'completed',
                    'progress_percent' => 100,
                    'score' => $component->component_type === 'quiz' ? 8 : null,
                    'completed_at' => now()->subDays(max(1, 7 - $component->sort_order)),
                    'source' => 'seed',
                    'metadata' => ['seed' => true],
                ]
            );
        }

        $totalComponents = CourseComponent::query()->where('course_id', $course->id)->count();
        $totalRequired = CourseComponent::query()->where('course_id', $course->id)->where('required', true)->count();
        UserCourseProgress::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $learner->id, 'course_id' => $course->id],
            [
                'status' => 'in_progress',
                'progress_percent' => 66,
                'completed_components_count' => $completed->count(),
                'total_components_count' => $totalComponents,
                'completed_required_count' => $completed->where('required', true)->count(),
                'total_required_count' => $totalRequired,
                'last_component_id' => $components->skip(6)->first()?->id ?? $quizComponent?->id,
                'last_accessed_at' => now()->subHours(2),
                'risk_level' => 'low',
                'metadata' => ['seed' => true],
            ]
        );

        $questions = Question::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['approved', 'published'])
            ->with(['options', 'matchingPairs', 'fillBlankAnswers'])
            ->take(10)
            ->get();
        foreach ($questions as $index => $question) {
            ExamQuestion::query()->updateOrCreate(
                ['exam_id' => $exam->id, 'question_id' => $question->id],
                ['tenant_id' => $tenant->id, 'score' => 1, 'sort_order' => $index + 1]
            );
        }

        ExamEnrollment::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'exam_id' => $exam->id, 'user_id' => $learner->id],
            [
                'course_id' => $course->id,
                'class_id' => $class->id,
                'status' => 'assigned',
                'assigned_by' => $teacher?->id ?? 1,
                'available_from' => now()->subWeek(),
                'available_until' => now()->addMonth(),
                'metadata' => ['seed' => true],
            ]
        );

        $attempt = ExamAttempt::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'exam_id' => $exam->id, 'user_id' => $learner->id, 'attempt_no' => 1],
            [
                'session_uuid' => (string) Str::uuid(),
                'status' => 'graded',
                'started_at' => now()->subDays(1)->subMinutes(35),
                'submitted_at' => now()->subDays(1),
                'graded_at' => now()->subDays(1)->addMinutes(2),
                'time_spent_seconds' => 2100,
                'score' => 8,
                'max_score' => 10,
                'pass_status' => 'passed',
                'suspicious_score' => 0,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Seeder Demo Browser',
                'metadata' => ['seed' => true],
            ]
        );

        foreach ($questions as $index => $question) {
            $options = $question->options->map(fn ($option) => $option->only(['option_key', 'content', 'media_url', 'sort_order', 'is_correct']))->values()->all();
            $attemptQuestion = ExamAttemptQuestion::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'attempt_id' => $attempt->id, 'question_id' => $question->id],
                [
                    'display_order' => $index + 1,
                    'score' => 1,
                    'question_snapshot' => $question->only(['id', 'code', 'question_type', 'title', 'stem', 'default_score', 'metadata']) + [
                        'matching_pairs' => $question->matchingPairs->toArray(),
                        'fill_blank_answers' => $question->fillBlankAnswers->toArray(),
                    ],
                    'options_snapshot' => $options,
                    'is_answered' => true,
                    'metadata' => ['client_options' => collect($options)->map(fn ($option) => collect($option)->except('is_correct')->all())->values()->all()],
                ]
            );
            $isCorrect = $index < 8;
            ExamAnswer::query()->updateOrCreate(
                ['attempt_id' => $attempt->id, 'attempt_question_id' => $attemptQuestion->id],
                [
                    'tenant_id' => $tenant->id,
                    'question_id' => $question->id,
                    'answer_data' => $this->answerFor($question, $isCorrect),
                    'is_correct' => $isCorrect,
                    'score' => $isCorrect ? 1 : 0,
                    'feedback' => $isCorrect ? 'Đúng.' : 'Cần xem lại nội dung bài học.',
                    'graded_by' => $teacher?->id,
                    'graded_at' => now()->subDay()->addMinutes(2),
                ]
            );
        }

        ExamResult::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'attempt_id' => $attempt->id],
            [
                'exam_id' => $exam->id,
                'user_id' => $learner->id,
                'score' => 8,
                'max_score' => 10,
                'percent' => 80,
                'pass_status' => 'passed',
                'published' => true,
                'published_at' => now()->subDay()->addMinutes(3),
                'approved_by' => $teacher?->id,
                'metadata' => ['seed' => true, 'class_id' => $class->id],
            ]
        );
    }

    private function answerFor(Question $question, bool $isCorrect): array
    {
        return match ($question->question_type) {
            'multiple_choice' => ['selected' => $isCorrect ? ['A', 'B'] : ['C']],
            'true_false' => ['selected' => $isCorrect ? 'A' : 'B'],
            'fill_blank' => ['blank_1' => $isCorrect ? 'EraLMS' : 'Sai'],
            'matching' => ['pairs' => $isCorrect ? ['CLO' => 'Course Learning Outcome', 'PLO' => 'Program Learning Outcome'] : ['CLO' => 'Program Learning Outcome']],
            'essay' => ['text' => $isCorrect ? 'Bài làm tự luận mẫu đạt yêu cầu.' : 'Bài làm chưa đủ ý.'],
            default => ['selected' => $isCorrect ? 'A' : 'B'],
        };
    }
}
