<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamEnrollment;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\LmsUser;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Tenant;
use App\Models\VideoAsset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoLearningContentSeeder extends Seeder
{
    private const DEMO_VIDEO_URL = 'https://interactive-examples.mdn.mozilla.net/media/cc0-videos/flower.mp4';
    private const DEMO_IMAGE_URL = '/demo-media/demo-image.svg';

    public function run(): void
    {
        $tenant = Tenant::query()->where('code', 'VABIS')->firstOrFail();
        $ownerId = LmsUser::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('email', ['gv.lms@vabis.edu.vn', 'admin.lms@vabis.edu.vn'])
            ->orderByRaw("case when email = 'gv.lms@vabis.edu.vn' then 0 else 1 end")
            ->value('id') ?: 1;

        $demoLearnerIds = LmsUser::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('email', ['sv.lms@vabis.edu.vn', 'learner.test@vabis.edu.vn'])
            ->pluck('id');

        $courseIds = collect();

        if ($demoLearnerIds->isNotEmpty()) {
            $demoEnrollments = Enrollment::query()
                ->where('tenant_id', $tenant->id)
                ->whereIn('user_id', $demoLearnerIds)
                ->whereNotNull('course_id')
                ->get(['course_id', 'metadata']);

            $courseIds = $courseIds->merge(
                $demoEnrollments
                    ->filter(fn (Enrollment $enrollment) => (bool) data_get($enrollment->metadata, 'demo') || (bool) data_get($enrollment->metadata, 'dashboard'))
                    ->pluck('course_id')
            );
        }

        $courseIds = $courseIds->merge(
            Course::query()
                ->where('tenant_id', $tenant->id)
                ->where(function ($query) {
                    $query->whereIn('code', ['COURSE-HSK1', 'COURSE001'])
                        ->orWhere('code', 'like', 'DEMO-CNC-%');
                })
                ->pluck('id')
        );

        if ($courseIds->isEmpty()) {
            $courseIds = Course::query()
                ->where('tenant_id', $tenant->id)
                ->where('code', 'like', 'DEMO-%')
                ->pluck('id');
        }

        $courses = Course::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $courseIds->filter()->unique()->values())
            ->orderBy('id')
            ->get();

        $seeded = 0;
        $quizReady = 0;
        foreach ($courses as $course) {
            if ($this->ensureCourseContent((int) $tenant->id, (int) $ownerId, $course)) {
                $seeded++;
            }
            $quizReady += $this->ensureQuizAccess((int) $tenant->id, (int) $ownerId, $course);
        }

        $this->command?->info("Demo learning content ready for {$seeded} empty course(s).");
        $this->command?->info("Demo quiz access ready for {$quizReady} quiz component(s).");
    }

    private function ensureCourseContent(int $tenantId, int $ownerId, Course $course): bool
    {
        if (CourseComponent::query()->where('tenant_id', $tenantId)->where('course_id', $course->id)->exists()) {
            return false;
        }

        foreach ($this->coursePlan($course) as $sectionIndex => $sectionSpec) {
            $section = CourseSection::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'course_id' => $course->id,
                    'parent_id' => null,
                    'type' => 'section',
                    'sort_order' => (string) ($sectionIndex + 1),
                ],
                [
                    'title' => $sectionSpec['title'],
                    'description' => $sectionSpec['description'],
                    'status' => 'published',
                    'release_at' => now()->subDays(12 - ($sectionIndex * 3)),
                    'due_at' => now()->addDays(14 + ($sectionIndex * 7)),
                    'settings' => ['demo_seed' => true],
                ]
            );

            foreach ($sectionSpec['units'] as $unitIndex => $unitSpec) {
                $unit = CourseSection::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'course_id' => $course->id,
                        'parent_id' => $section->id,
                        'type' => 'unit',
                        'sort_order' => (string) ($unitIndex + 1),
                    ],
                    [
                        'title' => $unitSpec['title'],
                        'description' => $unitSpec['objective'],
                        'status' => 'published',
                        'release_at' => now()->subDays(10 - ($sectionIndex * 3) - $unitIndex),
                        'due_at' => now()->addDays(7 + ($sectionIndex * 5) + $unitIndex),
                        'settings' => [
                            'demo_seed' => true,
                            'objective' => $unitSpec['objective'],
                        ],
                    ]
                );

                foreach ($unitSpec['activities'] as $activityIndex => $activity) {
                    $component = CourseComponent::query()->updateOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'course_id' => $course->id,
                            'section_id' => $unit->id,
                            'sort_order' => (string) ($activityIndex + 1),
                        ],
                        [
                            'component_type' => $activity['type'],
                            'title' => $activity['title'],
                            'config' => $this->componentConfig($course, $unitSpec, $activity, $activityIndex + 1),
                            'required' => true,
                            'status' => 'published',
                        ]
                    );

                    if ($activity['type'] === 'video') {
                        $this->videoAsset($tenantId, $ownerId, $course, $component);
                    }
                }
            }
        }

        $course->forceFill([
            'status' => 'published',
            'visibility' => $course->visibility ?: 'internal',
            'published_at' => $course->published_at ?: now(),
            'approved_by' => $course->approved_by ?: $ownerId,
            'approved_at' => $course->approved_at ?: now(),
            'settings' => array_merge($course->settings ?? [], ['demo_content_ready' => true]),
        ])->save();

        return true;
    }

    private function coursePlan(Course $course): array
    {
        $key = Str::lower($course->code.' '.$course->title);

        if (str_contains($key, 'hsk') || str_contains($key, 'tiếng trung')) {
            return [
                [
                    'title' => 'Nền tảng HSK',
                    'description' => 'Làm quen phát âm, từ vựng và mẫu câu HSK cơ bản.',
                    'units' => [
                        [
                            'title' => 'Chào hỏi và giới thiệu bản thân',
                            'objective' => 'Nghe hiểu và sử dụng mẫu câu chào hỏi, hỏi tên, giới thiệu lớp học.',
                            'activities' => [
                                ['type' => 'text', 'title' => 'Từ vựng trọng tâm', 'body' => '<p>Học các mẫu câu: <strong>你好</strong>, <strong>我叫...</strong>, <strong>你是哪国人?</strong>. Ghi âm lại phần đọc của mình và so sánh với video mẫu.</p><ul><li>Mục tiêu: nhận diện 12 từ mới.</li><li>Thực hành: tự giới thiệu trong 30 giây.</li></ul>', 'minutes' => 8],
                                ['type' => 'video', 'title' => 'Video luyện nghe chào hỏi', 'minutes' => 12],
                                ['type' => 'quiz', 'title' => 'Quiz nhận diện mẫu câu', 'questions' => 8, 'minutes' => 10],
                            ],
                        ],
                        [
                            'title' => 'Số đếm, ngày giờ và lớp học',
                            'objective' => 'Đọc số, hỏi giờ học và mô tả lịch học hằng ngày.',
                            'activities' => [
                                ['type' => 'text', 'title' => 'Bảng mẫu câu ngày giờ', 'body' => '<p>Ôn số đếm 1-20, thứ trong tuần và cấu trúc hỏi giờ: <strong>现在几点?</strong>. Hoàn thành bảng chuyển đổi số sang tiếng Hoa.</p>', 'minutes' => 9],
                                ['type' => 'assignment', 'title' => 'Bài tập viết lịch học cá nhân', 'minutes' => 20],
                                ['type' => 'forum', 'title' => 'Thảo luận: giới thiệu thời khóa biểu', 'minutes' => 12],
                            ],
                        ],
                    ],
                ],
                [
                    'title' => 'Luyện tập giao tiếp',
                    'description' => 'Củng cố nghe, nói, đọc, viết qua tình huống lớp học.',
                    'units' => [
                        [
                            'title' => 'Mua đồ và hỏi giá',
                            'objective' => 'Sử dụng mẫu câu hỏi giá, số lượng và màu sắc trong hội thoại ngắn.',
                            'activities' => [
                                ['type' => 'video', 'title' => 'Video hội thoại mua đồ', 'minutes' => 14],
                                ['type' => 'text', 'title' => 'Mẫu câu thực hành theo cặp', 'body' => '<p>Đóng vai người mua và người bán. Mỗi cặp tạo tối thiểu 4 lượt hội thoại có giá tiền, màu sắc và số lượng.</p>', 'minutes' => 10],
                                ['type' => 'quiz', 'title' => 'Quiz nghe hiểu tình huống', 'questions' => 10, 'minutes' => 12],
                            ],
                        ],
                        [
                            'title' => 'Ôn tập cuối mô-đun',
                            'objective' => 'Tự đánh giá mức độ sẵn sàng trước bài kiểm tra HSK mini.',
                            'activities' => [
                                ['type' => 'text', 'title' => 'Checklist tự ôn tập', 'body' => '<p>Đánh dấu các kỹ năng đã làm được: đọc pinyin, nghe từ quen thuộc, trả lời câu hỏi cá nhân và viết câu ngắn.</p>', 'minutes' => 7],
                                ['type' => 'assignment', 'title' => 'Nộp đoạn ghi âm giới thiệu bản thân', 'minutes' => 25],
                                ['type' => 'quiz', 'title' => 'Mini test HSK', 'questions' => 15, 'minutes' => 18],
                            ],
                        ],
                    ],
                ],
            ];
        }

        return [
            [
                'title' => 'Nền tảng thực hành',
                'description' => 'Chuẩn bị kiến thức, an toàn và quy trình trước khi vào bài thực hành.',
                'units' => [
                    [
                        'title' => 'Mục tiêu và tiêu chuẩn hoàn thành',
                        'objective' => 'Nắm đầu ra bài học, tiêu chí đánh giá và yêu cầu minh chứng.',
                        'activities' => [
                            ['type' => 'text', 'title' => 'Tóm tắt bài học', 'body' => '<p>Bài học mô phỏng quy trình học thực tế: đọc tài liệu, xem video hướng dẫn, làm quiz và nộp minh chứng. Người học cần hoàn thành đủ các hoạt động bắt buộc để đạt tiến độ.</p>', 'minutes' => 8],
                            ['type' => 'video', 'title' => 'Video hướng dẫn thao tác chính', 'minutes' => 12],
                            ['type' => 'quiz', 'title' => 'Quiz kiểm tra nhanh', 'questions' => 8, 'minutes' => 10],
                        ],
                    ],
                    [
                        'title' => 'Quy trình làm việc an toàn',
                        'objective' => 'Áp dụng checklist an toàn và ghi nhận lỗi thường gặp trong quá trình học.',
                        'activities' => [
                            ['type' => 'text', 'title' => 'Checklist trước khi thực hành', 'body' => '<p>Kiểm tra thiết bị, mục tiêu đầu ra, dữ liệu đầu vào và tiêu chí nộp bài. Ghi chú lại ít nhất 3 rủi ro có thể xảy ra trong ca thực hành.</p>', 'minutes' => 10],
                            ['type' => 'assignment', 'title' => 'Bài tập phân tích tình huống', 'minutes' => 25],
                            ['type' => 'forum', 'title' => 'Thảo luận lỗi thường gặp', 'minutes' => 12],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Thực hành và đánh giá',
                'description' => 'Thực hành theo tình huống và hoàn thành các mốc đánh giá.',
                'units' => [
                    [
                        'title' => 'Thực hành có hướng dẫn',
                        'objective' => 'Hoàn thành nhiệm vụ theo từng bước và lưu lại minh chứng học tập.',
                        'activities' => [
                            ['type' => 'video', 'title' => 'Video mô phỏng bài thực hành', 'minutes' => 15],
                            ['type' => 'text', 'title' => 'Phiếu ghi nhận kết quả', 'body' => '<p>Điền kết quả theo 3 bước: chuẩn bị, thực hiện, kiểm tra. Với mỗi bước, ghi một minh chứng hoặc ảnh chụp màn hình nếu có.</p>', 'minutes' => 9],
                            ['type' => 'assignment', 'title' => 'Nộp minh chứng thực hành', 'minutes' => 30],
                        ],
                    ],
                    [
                        'title' => 'Tổng kết mô-đun',
                        'objective' => 'Tự đánh giá kết quả học và nhận diện phần cần học lại.',
                        'activities' => [
                            ['type' => 'text', 'title' => 'Rubric tự đánh giá', 'body' => '<p>Tự chấm theo 4 mức: chưa đạt, đạt tối thiểu, đạt tốt, vượt yêu cầu. Ghi rõ phần cần học lại trước buổi tiếp theo.</p>', 'minutes' => 8],
                            ['type' => 'quiz', 'title' => 'Quiz tổng kết mô-đun', 'questions' => 12, 'minutes' => 15],
                            ['type' => 'forum', 'title' => 'Phản hồi sau bài học', 'minutes' => 10],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function componentConfig(Course $course, array $unitSpec, array $activity, int $sortOrder): array
    {
        $base = [
            'demo_seed' => true,
            'estimated_minutes' => $activity['minutes'] ?? 10,
            'learning_objective' => $unitSpec['objective'],
            'course_code' => $course->code,
        ];

        return match ($activity['type']) {
            'text' => $base + [
                'html' => $activity['body'] ?? '<p>Nội dung bài học mẫu đã sẵn sàng.</p>',
                'media_url' => $sortOrder === 1 ? self::DEMO_IMAGE_URL : null,
                'media_type' => $sortOrder === 1 ? 'image' : null,
            ],
            'video' => $base + [
                'media_url' => self::DEMO_VIDEO_URL,
                'media_type' => 'video',
                'poster_url' => self::DEMO_IMAGE_URL,
                'min_watch_percent' => 80,
            ],
            'quiz' => $base + [
                'questions_count' => $activity['questions'] ?? 10,
                'duration_minutes' => $activity['minutes'] ?? 12,
                'min_score' => 70,
                'completion_rule' => ['type' => 'score', 'score' => 70],
            ],
            'assignment' => $base + [
                'submission_type' => 'mixed',
                'max_score' => 10,
                'pass_score' => 5,
                'instructions' => 'Nộp nội dung trả lời kèm minh chứng học tập nếu có.',
            ],
            'forum' => $base + [
                'prompt' => 'Chia sẻ kết quả học tập, câu hỏi còn vướng và phản hồi cho bạn học.',
            ],
            default => $base,
        };
    }

    private function videoAsset(int $tenantId, int $ownerId, Course $course, CourseComponent $component): void
    {
        VideoAsset::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'component_id' => $component->id],
            [
                'course_id' => $course->id,
                'title' => $component->title,
                'description' => 'Video demo gắn với bài học để learner mở lên có nội dung phát được.',
                'original_filename' => 'flower.mp4',
                'original_storage_path' => self::DEMO_VIDEO_URL,
                'duration_seconds' => 30,
                'file_size' => 0,
                'mime_type' => 'video/mp4',
                'processing_status' => 'ready',
                'visibility' => 'course',
                'checksum' => sha1($course->code.'-'.$component->id),
                'thumbnail_url' => self::DEMO_IMAGE_URL,
                'settings' => ['external_url' => true, 'demo_seed' => true],
                'uploaded_by' => $ownerId,
                'processed_at' => now(),
            ]
        );
    }

    private function ensureQuizAccess(int $tenantId, int $ownerId, Course $course): int
    {
        $quizComponents = CourseComponent::query()
            ->where('tenant_id', $tenantId)
            ->where('course_id', $course->id)
            ->where('component_type', 'quiz')
            ->orderBy('section_id')
            ->orderBy('sort_order')
            ->get();

        if ($quizComponents->isEmpty()) {
            return 0;
        }

        $learnerEnrollments = Enrollment::query()
            ->where('tenant_id', $tenantId)
            ->where('course_id', $course->id)
            ->whereIn('status', ['pending', 'active', 'completed'])
            ->orderByDesc('updated_at')
            ->get()
            ->unique('user_id')
            ->values();

        $ready = 0;
        foreach ($quizComponents as $component) {
            $exam = $this->ensureQuizExam($tenantId, $ownerId, $course, $component);
            $this->assignCourseLearnersToExam($tenantId, $ownerId, $exam, $learnerEnrollments);
            $ready++;
        }

        return $ready;
    }

    private function ensureQuizExam(int $tenantId, int $ownerId, Course $course, CourseComponent $component): Exam
    {
        $config = $component->config ?? [];
        $exam = ! empty($config['exam_id'])
            ? Exam::query()->where('tenant_id', $tenantId)->find($config['exam_id'])
            : null;

        $bank = $this->questionBank($tenantId, $ownerId, $course);

        if (! $exam) {
            $exam = Exam::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => 'DEMO-QUIZ-COMP-'.$component->id],
                [
                    'course_id' => $course->id,
                    'component_id' => $component->id,
                    'question_bank_id' => $bank->id,
                    'title' => $component->title,
                    'description' => 'Quiz demo gắn trực tiếp với bài học để người học bắt đầu làm bài từ trang học.',
                    'exam_type' => 'quiz',
                    'delivery_mode' => 'self_paced',
                    'status' => 'published',
                    'total_score' => 10,
                    'pass_score' => 7,
                    'duration_minutes' => (int) ($config['duration_minutes'] ?? $config['estimated_minutes'] ?? 15),
                    'max_attempts' => 99,
                    'shuffle_questions' => true,
                    'shuffle_options' => true,
                    'show_result_mode' => 'immediately',
                    'show_correct_answers' => true,
                    'open_at' => now()->subDay(),
                    'close_at' => now()->addMonths(6),
                    'settings' => ['demo_seed' => true, 'component_id' => $component->id],
                    'created_by' => $ownerId,
                    'approved_by' => $ownerId,
                    'approved_at' => now(),
                ]
            );
        } else {
            $exam->forceFill([
                'course_id' => $exam->course_id ?: $course->id,
                'component_id' => $exam->component_id ?: $component->id,
                'question_bank_id' => $exam->question_bank_id ?: $bank->id,
                'status' => 'published',
                'max_attempts' => max((int) $exam->max_attempts, 99),
                'open_at' => $exam->open_at ?: now()->subDay(),
                'close_at' => $exam->close_at ?: now()->addMonths(6),
                'approved_by' => $exam->approved_by ?: $ownerId,
                'approved_at' => $exam->approved_at ?: now(),
                'settings' => array_merge($exam->settings ?? [], ['demo_quiz_access_ready' => true]),
            ])->save();
        }

        $this->ensureExamQuestions($tenantId, $ownerId, $exam, $bank, $component);

        $component->forceFill([
            'status' => 'published',
            'config' => array_merge($config, [
                'exam_id' => $exam->id,
                'exam_title' => $exam->title,
                'duration_minutes' => $exam->duration_minutes,
                'questions_count' => max(ExamQuestion::query()->where('exam_id', $exam->id)->count(), (int) ($config['questions_count'] ?? 5)),
                'min_score' => 70,
                'completion_rule' => ['type' => 'score', 'score' => 70],
            ]),
        ])->save();

        return $exam;
    }

    private function questionBank(int $tenantId, int $ownerId, Course $course): QuestionBank
    {
        return QuestionBank::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'DEMO-QB-'.$course->code],
            [
                'course_id' => $course->id,
                'name' => 'Ngân hàng câu hỏi demo - '.$course->title,
                'description' => 'Câu hỏi demo dùng cho quiz trên trang học learner.',
                'visibility' => 'tenant',
                'status' => 'published',
                'owner_id' => $ownerId,
                'settings' => ['demo_seed' => true],
            ]
        );
    }

    private function ensureExamQuestions(int $tenantId, int $ownerId, Exam $exam, QuestionBank $bank, CourseComponent $component): void
    {
        if (ExamQuestion::query()->where('exam_id', $exam->id)->exists()) {
            return;
        }

        $section = ExamSection::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'exam_id' => $exam->id, 'sort_order' => 1],
            [
                'title' => 'Câu hỏi trọng tâm',
                'description' => 'Câu hỏi kiểm tra nhanh nội dung vừa học.',
                'question_count' => 5,
                'score' => 10,
                'config' => ['demo_seed' => true],
            ]
        );

        foreach (range(1, 5) as $index) {
            $question = Question::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => 'DEMO-Q-'.$component->id.'-'.$index],
                [
                    'question_bank_id' => $bank->id,
                    'question_type' => 'single_choice',
                    'title' => 'Câu '.$index.' - '.$component->title,
                    'stem' => 'Chọn phương án đúng nhất sau khi học hoạt động "'.$component->title.'".',
                    'explanation' => 'Đáp án đúng phản ánh nội dung trọng tâm và điều kiện hoàn thành bài học.',
                    'difficulty' => $index <= 2 ? 'easy' : 'medium',
                    'bloom_level' => $index <= 2 ? 'remember' : 'understand',
                    'default_score' => 2,
                    'penalty_score' => 0,
                    'time_limit_seconds' => 120,
                    'status' => 'approved',
                    'owner_id' => $ownerId,
                    'approved_by' => $ownerId,
                    'approved_at' => now(),
                    'metadata' => ['demo_seed' => true, 'component_id' => $component->id],
                ]
            );

            foreach ([
                'A' => 'Phương án đúng theo mục tiêu bài học',
                'B' => 'Phương án nhiễu do bỏ qua bước chuẩn bị',
                'C' => 'Phương án nhiễu do hiểu sai yêu cầu',
                'D' => 'Phương án nhiễu do thiếu minh chứng',
            ] as $key => $content) {
                QuestionOption::query()->updateOrCreate(
                    ['tenant_id' => $tenantId, 'question_id' => $question->id, 'option_key' => $key],
                    [
                        'content' => $content,
                        'is_correct' => $key === 'A',
                        'score_weight' => $key === 'A' ? 1 : 0,
                        'feedback' => $key === 'A' ? 'Đúng.' : 'Chưa đúng, cần xem lại bài học.',
                        'sort_order' => ord($key) - 64,
                        'metadata' => ['demo_seed' => true],
                    ]
                );
            }

            ExamQuestion::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'exam_id' => $exam->id, 'question_id' => $question->id],
                [
                    'section_id' => $section->id,
                    'score' => 2,
                    'sort_order' => $index,
                    'required' => true,
                    'metadata' => ['demo_seed' => true],
                ]
            );
        }
    }

    private function assignCourseLearnersToExam(int $tenantId, int $ownerId, Exam $exam, $learnerEnrollments): void
    {
        foreach ($learnerEnrollments as $enrollment) {
            ExamEnrollment::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'exam_id' => $exam->id, 'user_id' => $enrollment->user_id],
                [
                    'course_id' => $exam->course_id,
                    'class_id' => $enrollment->class_section_id,
                    'status' => 'assigned',
                    'assigned_by' => $ownerId,
                    'available_from' => now()->subDay(),
                    'available_until' => now()->addMonths(6),
                    'metadata' => ['demo_seed' => true, 'course_enrollment_id' => $enrollment->id],
                ]
            );
        }
    }
}
