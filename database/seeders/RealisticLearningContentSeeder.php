<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use App\Models\Assignment;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Certificate;
use App\Models\CertificateIssue;
use App\Models\CertificateTemplate;
use App\Models\ContentRepositoryItem;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseComponent;
use App\Models\CourseSection;
use App\Models\Exam;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\GradeApprovalBatch;
use App\Models\GradeCategory;
use App\Models\GradeItem;
use App\Models\GradeSummary;
use App\Models\Gradebook;
use App\Models\IntegrationEvent;
use App\Models\IntegrationMapping;
use App\Models\IntegrationSystem;
use App\Models\LearnerGrade;
use App\Models\LearningPathRule;
use App\Models\LmsUser;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Rubric;
use App\Models\RubricCriterion;
use App\Models\RubricLevel;
use App\Models\SyncJob;
use App\Models\Tenant;
use App\Models\VideoAsset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RealisticLearningContentSeeder extends Seeder
{
    private const COURSE_TARGET = 100;
    private const LESSONS_PER_COURSE = 10;
    private const CONTENT_TARGET = 3000;
    private const QUIZ_TARGET = 500;
    private const ASSIGNMENT_TARGET = 300;

    public function run(): void
    {
        DB::disableQueryLog();

        $tenant = Tenant::query()->where('code', 'VABIS')->firstOrFail();
        $owner = LmsUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_type', 'teacher')
            ->first();
        $students = LmsUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_type', 'student')
            ->orderBy('id')
            ->limit(120)
            ->get();

        DB::beginTransaction();

        try {
        $this->ensureActivityTypes();

        $courseSpecs = $this->courseSpecs();
        $folders = $this->repositoryFolders($tenant->id, $owner?->id ?? 1);
        $categories = $this->courseCategories($tenant->id);
        $rubric = $this->rubric($tenant->id, $owner?->id ?? 1);
        $template = $this->certificateTemplate($tenant->id);
        $sis = $this->sisSystem($tenant->id);

        $contentCreated = 0;
        $nonCoreExtraSlots = 760;
        $quizCreated = 0;
        $assignmentCreated = 0;

        foreach ($courseSpecs as $courseIndex => $spec) {
            $courseNumber = $courseIndex + 1;
            $course = $this->course($tenant->id, $owner?->id ?? 1, $courseNumber, $spec, $categories);
            $bank = $this->questionBank($tenant->id, $owner?->id ?? 1, $course, $courseNumber, $spec);
            $questions = $this->questions($tenant->id, $owner?->id ?? 1, $bank, $courseNumber, $spec);
            $certificate = $this->certificate($tenant->id, $template, $course, $courseNumber, $spec);

            $gradebook = $this->gradebook($tenant->id, $owner?->id ?? 1, $course, $courseNumber, $spec);
            $gradeItems = $this->gradeItems($tenant->id, $gradebook);

            $isCoreThptSubject = $spec['group'] === 'Ôn tập THPT' && $spec['track'] === 'Lộ trình chuẩn';
            foreach (range(1, self::LESSONS_PER_COURSE) as $lessonIndex) {
                $chapterTitle = $spec['chapters'][$lessonIndex - 1];
                $section = $this->section($tenant->id, $course, $lessonIndex, $chapterTitle, $spec);

                [$contentItems, $contentCreated, $nonCoreExtraSlots] = $this->contentItems(
                    $tenant->id,
                    $owner?->id ?? 1,
                    $course,
                    $section,
                    $spec,
                    $chapterTitle,
                    $lessonIndex,
                    $folders[$spec['group']][$spec['subject']] ?? $folders[$spec['group']]['__root'],
                    $isCoreThptSubject,
                    $contentCreated,
                    $nonCoreExtraSlots
                );

                $components = $this->components($tenant->id, $course, $section, $spec, $chapterTitle, $lessonIndex, $contentItems, $isCoreThptSubject);

                if ($quizCreated < self::QUIZ_TARGET) {
                    $quizCreated++;
                    $exam = $this->quiz($tenant->id, $owner?->id ?? 1, $course, $components['quiz'] ?? null, $bank, $questions, $quizCreated, $spec, $chapterTitle, $lessonIndex);
                    $this->pathRules($tenant->id, $owner?->id ?? 1, $course, $components, $exam, $spec);
                }

                if ($assignmentCreated < self::ASSIGNMENT_TARGET) {
                    $assignmentCreated++;
                    $this->assignment($tenant->id, $owner?->id ?? 1, $course, $components['assignment'] ?? null, $rubric, $assignmentCreated, $spec, $chapterTitle, $lessonIndex);
                }
            }

            $this->attendance($tenant->id, $owner?->id ?? 1, $course, $courseNumber, $students);
            $this->gradeSamples($tenant->id, $owner?->id ?? 1, $gradebook, $gradeItems, $students, $courseNumber);
            $this->certificateIssues($tenant->id, $certificate, $template, $course, $students, $courseNumber, $spec);
            $this->sisMappings($tenant->id, $sis, $course, $courseNumber, $spec);
        }

        $this->sisJobsAndEvents($tenant->id, $sis);
            DB::commit();
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }
    }

    private function ensureActivityTypes(): void
    {
        foreach (['text','video','pdf','file','quiz','assignment','live_session','certificate'] as $type) {
            ActivityType::query()->updateOrCreate(
                ['key' => $type],
                [
                    'name' => Str::headline($type),
                    'description' => 'Hoạt động học tập '.$type,
                    'icon' => $type,
                    'enabled' => true,
                    'config_schema' => ['type' => 'object'],
                    'grading_supported' => in_array($type, ['quiz', 'assignment'], true),
                    'completion_supported' => true,
                ]
            );
        }
    }

    private function repositoryFolders(int $tenantId, int $ownerId): array
    {
        $tree = [
            'Ôn tập THPT' => ['Toán', 'Văn', 'Anh', 'Lý', 'Hóa', 'Sinh', 'Sử', 'Địa'],
            'Văn hóa 9+' => ['Toán', 'Văn', 'Anh', 'Sử/Địa', 'Lý', 'Hóa', 'Sinh', 'Sử'],
            'Trung cấp nghề' => ['CNTT', 'Du lịch', 'Ngoại ngữ'],
            'Cao đẳng chính quy' => ['CNTT', 'Du lịch', 'Kinh doanh'],
            'Chứng chỉ quốc tế' => ['Tiếng Anh', 'Tiếng Hoa', 'Tiếng Hàn'],
        ];

        $folders = [];
        foreach ($tree as $group => $children) {
            $root = ContentRepositoryItem::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'parent_id' => null, 'item_type' => 'folder', 'title' => $group],
                [
                    'description' => 'Thư mục học liệu demo cho '.$group,
                    'owner_id' => $ownerId,
                    'visibility' => 'tenant',
                    'status' => 'published',
                    'metadata' => ['realistic_seed' => true],
                ]
            );
            $folders[$group]['__root'] = $root;
            foreach ($children as $child) {
                $folders[$group][$child] = ContentRepositoryItem::query()->updateOrCreate(
                    ['tenant_id' => $tenantId, 'parent_id' => $root->id, 'item_type' => 'folder', 'title' => $child],
                    [
                        'description' => 'Học liệu '.$child.' thuộc nhóm '.$group,
                        'owner_id' => $ownerId,
                        'visibility' => 'tenant',
                        'status' => 'published',
                        'metadata' => ['realistic_seed' => true],
                    ]
                );
            }
        }

        return $folders;
    }

    private function courseCategories(int $tenantId): array
    {
        $categoryNames = ['Ôn tập THPT', 'Văn hóa 9+', 'Trung cấp nghề', 'Cao đẳng chính quy', 'Chứng chỉ quốc tế'];
        $categories = [];
        foreach ($categoryNames as $index => $name) {
            $categories[$name] = CourseCategory::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => 'RLC-G'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)],
                ['name' => $name, 'description' => 'Nhóm dữ liệu học liệu thực tế: '.$name, 'sort_order' => $index + 1, 'status' => 'active']
            );
        }

        return $categories;
    }

    private function course(int $tenantId, int $ownerId, int $number, array $spec, array $categories): Course
    {
        return Course::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'RLC-C'.str_pad((string) $number, 3, '0', STR_PAD_LEFT)],
            [
                'title' => $spec['title'],
                'slug' => Str::slug($spec['title']).'-rlc-'.$number,
                'short_description' => $spec['summary'],
                'description' => $spec['description'],
                'category_id' => $categories[$spec['group']]?->id,
                'level' => $spec['level'],
                'course_type' => $spec['course_type'],
                'status' => 'published',
                'visibility' => 'internal',
                'language' => 'vi',
                'estimated_hours' => $spec['hours'],
                'owner_id' => $ownerId,
                'approved_by' => $ownerId,
                'approved_at' => now()->subDays(30),
                'published_at' => now()->subDays(20),
                'settings' => [
                    'realistic_seed' => true,
                    'group' => $spec['group'],
                    'subject' => $spec['subject'],
                    'track' => $spec['track'],
                    'learning_mode' => $spec['group'] === 'Văn hóa 9+' ? 'sequential' : 'mastery',
                    'sis_code' => 'SIS-RLC-C'.str_pad((string) $number, 3, '0', STR_PAD_LEFT),
                ],
            ]
        );
    }

    private function section(int $tenantId, Course $course, int $lessonIndex, string $chapterTitle, array $spec): CourseSection
    {
        return CourseSection::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'course_id' => $course->id, 'parent_id' => null, 'type' => 'unit', 'sort_order' => $lessonIndex],
            [
                'title' => $chapterTitle,
                'description' => 'Bài học '.$lessonIndex.' giúp học viên nắm '.$chapterTitle.' trong '.$spec['title'].'.',
                'status' => 'published',
                'release_at' => now()->subDays(20 - min(19, $lessonIndex)),
                'due_at' => now()->addDays(15 + $lessonIndex),
                'settings' => [
                    'lesson_no' => $lessonIndex,
                    'realistic_seed' => true,
                    'outcomes' => $this->outcomes($spec, $chapterTitle),
                ],
            ]
        );
    }

    private function contentItems(
        int $tenantId,
        int $ownerId,
        Course $course,
        CourseSection $section,
        array $spec,
        string $chapterTitle,
        int $lessonIndex,
        ContentRepositoryItem $folder,
        bool $isCoreThptSubject,
        int $contentCreated,
        int $nonCoreExtraSlots
    ): array {
        $types = $isCoreThptSubject
            ? [
                ['video', 'Video 1 - Kiến thức trọng tâm', 'video/mp4'],
                ['video', 'Video 2 - Ví dụ mẫu', 'video/mp4'],
                ['video', 'Video 3 - Chữa lỗi thường gặp', 'video/mp4'],
                ['pdf', 'PDF tóm tắt công thức và dàn ý', 'application/pdf'],
                ['file', 'Đề ôn tập có đáp án gợi ý', 'application/pdf'],
            ]
            : [
                ['video', 'Video bài giảng chính', 'video/mp4'],
                ['pdf', 'Tài liệu đọc trước và ghi chú', 'application/pdf'],
            ];

        if (! $isCoreThptSubject && $nonCoreExtraSlots > 0) {
            $types[] = ['file', 'Phiếu luyện tập tình huống', 'application/pdf'];
            $nonCoreExtraSlots--;
        }

        $items = [];
        foreach ($types as $offset => [$type, $label, $mime]) {
            if ($contentCreated >= self::CONTENT_TARGET) {
                break;
            }
            $contentCreated++;
            $title = $chapterTitle.' - '.$label;
            $path = 'realistic-learning/'.$course->code.'/lesson-'.$lessonIndex.'/material-'.($offset + 1).'-'.Str::slug($label).($type === 'video' ? '.mp4' : '.pdf');
            $item = ContentRepositoryItem::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'storage_path' => $path],
                [
                    'parent_id' => $folder->id,
                    'item_type' => $type,
                    'title' => $title,
                    'description' => $this->contentDescription($spec, $chapterTitle, $label),
                    'mime_type' => $mime,
                    'file_size' => $type === 'video' ? 160000000 + ($lessonIndex * 3500000) : 1800000 + ($lessonIndex * 90000),
                    'checksum' => sha1($path),
                    'owner_id' => $ownerId,
                    'visibility' => 'tenant',
                    'status' => 'published',
                    'metadata' => [
                        'realistic_seed' => true,
                        'course_code' => $course->code,
                        'lesson' => $lessonIndex,
                        'group' => $spec['group'],
                        'chapter' => $chapterTitle,
                    ],
                ]
            );
            $items[] = $item;
        }

        return [$items, $contentCreated, $nonCoreExtraSlots];
    }

    private function components(int $tenantId, Course $course, CourseSection $section, array $spec, string $chapterTitle, int $lessonIndex, array $contentItems, bool $isCoreThptSubject): array
    {
        $components = [];
        $sort = 1;
        foreach ($contentItems as $item) {
            $componentType = $item->item_type === 'file' ? 'pdf' : $item->item_type;
            if ($item->item_type === 'video') {
                $componentKey = isset($components['video']) ? 'video_'.$sort : 'video';
            } else {
                $componentKey = $item->item_type === 'pdf' ? 'pdf' : 'practice_file';
            }

            $component = CourseComponent::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'course_id' => $course->id, 'section_id' => $section->id, 'sort_order' => $sort],
                [
                    'component_type' => $componentType,
                    'title' => $item->title,
                    'content_id' => $item->id,
                    'config' => [
                        'realistic_seed' => true,
                        'min_watch_percent' => $componentType === 'video' ? 85 : null,
                        'estimated_minutes' => $componentType === 'video' ? 18 : 12,
                    ],
                    'required' => true,
                    'status' => 'published',
                ]
            );
            $components[$componentKey] = $component;
            if ($componentType === 'video') {
                $this->videoAsset($tenantId, $course, $component, $item, $spec, $chapterTitle, $lessonIndex, $sort);
            }
            $sort++;
        }

        $quiz = CourseComponent::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'course_id' => $course->id, 'section_id' => $section->id, 'sort_order' => 20],
            [
                'component_type' => 'quiz',
                'title' => ($spec['group'] === 'Chứng chỉ quốc tế' && $lessonIndex === 1 ? 'Placement Test - ' : 'Quiz - ').$chapterTitle,
                'config' => ['min_score' => 70, 'attempts' => 2, 'realistic_seed' => true],
                'required' => true,
                'status' => 'published',
            ]
        );
        $components['quiz'] = $quiz;

        $assignmentTitle = $isCoreThptSubject ? 'Đề ôn tập - '.$chapterTitle : 'Assignment - '.$chapterTitle;
        $assignment = CourseComponent::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'course_id' => $course->id, 'section_id' => $section->id, 'sort_order' => 30],
            [
                'component_type' => 'assignment',
                'title' => $assignmentTitle,
                'config' => [
                    'pass_score' => 5,
                    'max_score' => 10,
                    'realistic_seed' => true,
                    'submission_type' => $spec['group'] === 'Trung cấp nghề' ? 'file' : 'mixed',
                ],
                'required' => true,
                'status' => 'published',
            ]
        );
        $components['assignment'] = $assignment;

        return $components;
    }

    private function videoAsset(int $tenantId, Course $course, CourseComponent $component, ContentRepositoryItem $item, array $spec, string $chapterTitle, int $lessonIndex, int $sort): void
    {
        VideoAsset::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'repository_item_id' => $item->id],
            [
                'course_id' => $course->id,
                'component_id' => $component->id,
                'title' => $item->title,
                'description' => 'Video học liệu '.$chapterTitle.' phục vụ '.$spec['title'].'.',
                'original_filename' => basename((string) $item->storage_path),
                'original_storage_path' => $item->storage_path,
                'hls_master_path' => str_replace('.mp4', '/master.m3u8', $item->storage_path),
                'duration_seconds' => 720 + ($lessonIndex * 35) + ($sort * 20),
                'file_size' => $item->file_size ?? 0,
                'mime_type' => 'video/mp4',
                'processing_status' => 'ready',
                'visibility' => 'tenant',
                'checksum' => $item->checksum,
                'thumbnail_url' => '/demo-media/demo-image.svg',
                'subtitle_path' => str_replace('.mp4', '.vi.vtt', $item->storage_path),
                'transcript_path' => str_replace('.mp4', '.txt', $item->storage_path),
                'settings' => [
                    'anti_fake' => true,
                    'watermark' => true,
                    'chapter' => $chapterTitle,
                    'learning_objective' => $this->outcomes($spec, $chapterTitle)[0],
                ],
                'uploaded_by' => $component->course->owner_id ?? 1,
                'processed_at' => now()->subDays(10),
            ]
        );
    }

    private function questionBank(int $tenantId, int $ownerId, Course $course, int $courseNumber, array $spec): QuestionBank
    {
        return QuestionBank::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'RLC-QB'.str_pad((string) $courseNumber, 3, '0', STR_PAD_LEFT)],
            [
                'course_id' => $course->id,
                'name' => 'Ngân hàng câu hỏi - '.$course->title,
                'description' => 'Câu hỏi đánh giá theo chủ đề '.$spec['subject'].' trong nhóm '.$spec['group'].'.',
                'visibility' => 'tenant',
                'status' => 'published',
                'owner_id' => $ownerId,
                'settings' => ['realistic_seed' => true, 'blueprint_ready' => true],
            ]
        );
    }

    private function questions(int $tenantId, int $ownerId, QuestionBank $bank, int $courseNumber, array $spec): array
    {
        $questions = [];
        foreach (range(1, 5) as $index) {
            $code = 'RLC-Q'.str_pad((string) $courseNumber, 3, '0', STR_PAD_LEFT).'-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            $question = Question::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $code],
                [
                    'question_bank_id' => $bank->id,
                    'question_type' => $index === 5 ? 'essay' : 'single_choice',
                    'title' => 'Câu '.$index.' - '.$spec['subject'],
                    'stem' => $this->questionStem($spec, $index),
                    'explanation' => 'Đáp án đúng dựa trên mục tiêu bài học và ví dụ đã trình bày trong học liệu.',
                    'difficulty' => ['easy', 'medium', 'medium', 'hard', 'medium'][$index - 1],
                    'bloom_level' => ['remember', 'understand', 'apply', 'analyze', 'evaluate'][$index - 1],
                    'default_score' => $index === 5 ? 2 : 1,
                    'penalty_score' => 0,
                    'time_limit_seconds' => 90 + ($index * 30),
                    'status' => 'approved',
                    'owner_id' => $ownerId,
                    'approved_by' => $ownerId,
                    'approved_at' => now()->subDays(12),
                    'metadata' => ['realistic_seed' => true, 'subject' => $spec['subject']],
                ]
            );

            if ($question->question_type === 'single_choice') {
                foreach ($this->optionsFor($spec, $index) as $sort => [$key, $content, $correct]) {
                    QuestionOption::query()->updateOrCreate(
                        ['tenant_id' => $tenantId, 'question_id' => $question->id, 'option_key' => $key],
                        [
                            'content' => $content,
                            'is_correct' => $correct,
                            'score_weight' => $correct ? 1 : 0,
                            'feedback' => $correct ? 'Chính xác, lựa chọn này bám sát mục tiêu bài học.' : 'Chưa đúng, cần xem lại phần ví dụ và ghi chú.',
                            'sort_order' => $sort + 1,
                            'metadata' => ['realistic_seed' => true],
                        ]
                    );
                }
            }
            $questions[] = $question;
        }

        return $questions;
    }

    private function quiz(int $tenantId, int $ownerId, Course $course, ?CourseComponent $component, QuestionBank $bank, array $questions, int $quizNumber, array $spec, string $chapterTitle, int $lessonIndex): Exam
    {
        $examType = 'quiz';
        if ($spec['group'] === 'Chứng chỉ quốc tế' && $lessonIndex === 1) {
            $examType = 'placement_test';
        } elseif ($spec['group'] === 'Chứng chỉ quốc tế' && in_array($lessonIndex, [7, 9], true)) {
            $examType = 'mock_exam';
        }

        $exam = Exam::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'RLC-QUIZ-'.str_pad((string) $quizNumber, 4, '0', STR_PAD_LEFT)],
            [
                'course_id' => $course->id,
                'component_id' => $component?->id,
                'question_bank_id' => $bank->id,
                'title' => ($examType === 'quiz' ? 'Quiz ' : Str::headline($examType).' ').$chapterTitle,
                'description' => 'Đánh giá nhanh năng lực sau bài '.$chapterTitle.' của khóa '.$course->title.'.',
                'exam_type' => $examType,
                'delivery_mode' => $examType === 'mock_exam' ? 'remote_proctored' : 'self_paced',
                'status' => 'published',
                'total_score' => 6,
                'pass_score' => 4,
                'duration_minutes' => $examType === 'mock_exam' ? 60 : 20,
                'max_attempts' => $examType === 'placement_test' ? 1 : 2,
                'shuffle_questions' => true,
                'shuffle_options' => true,
                'show_result_mode' => 'immediately',
                'show_correct_answers' => false,
                'open_at' => now()->subDays(10),
                'close_at' => now()->addDays(60),
                'settings' => [
                    'realistic_seed' => true,
                    'group' => $spec['group'],
                    'learning_path_gate' => true,
                    'sis_sync_ready' => true,
                ],
                'created_by' => $ownerId,
                'approved_by' => $ownerId,
                'approved_at' => now()->subDays(9),
            ]
        );

        $section = ExamSection::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'exam_id' => $exam->id, 'sort_order' => 1],
            [
                'title' => 'Phần kiểm tra '.$spec['subject'],
                'description' => 'Câu hỏi trắc nghiệm và tự luận ngắn theo bài học.',
                'question_count' => count($questions),
                'score' => 6,
                'config' => ['realistic_seed' => true],
            ]
        );

        foreach ($questions as $index => $question) {
            ExamQuestion::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'exam_id' => $exam->id, 'question_id' => $question->id],
                [
                    'section_id' => $section->id,
                    'score' => $question->question_type === 'essay' ? 2 : 1,
                    'sort_order' => $index + 1,
                    'required' => true,
                    'metadata' => ['realistic_seed' => true],
                ]
            );
        }

        return $exam;
    }

    private function assignment(int $tenantId, int $ownerId, Course $course, ?CourseComponent $component, Rubric $rubric, int $assignmentNumber, array $spec, string $chapterTitle, int $lessonIndex): void
    {
        Assignment::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'course_id' => $course->id, 'title' => 'RLC Assignment '.str_pad((string) $assignmentNumber, 3, '0', STR_PAD_LEFT).' - '.$chapterTitle],
            [
                'component_id' => $component?->id,
                'description' => $this->assignmentPrompt($spec, $chapterTitle),
                'assignment_type' => $spec['group'] === 'Cao đẳng chính quy' ? 'project' : 'individual',
                'submission_type' => in_array($spec['group'], ['Trung cấp nghề', 'Cao đẳng chính quy'], true) ? 'mixed' : 'file',
                'status' => 'published',
                'open_at' => now()->subDays(8),
                'due_at' => now()->addDays(7 + ($lessonIndex % 5)),
                'allow_late' => true,
                'late_penalty_config' => ['type' => 'percent_per_day', 'value' => 5, 'max_percent' => 30],
                'max_score' => 10,
                'pass_score' => 5,
                'max_submissions' => 3,
                'rubric_id' => $rubric->id,
                'settings' => [
                    'realistic_seed' => true,
                    'learning_path_gate' => true,
                    'sis_sync_ready' => true,
                    'rubric_dimensions' => ['đúng yêu cầu', 'lập luận/thực hành', 'minh chứng'],
                ],
                'created_by' => $ownerId,
            ]
        );
    }

    private function pathRules(int $tenantId, int $ownerId, Course $course, array $components, Exam $exam, array $spec): void
    {
        if (! isset($components['video'], $components['quiz'], $components['assignment'])) {
            return;
        }

        LearningPathRule::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'course_id' => $course->id, 'target_type' => 'course_component', 'target_id' => $components['quiz']->id],
            [
                'rule_type' => 'sequential',
                'title' => 'Mở quiz sau video',
                'description' => 'Học viên xem video chính trước khi làm quiz.',
                'is_active' => true,
                'config' => [
                    'requires' => [['type' => 'video_watch_min', 'component_id' => $components['video']->id, 'min_watch_percent' => 85]],
                    'unlock_behavior' => 'all_required',
                    'message_locked' => 'Bạn cần xem video bài giảng trước khi làm quiz.',
                ],
                'created_by' => $ownerId,
            ]
        );

        LearningPathRule::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'course_id' => $course->id, 'target_type' => 'course_component', 'target_id' => $components['assignment']->id],
            [
                'rule_type' => $spec['group'] === 'Văn hóa 9+' ? 'sequential' : 'mastery',
                'title' => 'Mở assignment sau quiz',
                'description' => 'Học viên đạt quiz trước khi nộp bài tập.',
                'is_active' => true,
                'config' => [
                    'requires' => [['type' => 'quiz_score_min', 'component_id' => $components['quiz']->id, 'exam_id' => $exam->id, 'min_score' => 70]],
                    'unlock_behavior' => 'all_required',
                    'message_locked' => 'Bạn cần đạt tối thiểu 70% ở quiz để mở bài tập.',
                ],
                'created_by' => $ownerId,
            ]
        );
    }

    private function rubric(int $tenantId, int $ownerId): Rubric
    {
        $rubric = Rubric::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'title' => 'RLC Rubric học liệu thực tế'],
            [
                'description' => 'Rubric dùng cho đề ôn tập, bài thực hành nghề, project cao đẳng và bài viết chứng chỉ.',
                'max_score' => 10,
                'status' => 'published',
                'created_by' => $ownerId,
            ]
        );

        foreach ([['Đúng yêu cầu', 4], ['Phân tích hoặc thao tác thực hành', 4], ['Trình bày minh chứng', 2]] as $index => [$title, $score]) {
            $criterion = RubricCriterion::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'rubric_id' => $rubric->id, 'title' => $title],
                ['description' => 'Đánh giá '.$title.' theo chuẩn demo học liệu thực tế.', 'max_score' => $score, 'sort_order' => $index + 1, 'metadata' => ['realistic_seed' => true]]
            );
            foreach ([['Xuất sắc', $score], ['Đạt', round($score * 0.7, 2)], ['Cần bổ sung', round($score * 0.4, 2)]] as $levelIndex => [$name, $levelScore]) {
                RubricLevel::query()->updateOrCreate(
                    ['tenant_id' => $tenantId, 'criterion_id' => $criterion->id, 'level_name' => $name],
                    ['description' => $name.' cho tiêu chí '.$title, 'score' => $levelScore, 'sort_order' => $levelIndex + 1]
                );
            }
        }

        return $rubric;
    }

    private function gradebook(int $tenantId, int $ownerId, Course $course, int $courseNumber, array $spec): Gradebook
    {
        return Gradebook::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'course_id' => $course->id, 'title' => 'RLC Gradebook - '.$course->code],
            [
                'class_id' => 5000 + $courseNumber,
                'grading_scheme' => 'weighted',
                'status' => $courseNumber % 10 === 0 ? 'locked' : 'active',
                'settings' => [
                    'realistic_seed' => true,
                    'pass_percent' => $spec['group'] === 'Chứng chỉ quốc tế' ? 65 : 50,
                    'sis_sync_ready' => true,
                ],
                'created_by' => $ownerId,
                'locked_by' => $courseNumber % 10 === 0 ? $ownerId : null,
                'locked_at' => $courseNumber % 10 === 0 ? now()->subDay() : null,
            ]
        );
    }

    private function gradeItems(int $tenantId, Gradebook $gradebook): array
    {
        $items = [];
        foreach ([['Quiz', 'quiz', 30], ['Assignment', 'assignment', 30], ['Attendance', 'attendance', 10], ['Final/Mock Exam', 'exam', 30]] as $sort => [$title, $source, $weight]) {
            $category = GradeCategory::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'gradebook_id' => $gradebook->id, 'title' => $title],
                ['weight' => $weight, 'max_score' => 10, 'aggregation_method' => 'weighted', 'sort_order' => $sort + 1]
            );
            $items[] = GradeItem::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'gradebook_id' => $gradebook->id, 'title' => $title],
                [
                    'category_id' => $category->id,
                    'source_type' => $source,
                    'max_score' => 10,
                    'weight' => $weight,
                    'required' => true,
                    'sort_order' => $sort + 1,
                    'settings' => ['realistic_seed' => true],
                ]
            );
        }

        return $items;
    }

    private function gradeSamples(int $tenantId, int $ownerId, Gradebook $gradebook, array $items, $students, int $courseNumber): void
    {
        foreach ($students->take(24) as $index => $student) {
            $total = 0;
            $max = 0;
            foreach ($items as $itemIndex => $item) {
                $score = round(5 + (($courseNumber + $index + $itemIndex) % 46) / 10, 2);
                LearnerGrade::query()->updateOrCreate(
                    ['tenant_id' => $tenantId, 'grade_item_id' => $item->id, 'user_id' => $student->id],
                    [
                        'gradebook_id' => $gradebook->id,
                        'raw_score' => $score,
                        'final_score' => $score,
                        'letter_grade' => $this->letterGrade($score * 10),
                        'pass_status' => $score >= 5 ? 'passed' : 'failed',
                        'source_status' => $gradebook->status === 'locked' ? 'locked' : 'final',
                        'feedback' => $score < 6 ? 'Cần hoàn thiện thêm quiz hoặc assignment còn yếu.' : 'Tiến độ ổn định.',
                        'updated_by' => $ownerId,
                    ]
                );
                $total += $score * ((float) ($item->weight ?? 0));
                $max += 10 * ((float) ($item->weight ?? 0));
            }
            $percent = $max > 0 ? round($total / $max * 100, 2) : 0;
            GradeSummary::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'gradebook_id' => $gradebook->id, 'user_id' => $student->id],
                [
                    'total_score' => round($percent / 10, 2),
                    'max_score' => 10,
                    'percent' => $percent,
                    'letter_grade' => $this->letterGrade($percent),
                    'pass_status' => $percent >= 50 ? 'passed' : 'failed',
                    'status' => $gradebook->status === 'locked' ? 'locked' : 'approved',
                    'approved_by' => $ownerId,
                    'approved_at' => now()->subDays(2),
                    'locked_by' => $gradebook->status === 'locked' ? $ownerId : null,
                    'locked_at' => $gradebook->status === 'locked' ? now()->subDay() : null,
                    'metadata' => ['realistic_seed' => true, 'sis_sync_status' => 'ready'],
                ]
            );
        }

        GradeApprovalBatch::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'gradebook_id' => $gradebook->id, 'title' => 'RLC Batch duyệt điểm'],
            [
                'status' => $gradebook->status === 'locked' ? 'locked' : 'approved',
                'submitted_by' => $ownerId,
                'approved_by' => $ownerId,
                'locked_by' => $gradebook->status === 'locked' ? $ownerId : null,
                'submitted_at' => now()->subDays(3),
                'approved_at' => now()->subDays(2),
                'locked_at' => $gradebook->status === 'locked' ? now()->subDay() : null,
                'sync_status' => 'queued',
                'metadata' => ['realistic_seed' => true],
            ]
        );
    }

    private function attendance(int $tenantId, int $ownerId, Course $course, int $courseNumber, $students): void
    {
        $session = AttendanceSession::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'course_id' => $course->id, 'title' => 'RLC Điểm danh buổi học chính'],
            [
                'class_id' => 5000 + $courseNumber,
                'attendance_type' => $courseNumber % 3 === 0 ? 'otp' : 'qr',
                'open_at' => now()->subDays(2)->setTime(8, 0),
                'close_at' => now()->subDays(2)->setTime(10, 30),
                'qr_token' => 'RLC-QR-'.$course->code,
                'otp_code' => str_pad((string) (100000 + $courseNumber), 6, '0', STR_PAD_LEFT),
                'status' => 'locked',
                'created_by' => $ownerId,
                'locked_by' => $ownerId,
                'locked_at' => now()->subDay(),
                'settings' => ['realistic_seed' => true, 'sis_sync_ready' => true],
            ]
        );

        foreach ($students->take(30) as $index => $student) {
            $status = match (($courseNumber + $index) % 10) {
                0 => 'absent',
                1, 2 => 'late',
                default => 'present',
            };
            AttendanceRecord::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'attendance_session_id' => $session->id, 'user_id' => $student->id],
                [
                    'live_session_id' => null,
                    'status' => $status,
                    'checkin_at' => $status === 'absent' ? null : now()->subDays(2)->setTime(8, $status === 'late' ? 18 : 3),
                    'checkout_at' => $status === 'absent' ? null : now()->subDays(2)->setTime(10, 15),
                    'attended_minutes' => $status === 'absent' ? 0 : ($status === 'late' ? 105 : 132),
                    'source' => $courseNumber % 3 === 0 ? 'otp' : 'qr',
                    'note' => $status === 'late' ? 'Đi muộn do vào lớp sau thời điểm mở QR.' : null,
                    'verified_by' => $ownerId,
                    'metadata' => ['realistic_seed' => true, 'sis_export_status' => 'ready'],
                ]
            );
        }
    }

    private function certificateTemplate(int $tenantId): CertificateTemplate
    {
        return CertificateTemplate::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'RLC-CERT-TPL'],
            [
                'name' => 'Mẫu chứng chỉ EraLMS học liệu thực tế',
                'type' => 'certificate',
                'language' => 'vi',
                'status' => 'published',
                'canvas_schema' => [
                    'width' => 1123,
                    'height' => 794,
                    'elements' => [
                        ['type' => 'text', 'field' => 'certificate_title', 'x' => 110, 'y' => 170, 'fontSize' => 34],
                        ['type' => 'text', 'field' => 'learner_name', 'x' => 180, 'y' => 330, 'fontSize' => 42],
                        ['type' => 'qr', 'field' => 'qr_payload', 'x' => 912, 'y' => 604, 'size' => 120],
                    ],
                ],
                'dynamic_fields' => ['learner_name', 'certificate_title', 'course_title', 'issued_at', 'qr_payload'],
                'signature_image_path' => '/images/signatures/rlc-director.png',
                'translations' => ['vi' => ['title' => 'Chứng nhận hoàn thành'], 'en' => ['title' => 'Certificate of Completion']],
                'settings' => ['qr_code' => true, 'hash_verify' => true, 'sis_sync_ready' => true],
            ]
        );
    }

    private function certificate(int $tenantId, CertificateTemplate $template, Course $course, int $courseNumber, array $spec): Certificate
    {
        return Certificate::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'RLC-CERT-'.str_pad((string) $courseNumber, 3, '0', STR_PAD_LEFT)],
            [
                'certificate_template_id' => $template->id,
                'title' => 'Chứng nhận hoàn thành '.$course->title,
                'description' => 'Cấp tự động khi học viên hoàn thành learning path, đạt điểm và đủ điều kiện điểm danh.',
                'credential_type' => $spec['group'] === 'Chứng chỉ quốc tế' ? 'international_certificate_path' : 'course_completion',
                'issuer_name' => 'EraLMS Demo',
                'rules' => ['progress_percent' => 100, 'min_score' => $spec['group'] === 'Chứng chỉ quốc tế' ? 65 : 50, 'min_attendance' => 80],
                'metadata' => ['realistic_seed' => true, 'sis_sync_ready' => true, 'group' => $spec['group']],
                'status' => 'active',
            ]
        );
    }

    private function certificateIssues(int $tenantId, Certificate $certificate, CertificateTemplate $template, Course $course, $students, int $courseNumber, array $spec): void
    {
        foreach ($students->take(6) as $index => $student) {
            $issueCode = 'RLC-CI-'.str_pad((string) $courseNumber, 3, '0', STR_PAD_LEFT).'-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
            CertificateIssue::query()->updateOrCreate(
                ['issue_code' => $issueCode],
                [
                    'tenant_id' => $tenantId,
                    'certificate_id' => $certificate->id,
                    'certificate_template_id' => $template->id,
                    'user_id' => $student->id,
                    'course_id' => $course->id,
                    'learner_name' => $student->full_name,
                    'certificate_title' => $certificate->title,
                    'status' => 'issued',
                    'issued_at' => now()->subDays($index + 1),
                    'expires_at' => $spec['group'] === 'Chứng chỉ quốc tế' ? now()->addYears(2) : null,
                    'qr_payload' => 'https://verify.eralms.test/cert/'.$issueCode,
                    'verification_hash' => hash('sha256', $issueCode.'|'.$student->id.'|'.$course->id),
                    'verification_url' => 'https://verify.eralms.test/cert/'.$issueCode,
                    'language' => $spec['group'] === 'Chứng chỉ quốc tế' ? 'en' : 'vi',
                    'field_values' => [
                        'course_title' => $course->title,
                        'group' => $spec['group'],
                        'final_score' => 75 + (($courseNumber + $index) % 20),
                    ],
                    'sis_payload' => [
                        'external_student_id' => 'SIS-U-'.$student->id,
                        'external_course_code' => 'SIS-'.$course->code,
                        'sync_status' => 'queued',
                    ],
                    'blockchain_status' => 'pending',
                ]
            );
        }
    }

    private function sisSystem(int $tenantId): IntegrationSystem
    {
        return IntegrationSystem::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'code' => 'RLC-SIS'],
            [
                'name' => 'EraLMS Demo SIS - Bộ học liệu thực tế',
                'system_type' => 'sis',
                'base_url' => 'https://sis-demo.eralms.test/api',
                'auth_type' => 'api_key',
                'credentials_encrypted' => Crypt::encryptString(json_encode(['api_key' => 'rlc-demo-secret'])),
                'status' => 'active',
                'settings' => ['realistic_seed' => true, 'supported_entities' => ['course', 'class', 'student', 'grade', 'attendance', 'certificate']],
            ]
        );
    }

    private function sisMappings(int $tenantId, IntegrationSystem $sis, Course $course, int $courseNumber, array $spec): void
    {
        IntegrationMapping::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'system_id' => $sis->id, 'entity_type' => 'course', 'local_id' => (string) $course->id],
            [
                'external_id' => 'RLC-SIS-C-'.$courseNumber,
                'external_code' => 'SIS-'.$course->code,
                'mapping_status' => 'active',
                'metadata' => ['title' => $course->title, 'group' => $spec['group']],
            ]
        );
        IntegrationMapping::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'system_id' => $sis->id, 'entity_type' => 'class', 'local_id' => (string) (5000 + $courseNumber)],
            [
                'external_id' => 'RLC-SIS-CLASS-'.$courseNumber,
                'external_code' => 'RLC-CLS-'.str_pad((string) $courseNumber, 3, '0', STR_PAD_LEFT),
                'mapping_status' => 'active',
                'metadata' => ['course_code' => $course->code, 'cohort' => 'Demo 2026'],
            ]
        );
    }

    private function sisJobsAndEvents(int $tenantId, IntegrationSystem $sis): void
    {
        foreach ([
            ['pull', 'students', 'success', 120, 120, 0],
            ['pull', 'enrollments', 'success', 1000, 992, 8],
            ['push', 'grades', 'running', 2400, 1800, 0],
            ['push', 'attendance', 'success', 3000, 3000, 0],
            ['push', 'certificates', 'pending', 600, 0, 0],
        ] as [$jobType, $entityType, $status, $total, $success, $failed]) {
            SyncJob::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'system_id' => $sis->id, 'job_type' => $jobType, 'entity_type' => $entityType],
                [
                    'status' => $status,
                    'total_count' => $total,
                    'success_count' => $success,
                    'failed_count' => $failed,
                    'started_at' => $status === 'pending' ? null : now()->subMinutes(45),
                    'finished_at' => $status === 'success' ? now()->subMinutes(15) : null,
                    'error_report' => $failed > 0 ? ['summary' => 'Một số ghi danh thiếu mapping lớp ở SIS demo.'] : null,
                    'created_by' => 1,
                ]
            );
        }

        foreach ([
            ['sis.course.updated', 'inbound', 'course', 'success'],
            ['sis.student.enrolled', 'inbound', 'enrollment', 'success'],
            ['lms.grade.ready', 'outbound', 'grade', 'pending'],
            ['lms.attendance.locked', 'outbound', 'attendance', 'success'],
            ['lms.certificate.issued', 'outbound', 'certificate', 'pending'],
        ] as $index => [$eventKey, $direction, $entityType, $status]) {
            IntegrationEvent::query()->updateOrCreate(
                ['system_id' => $sis->id, 'event_key' => $eventKey, 'idempotency_key' => 'rlc-event-'.$index],
                [
                    'tenant_id' => $tenantId,
                    'direction' => $direction,
                    'entity_type' => $entityType,
                    'entity_id' => (string) ($index + 1),
                    'payload' => ['realistic_seed' => true, 'event_key' => $eventKey],
                    'status' => $status,
                    'attempts' => $status === 'success' ? 1 : 0,
                    'processed_at' => $status === 'success' ? now()->subMinutes(10) : null,
                ]
            );
        }
    }

    private function courseSpecs(): array
    {
        $specs = [];
        $thptChapters = [
            'Toán' => ['Hàm số', 'Mũ và logarit', 'Nguyên hàm', 'Tích phân', 'Số phức', 'Hình học Oxyz', 'Xác suất', 'Cấp số', 'Phương trình lượng giác', 'Ôn đề tổng hợp'],
            'Văn' => ['Đọc hiểu văn bản', 'Nghị luận xã hội', 'Nghị luận văn học', 'Thơ hiện đại', 'Truyện ngắn Việt Nam', 'Tác phẩm nước ngoài', 'Kỹ năng mở bài kết bài', 'Lập dàn ý nhanh', 'Diễn đạt và liên kết', 'Ôn đề tổng hợp'],
            'Anh' => ['Grammar', 'Reading', 'Listening', 'Vocabulary', 'Cloze test', 'Sentence transformation', 'Pronunciation', 'Communication', 'Error identification', 'Ôn đề tổng hợp'],
            'Lý' => ['Dao động cơ', 'Sóng cơ', 'Điện xoay chiều', 'Mạch RLC', 'Sóng ánh sáng', 'Lượng tử ánh sáng', 'Hạt nhân nguyên tử', 'Điện trường', 'Từ trường', 'Ôn đề tổng hợp'],
            'Hóa' => ['Este lipid', 'Cacbohydrat', 'Amin amino axit', 'Polime', 'Đại cương kim loại', 'Sắt và hợp chất', 'Hóa vô cơ tổng hợp', 'Điện phân', 'Nhận biết chất', 'Ôn đề tổng hợp'],
            'Sinh' => ['Cơ chế di truyền', 'Quy luật di truyền', 'Di truyền quần thể', 'Ứng dụng di truyền', 'Tiến hóa', 'Sinh thái cá thể', 'Quần xã sinh vật', 'Hệ sinh thái', 'Bài tập phả hệ', 'Ôn đề tổng hợp'],
            'Sử' => ['Việt Nam 1919-1930', 'Việt Nam 1930-1945', 'Kháng chiến chống Pháp', 'Kháng chiến chống Mỹ', 'Đổi mới đất nước', 'Trật tự thế giới', 'ASEAN', 'Chiến tranh lạnh', 'Lịch sử thế giới hiện đại', 'Ôn đề tổng hợp'],
            'Địa' => ['Địa lý tự nhiên Việt Nam', 'Dân cư lao động', 'Chuyển dịch cơ cấu kinh tế', 'Nông nghiệp', 'Công nghiệp', 'Dịch vụ', 'Vùng kinh tế', 'Biển đảo', 'Atlat địa lý', 'Ôn đề tổng hợp'],
        ];
        foreach ($thptChapters as $subject => $chapters) {
            foreach (['Lộ trình chuẩn', 'Chuyên đề vận dụng', 'Luyện đề 30 ngày', 'Tăng tốc điểm 8+'] as $track) {
                $specs[] = $this->spec('Ôn tập THPT', $subject, $track, 'Ôn tập THPT '.$subject.' - '.$track, 'high_school_review', 'online', 35, $chapters);
            }
        }

        $vh9Subjects = [
            ['Toán', 'Cơ bản 9+'], ['Văn', 'Cơ bản 9+'], ['Anh', 'Cơ bản 9+'], ['Sử/Địa', 'Cơ bản 9+'],
            ['Toán', 'Tăng cường'], ['Văn', 'Tăng cường'], ['Anh', 'Tăng cường'], ['Lý', 'Tăng cường'], ['Hóa', 'Tăng cường'], ['Sinh', 'Tăng cường'], ['Sử', 'Tăng cường'],
            ['Toán', 'Học kỳ 1'], ['Văn', 'Học kỳ 1'], ['Anh', 'Học kỳ 1'], ['Lý', 'Học kỳ 1'], ['Hóa', 'Học kỳ 1'], ['Sinh', 'Học kỳ 1'], ['Sử', 'Học kỳ 1'], ['Sử/Địa', 'Dự án địa phương'], ['Anh', 'Giao tiếp lớp học'],
        ];
        foreach ($vh9Subjects as [$subject, $track]) {
            $specs[] = $this->spec('Văn hóa 9+', $subject, $track, 'Văn hóa 9+ '.$subject.' - '.$track, 'grade_9_plus', 'blended', 28, $this->genericChapters($subject));
        }

        foreach ([
            ['CNTT', 'Tin học cơ sở'], ['CNTT', 'Mạng máy tính'], ['CNTT', 'Hệ điều hành'], ['CNTT', 'CSDL'], ['CNTT', 'Lập trình web'],
            ['Du lịch', 'Nghiệp vụ lễ tân'], ['Du lịch', 'Nhà hàng'], ['Du lịch', 'Khách sạn'], ['Du lịch', 'Giao tiếp khách hàng'],
            ['Ngoại ngữ', 'Tiếng Anh giao tiếp'], ['Ngoại ngữ', 'Tiếng Hàn sơ cấp'], ['Ngoại ngữ', 'Tiếng Hoa sơ cấp'],
            ['CNTT', 'Thực hành helpdesk'], ['CNTT', 'An toàn thông tin cơ bản'], ['Du lịch', 'Xử lý tình huống dịch vụ'], ['Du lịch', 'Thiết kế tour nội địa'], ['Ngoại ngữ', 'Phỏng vấn nghề nghiệp'], ['CNTT', 'Dự án website nghề'],
        ] as [$subject, $name]) {
            $specs[] = $this->spec('Trung cấp nghề', $subject, 'Module nghề', 'Trung cấp nghề - '.$name, 'intermediate_vocational', 'blended', 45, $this->vocationalChapters($name));
        }

        foreach ([
            ['CNTT', 'Lập trình Web'], ['CNTT', 'Laravel'], ['CNTT', 'Java'], ['CNTT', 'Mobile'], ['CNTT', 'AI cơ bản'],
            ['Du lịch', 'Quản trị khách sạn'], ['Du lịch', 'Quản trị lữ hành'],
            ['Kinh doanh', 'Marketing số'], ['Kinh doanh', 'Thương mại điện tử'], ['Kinh doanh', 'CRM'],
            ['CNTT', 'Kiểm thử phần mềm'], ['CNTT', 'DevOps căn bản'], ['CNTT', 'Phân tích dữ liệu'], ['Du lịch', 'Revenue management'], ['Du lịch', 'Tổ chức sự kiện'], ['Kinh doanh', 'Bán hàng B2B'], ['Kinh doanh', 'Phân tích thị trường'], ['CNTT', 'Đồ án tốt nghiệp'],
        ] as [$subject, $name]) {
            $specs[] = $this->spec('Cao đẳng chính quy', $subject, 'Học phần', 'Cao đẳng - '.$name, 'college', 'blended', 60, $this->collegeChapters($name));
        }

        foreach ([
            ['Tiếng Anh', 'TOEIC'], ['Tiếng Anh', 'IELTS'], ['Tiếng Anh', 'TOEFL'],
            ['Tiếng Hoa', 'HSK1'], ['Tiếng Hoa', 'HSK2'], ['Tiếng Hoa', 'HSK3'], ['Tiếng Hoa', 'HSK4'],
            ['Tiếng Hàn', 'TOPIK I'], ['Tiếng Hàn', 'TOPIK II'],
            ['Tiếng Anh', 'TOEIC cấp tốc 650'], ['Tiếng Anh', 'IELTS Writing Clinic'], ['Tiếng Hàn', 'TOPIK Mock Camp'],
        ] as [$subject, $name]) {
            $specs[] = $this->spec('Chứng chỉ quốc tế', $subject, 'Chứng chỉ', 'Chứng chỉ quốc tế - '.$name, 'international_certificate', 'online', 50, $this->certificateChapters($name));
        }

        return array_slice($specs, 0, self::COURSE_TARGET);
    }

    private function spec(string $group, string $subject, string $track, string $title, string $level, string $courseType, int $hours, array $chapters): array
    {
        return [
            'group' => $group,
            'subject' => $subject,
            'track' => $track,
            'title' => $title,
            'level' => $level,
            'course_type' => $courseType,
            'hours' => $hours,
            'chapters' => array_slice(array_pad($chapters, self::LESSONS_PER_COURSE, 'Ôn tập và đánh giá tổng hợp'), 0, self::LESSONS_PER_COURSE),
            'summary' => 'Khóa học demo thực tế cho '.$group.' - '.$subject.', có video, quiz, assignment, sổ điểm và đồng bộ SIS.',
            'description' => 'Nội dung được thiết kế để trình diễn trọn luồng học tập trên EraLMS: học qua video, mở khóa quiz, nộp bài, ghi nhận điểm danh, tính sổ điểm, cấp chứng chỉ và gửi dữ liệu sang SIS.',
        ];
    }

    private function genericChapters(string $subject): array
    {
        return ['Khởi động '.$subject, 'Ôn kiến thức nền', 'Bài học trọng tâm 1', 'Bài học trọng tâm 2', 'Thực hành có hướng dẫn', 'Luyện tập phân hóa', 'Ứng dụng thực tế', 'Dự án nhỏ', 'Ôn tập học kỳ', 'Đánh giá cuối mô đun'];
    }

    private function vocationalChapters(string $name): array
    {
        return ['Tổng quan nghề '.$name, 'An toàn và quy trình', 'Công cụ làm việc', 'Tình huống cơ bản', 'Thực hành có hướng dẫn', 'Xử lý lỗi thường gặp', 'Tiêu chuẩn chất lượng', 'Mô phỏng ca làm việc', 'Báo cáo thực hành', 'Đánh giá tay nghề'];
    }

    private function collegeChapters(string $name): array
    {
        return ['Tổng quan học phần '.$name, 'Nền tảng lý thuyết', 'Thiết kế giải pháp', 'Thực hành phòng lab', 'Case study doanh nghiệp', 'Làm việc nhóm', 'Đánh giá giữa kỳ', 'Project ứng dụng', 'Phản biện sản phẩm', 'Đánh giá cuối kỳ'];
    }

    private function certificateChapters(string $name): array
    {
        return ['Placement Test '.$name, 'Phân tích năng lực đầu vào', 'Learning Path nền tảng', 'Kỹ năng 1 theo chuẩn đề', 'Kỹ năng 2 theo chuẩn đề', 'Chiến lược làm bài', 'Mock Exam 1', 'Chữa đề Mock Exam 1', 'Mock Exam 2', 'Certificate Readiness'];
    }

    private function outcomes(array $spec, string $chapterTitle): array
    {
        return [
            'Giải thích được trọng tâm '.$chapterTitle.' trong bối cảnh '.$spec['subject'].'.',
            'Hoàn thành bài luyện tập hoặc nhiệm vụ thực hành có minh chứng.',
            'Đạt điều kiện mở khóa hoạt động tiếp theo trong learning path.',
        ];
    }

    private function contentDescription(array $spec, string $chapterTitle, string $label): string
    {
        return $label.' cho chủ đề '.$chapterTitle.', dùng trong khóa '.$spec['title'].' để hỗ trợ học viên học, luyện tập và tự đánh giá.';
    }

    private function questionStem(array $spec, int $index): string
    {
        return match ($index) {
            1 => 'Chọn nhận định đúng nhất về trọng tâm của '.$spec['subject'].' trong bài học.',
            2 => 'Khi áp dụng kiến thức vào tình huống thực tế, bước nào cần thực hiện trước?',
            3 => 'Dữ kiện nào là căn cứ quan trọng nhất để giải quyết bài tập trong học liệu?',
            4 => 'Nếu kết quả chưa đạt yêu cầu, phương án cải thiện phù hợp nhất là gì?',
            default => 'Trình bày ngắn cách bạn vận dụng nội dung bài học vào một tình huống cụ thể.',
        };
    }

    private function optionsFor(array $spec, int $index): array
    {
        return [
            ['A', 'Xác định mục tiêu bài học, đọc dữ kiện và kiểm tra điều kiện áp dụng.', true],
            ['B', 'Bỏ qua phần ví dụ để chuyển ngay sang bài tập cuối bài.', false],
            ['C', 'Chỉ ghi nhớ đáp án mà không phân tích quy trình làm bài.', false],
            ['D', 'Đợi đến cuối khóa mới xem lại phần kiến thức nền.', false],
        ];
    }

    private function assignmentPrompt(array $spec, string $chapterTitle): string
    {
        if ($spec['group'] === 'Ôn tập THPT') {
            return 'Hoàn thành đề ôn tập chủ đề '.$chapterTitle.', ghi rõ phương pháp giải, lỗi thường gặp và kế hoạch cải thiện điểm.';
        }
        if ($spec['group'] === 'Trung cấp nghề') {
            return 'Nộp minh chứng thực hành '.$chapterTitle.': ảnh/video thao tác, checklist quy trình và nhận xét tự đánh giá.';
        }
        if ($spec['group'] === 'Cao đẳng chính quy') {
            return 'Xây dựng sản phẩm hoặc báo cáo case study cho '.$chapterTitle.', kèm phân tích yêu cầu, kết quả và hướng cải tiến.';
        }
        if ($spec['group'] === 'Chứng chỉ quốc tế') {
            return 'Hoàn thành nhiệm vụ luyện thi '.$chapterTitle.', phân tích điểm mạnh/yếu và mục tiêu điểm ở mock exam tiếp theo.';
        }

        return 'Nộp bài tập '.$chapterTitle.' theo trình tự Video -> Quiz -> Assignment, có phần tự phản ánh sau khi hoàn thành.';
    }

    private function letterGrade(float $percent): string
    {
        return match (true) {
            $percent >= 90 => 'A',
            $percent >= 80 => 'B+',
            $percent >= 70 => 'B',
            $percent >= 60 => 'C+',
            $percent >= 50 => 'C',
            default => 'F',
        };
    }
}
