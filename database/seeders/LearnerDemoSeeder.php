<?php

namespace Database\Seeders;

use App\Models\ClassSection;
use App\Models\Cohort;
use App\Models\CohortGroup;
use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\DigitalPortfolio;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamAttemptQuestion;
use App\Models\ExamEnrollment;
use App\Models\ExamQuestion;
use App\Models\ExamResult;
use App\Models\GradeSummary;
use App\Models\Gradebook;
use App\Models\LearningCompletion;
use App\Models\LearnerSkill;
use App\Models\LearnerGrade;
use App\Models\LmsUser;
use App\Models\PortfolioItem;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Role;
use App\Models\SkillDefinition;
use App\Models\Tenant;
use App\Models\UserCourseProgress;
use App\Models\VideoAsset;
use App\Services\CareerPortfolioService;
use App\Services\GradeFormulaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
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
            foreach (['enrollment.view', 'exam.view'] as $permissionKey) {
                $permissionId = DB::table('permissions')->where('key', $permissionKey)->value('id');
                if ($permissionId) {
                    DB::table('role_permission')->updateOrInsert(['role_id' => $studentRole->id, 'permission_id' => $permissionId]);
                }
            }
            Cache::forget("eralms:permissions:{$tenant->id}:{$learner->id}");
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

        $lessonMedia = [
            'image' => '/demo-media/demo-image.svg',
            'audio' => 'https://interactive-examples.mdn.mozilla.net/media/cc0-audio/t-rex-roar.mp3',
            'video' => 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
        ];
        $videoComponent = $components->firstWhere('component_type', 'video')
            ?: CourseComponent::query()->where('course_id', $course->id)->where('component_type', 'video')->first();
        if ($videoComponent) {
            VideoAsset::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'component_id' => $videoComponent->id, 'title' => 'Video demo learner'],
                [
                    'course_id' => $course->id,
                    'original_filename' => 'flower.mp4',
                    'original_storage_path' => $lessonMedia['video'],
                    'duration_seconds' => 30,
                    'file_size' => 0,
                    'mime_type' => 'video/mp4',
                    'processing_status' => 'ready',
                    'visibility' => 'course',
                    'checksum' => 'demo-video',
                    'thumbnail_url' => 'https://placehold.co/1280x720/0f172a/ffffff?text=Video+Demo',
                    'settings' => ['external_url' => true, 'seed' => true],
                    'uploaded_by' => $teacher?->id,
                    'processed_at' => now(),
                ]
            );
            $videoComponent->forceFill(['status' => 'published', 'config' => array_merge($videoComponent->config ?? [], ['media_url' => $lessonMedia['video'], 'media_type' => 'video', 'estimated_minutes' => 1])])->save();
        }
        $textComponents = $components->where('component_type', 'text')->values();
        if ($textComponents->get(0)) {
            $textComponents->get(0)->forceFill(['status' => 'published', 'config' => array_merge($textComponents->get(0)->config ?? [], ['media_url' => $lessonMedia['image'], 'media_type' => 'image'])])->save();
        }
        if ($textComponents->get(1)) {
            $textComponents->get(1)->forceFill(['status' => 'published', 'config' => array_merge($textComponents->get(1)->config ?? [], ['media_url' => $lessonMedia['audio'], 'media_type' => 'audio'])])->save();
        }

        $bank = QuestionBank::query()->where('tenant_id', $tenant->id)->where('status', 'published')->first()
            ?: QuestionBank::query()->where('tenant_id', $tenant->id)->first();
        $admin = LmsUser::query()->where('tenant_id', $tenant->id)->where('email', 'admin.lms@vabis.edu.vn')->first();
        $studioQuiz = CourseComponent::query()->where('tenant_id', $tenant->id)->whereKey(462)->first();
        if ($studioQuiz) {
            $course = $studioQuiz->course;
        }

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
                'component_id' => $studioQuiz?->id,
                'max_attempts' => 99,
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

        $quizComponent = $studioQuiz ?: $components->firstWhere('component_type', 'quiz')
            ?: CourseComponent::query()->where('course_id', $course->id)->where('component_type', 'quiz')->first();
        if ($quizComponent) {
            $quizComponent->forceFill([
                'status' => 'published',
                'config' => array_merge($quizComponent->config ?? [], [
                    'exam_id' => $exam->id,
                    'exam_title' => $exam->title,
                    'exam_code' => $exam->code,
                    'exam_description' => $exam->description,
                    'questions_count' => 10,
                    'duration_minutes' => $exam->duration_minutes,
                    'pass_score' => $exam->pass_score,
                    'total_score' => $exam->total_score,
                    'max_attempts' => $exam->max_attempts,
                    'completion_rule' => ['type' => 'score', 'score' => $exam->pass_score],
                    'min_score' => 50,
                ]),
            ])->save();
            $exam->forceFill(['component_id' => $quizComponent->id])->save();
        }

        $learnerComponents = CourseComponent::query()
            ->where('course_id', $class->course_id)
            ->orderBy('section_id')
            ->orderBy('sort_order')
            ->take(4)
            ->get()
            ->values();
        if ($learnerComponents->get(0)) {
            $learnerComponents->get(0)->forceFill(['component_type' => 'text', 'title' => 'Bài học hình ảnh', 'status' => 'published', 'config' => array_merge($learnerComponents->get(0)->config ?? [], ['media_url' => $lessonMedia['image'], 'media_type' => 'image'])])->save();
        }
        if ($learnerComponents->get(1)) {
            $learnerComponents->get(1)->forceFill(['component_type' => 'video', 'title' => 'Bài học video', 'status' => 'published', 'config' => array_merge($learnerComponents->get(1)->config ?? [], ['media_url' => $lessonMedia['video'], 'media_type' => 'video', 'estimated_minutes' => 1])])->save();
        }
        if ($learnerComponents->get(2)) {
            $learnerComponents->get(2)->forceFill(['component_type' => 'quiz', 'title' => 'Bài thi media demo', 'status' => 'published', 'config' => array_merge($learnerComponents->get(2)->config ?? [], ['exam_id' => $exam->id, 'exam_title' => $exam->title, 'duration_minutes' => $exam->duration_minutes])])->save();
            $exam->forceFill(['course_id' => $class->course_id, 'component_id' => $learnerComponents->get(2)->id])->save();
        }
        if ($learnerComponents->get(3)) {
            $learnerComponents->get(3)->forceFill(['component_type' => 'text', 'title' => 'Bài học âm thanh', 'status' => 'published', 'config' => array_merge($learnerComponents->get(3)->config ?? [], ['media_url' => $lessonMedia['audio'], 'media_type' => 'audio'])])->save();
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

        $questions = $this->seedMediaQuestions($tenant->id, $bank?->id, $exam);
        foreach ($questions as $index => $question) {
            ExamQuestion::query()->updateOrCreate(
                ['exam_id' => $exam->id, 'question_id' => $question->id],
                ['tenant_id' => $tenant->id, 'score' => 1, 'sort_order' => $index + 1]
            );
        }

        foreach ([$learner, $admin] as $examUser) {
            if (! $examUser) {
                continue;
            }
            ExamEnrollment::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'exam_id' => $exam->id, 'user_id' => $examUser->id],
                [
                    'course_id' => $course->id,
                    'class_id' => $examUser->id === $learner->id ? $class->id : null,
                    'status' => 'assigned',
                    'assigned_by' => $teacher?->id ?? 1,
                    'available_from' => now()->subWeek(),
                    'available_until' => now()->addMonths(3),
                    'metadata' => ['seed' => true, 'test_ready' => true],
                ]
            );
        }

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

        $this->seedLearnerIdp($tenant->id, $learner->id);
        $this->seedLearnerGradebook($tenant->id, $learner->id);
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

    private function seedMediaQuestions(int $tenantId, ?int $bankId, Exam $exam)
    {
        $media = [
            'image' => '/demo-media/demo-image.svg',
            'audio' => '/demo-media/demo-audio.wav',
            'video' => 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4',
        ];

        $items = [
            ['code' => 'MEDIA-Q01', 'title' => 'Nhận diện hình ảnh', 'stem' => 'Quan sát hình ảnh. Đối tượng chính trong ảnh là gì?', 'media_url' => $media['image'], 'media_type' => 'image', 'answer' => 'A', 'options' => ['A' => 'Núi và hồ', 'B' => 'Phòng máy tính', 'C' => 'Bảng điểm', 'D' => 'Micro thu âm']],
            ['code' => 'MEDIA-Q02', 'title' => 'Nghe âm thanh', 'stem' => 'Nghe đoạn âm thanh và chọn mô tả đúng nhất.', 'media_url' => $media['audio'], 'media_type' => 'audio', 'answer' => 'B', 'options' => ['A' => 'Tiếng chuông', 'B' => 'Âm thanh động vật', 'C' => 'Nhạc nền lớp học', 'D' => 'Tiếng gõ bàn phím']],
            ['code' => 'MEDIA-Q03', 'title' => 'Xem video', 'stem' => 'Xem video ngắn. Nội dung chính của video là gì?', 'media_url' => $media['video'], 'media_type' => 'video', 'answer' => 'C', 'options' => ['A' => 'Một bài giảng bảng trắng', 'B' => 'Một buổi họp trực tuyến', 'C' => 'Hoa chuyển động trong khung hình', 'D' => 'Màn hình làm bài thi']],
            ['code' => 'MEDIA-Q04', 'title' => 'Quy định thời gian thi', 'stem' => 'Trong màn hình thi, chức năng nào giúp người học biết còn bao lâu để nộp bài?', 'answer' => 'A', 'options' => ['A' => 'Đồng hồ đếm ngược', 'B' => 'Nút xem kết quả', 'C' => 'Menu khóa học', 'D' => 'Thanh tìm kiếm']],
            ['code' => 'MEDIA-Q05', 'title' => 'Tự động lưu đáp án', 'stem' => 'Khi chọn đáp án trong bài thi, hệ thống cần thực hiện thao tác nào?', 'answer' => 'B', 'options' => ['A' => 'Xóa câu hỏi', 'B' => 'Autosave đáp án', 'C' => 'Đóng trình duyệt', 'D' => 'Chuyển sang Studio']],
            ['code' => 'MEDIA-Q06', 'title' => 'Đánh dấu xem lại', 'stem' => 'Nút đánh dấu xem lại dùng để làm gì?', 'answer' => 'C', 'options' => ['A' => 'Nộp bài ngay', 'B' => 'Tạo câu hỏi mới', 'C' => 'Ghi nhớ câu cần kiểm tra lại', 'D' => 'Tải video lên kho']],
            ['code' => 'MEDIA-Q07', 'title' => 'Câu hỏi có hình ở đáp án', 'stem' => 'Đáp án nào có minh họa hình ảnh?', 'answer' => 'D', 'options' => ['A' => 'Đáp án văn bản thường', 'B' => 'Đáp án không có media', 'C' => 'Đáp án chỉ có chữ', 'D' => 'Đáp án có hình ảnh minh họa'], 'option_media' => ['D' => $media['image']]],
            ['code' => 'MEDIA-Q08', 'title' => 'Câu hỏi có audio ở đáp án', 'stem' => 'Đáp án nào phát được âm thanh?', 'answer' => 'A', 'options' => ['A' => 'Đáp án có audio', 'B' => 'Đáp án văn bản', 'C' => 'Đáp án hình ảnh', 'D' => 'Đáp án video'], 'option_media' => ['A' => $media['audio']]],
            ['code' => 'MEDIA-Q09', 'title' => 'Câu hỏi có video ở đáp án', 'stem' => 'Đáp án nào có video chạy được?', 'answer' => 'C', 'options' => ['A' => 'File PDF', 'B' => 'Âm thanh', 'C' => 'Video minh họa', 'D' => 'Không có media'], 'option_media' => ['C' => $media['video']]],
            ['code' => 'MEDIA-Q10', 'title' => 'Hoàn tất bài thi', 'stem' => 'Sau khi trả lời xong, người học cần bấm nút nào để gửi bài?', 'answer' => 'D', 'options' => ['A' => 'Câu trước', 'B' => 'Đánh dấu xem lại', 'C' => 'Sửa đề', 'D' => 'Nộp bài']],
        ];

        $questions = collect($items)->map(function (array $item, int $index) use ($tenantId, $bankId, $exam) {
            $question = Question::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'question_bank_id' => $bankId, 'code' => $item['code']],
                [
                    'title' => $item['title'],
                    'stem' => $item['stem'],
                    'question_type' => 'single_choice',
                    'difficulty' => 'easy',
                    'bloom_level' => 'understand',
                    'default_score' => 1,
                    'status' => 'published',
                    'owner_id' => 1,
                    'metadata' => array_filter(['media_url' => $item['media_url'] ?? null, 'media_type' => $item['media_type'] ?? null, 'demo_media' => true]),
                ]
            );
            $question->options()->delete();
            foreach ($item['options'] as $key => $content) {
                $question->options()->create([
                    'tenant_id' => $tenantId,
                    'option_key' => $key,
                    'content' => $content,
                    'is_correct' => $key === $item['answer'],
                    'score_weight' => $key === $item['answer'] ? 1 : 0,
                    'sort_order' => ord($key) - 64,
                    'media_url' => $item['option_media'][$key] ?? null,
                    'metadata' => ['demo_media' => true],
                ]);
            }
            ExamQuestion::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'exam_id' => $exam->id, 'question_id' => $question->id],
                ['score' => 1, 'sort_order' => $index + 1, 'required' => true]
            );

            return $question->fresh(['options', 'matchingPairs', 'fillBlankAnswers']);
        });

        ExamQuestion::query()
            ->where('exam_id', $exam->id)
            ->whereNotIn('question_id', $questions->pluck('id'))
            ->delete();

        return $questions;
    }

    private function seedLearnerIdp(int $tenantId, int $userId): void
    {
        $service = app(CareerPortfolioService::class);
        $service->defaultSkillDefinitions($tenantId);
        $service->ensureProfile($tenantId, $userId, [
            'headline' => 'Digital Twin IDP - Thí sinh Test Learner',
            'summary' => 'Hồ sơ dùng để phân tích sâu dữ liệu học, kỹ năng, portfolio và định hướng 30/60/90 ngày.',
            'visibility' => 'private',
            'lifecycle_status' => 'student',
            'resume_data' => ['target_role' => 'Thực tập sinh kỹ thuật số', 'idp_ready' => true],
        ]);

        $scores = [
            'Kỹ năng nghề' => 72,
            'Kỹ năng mềm' => 66,
            'AI Skills' => 58,
            'Digital Skills' => 81,
            'Ngoại ngữ' => 62,
        ];
        SkillDefinition::query()
            ->where('tenant_id', $tenantId)
            ->get()
            ->groupBy('category')
            ->each(function ($definitions, $category) use ($tenantId, $userId, $scores) {
                foreach ($definitions->take(2) as $definition) {
                    LearnerSkill::query()->updateOrCreate(
                        ['tenant_id' => $tenantId, 'user_id' => $userId, 'skill_definition_id' => $definition->id],
                        [
                            'score' => ($scores[$category] ?? 70) + (($definition->id % 3) * 2),
                            'source' => 'digital_twin_seed',
                            'evidence' => ['source' => 'learner_demo', 'idp' => true],
                            'assessed_at' => now()->subDays($definition->id % 10),
                        ]
                    );
                }
            });

        $portfolio = DigitalPortfolio::query()->where('tenant_id', $tenantId)->where('user_id', $userId)->first();
        if ($portfolio) {
            foreach ([
                ['assignment', 'Bài nộp phân tích quy trình học an toàn'],
                ['project', 'Dự án nhóm Digital Twin IDP'],
                ['certificate', 'Chứng chỉ hoàn thành khóa demo media'],
                ['internship', 'Minh chứng sẵn sàng thực tập'],
            ] as $index => [$type, $title]) {
                PortfolioItem::query()->updateOrCreate(
                    ['tenant_id' => $tenantId, 'portfolio_id' => $portfolio->id, 'title' => $title],
                    [
                        'user_id' => $userId,
                        'item_type' => $type,
                        'description' => 'Minh chứng mẫu phục vụ Career Portfolio và Digital Twin IDP.',
                        'issuer' => $type === 'certificate' ? 'EraLMS VABIS' : null,
                        'evidence_url' => '/career/digital-twin',
                        'issued_at' => now()->subDays(20 - ($index * 3)),
                        'verification_code' => $type === 'certificate' ? 'CERT-LEARNER-TEST' : null,
                        'visibility' => 'public',
                        'status' => 'published',
                        'metadata' => ['seed' => true, 'learner_demo' => true],
                    ]
                );
            }
            $service->recalculatePortfolioScore($portfolio);
        }
    }

    private function seedLearnerGradebook(int $tenantId, int $userId): void
    {
        $gradebooks = Gradebook::query()
            ->where('tenant_id', $tenantId)
            ->with('items')
            ->latest('updated_at')
            ->take(5)
            ->get();

        foreach ($gradebooks as $bookIndex => $gradebook) {
            if ($gradebook->items->isEmpty()) {
                continue;
            }

            $scores = [8.4 - ($bookIndex * .1), 7.8, 9.5, 7.2 + ($bookIndex * .1)];
            foreach ($gradebook->items as $index => $item) {
                $score = $scores[$index] ?? 8.0;
                LearnerGrade::query()->updateOrCreate(
                    ['tenant_id' => $tenantId, 'grade_item_id' => $item->id, 'user_id' => $userId],
                    [
                        'gradebook_id' => $gradebook->id,
                        'raw_score' => $score,
                        'final_score' => $score,
                        'pass_status' => $score >= 5 ? 'passed' : 'failed',
                        'source_status' => in_array($gradebook->status, ['locked', 'approved'], true) ? 'locked' : 'final',
                        'feedback' => $score < 7.5 ? 'Cần học lại phần còn yếu và xem lại kết quả thi.' : 'Hoàn thành tốt.',
                        'updated_by' => 1,
                    ]
                );
            }

            app(GradeFormulaService::class)->calculateLearner($gradebook, $gradebook->items, $userId);
            GradeSummary::query()
                ->where('tenant_id', $tenantId)
                ->where('gradebook_id', $gradebook->id)
                ->where('user_id', $userId)
                ->update(['status' => $gradebook->status === 'locked' ? 'locked' : 'approved']);
        }
    }
}
