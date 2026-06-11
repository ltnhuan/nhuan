<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\ContentRepositoryItem;
use App\Models\ContentVersion;
use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\CourseSection;
use App\Models\Exam;
use App\Models\ExamEnrollment;
use App\Models\ExamQuestion;
use App\Models\ExamSection;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\QuestionOption;
use App\Models\Rubric;
use App\Models\RubricCriterion;
use App\Models\RubricLevel;
use App\Models\VideoAsset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MeaningfulCourseLessonSeeder extends Seeder
{
    private const COMPONENTS = ['text', 'video', 'pdf', 'quiz', 'assignment'];

    public function run(): void
    {
        DB::statement('PRAGMA busy_timeout = 60000');

        Course::query()
            ->orderBy('id')
            ->chunkById(25, function ($courses): void {
                foreach ($courses as $course) {
                    DB::transaction(fn () => $this->enrichCourse($course->fresh()));
                }
            });
    }

    private function enrichCourse(Course $course): void
    {
        $course->forceFill([
            'settings' => array_replace_recursive($course->settings ?? [], [
                'lesson_detail_seeded' => true,
                'lesson_detail_seeded_at' => now()->toDateTimeString(),
                'completion_policy' => 'required_components',
                'publish_constraints' => [
                    'requires_section' => true,
                    'requires_unit' => true,
                    'requires_component_source' => true,
                    'requires_completion_rule' => true,
                ],
            ]),
        ])->save();

        $units = $this->ensureSectionsAndUnits($course);
        $bank = $this->ensureQuestionBank($course);
        $rubric = $this->ensureRubric($course);

        foreach ($units as $index => $unit) {
            $this->enrichUnit($course, $unit, $index + 1);
            $this->ensureUnitComponents($course, $unit);

            $unit->components()->orderBy('sort_order')->get()->each(function (CourseComponent $component) use ($course, $unit, $bank, $rubric): void {
                $this->enrichComponent($course, $unit, $component, $bank, $rubric);
            });
        }
    }

    private function ensureSectionsAndUnits(Course $course)
    {
        $allSections = CourseSection::query()
            ->where('course_id', $course->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $sections = $allSections->where('type', 'section')->values();
        $rootUnits = $allSections->where('type', 'unit')->whereNull('parent_id')->values();

        if ($sections->isEmpty()) {
            $sectionTitles = $this->sectionTitles($course);
            foreach ($sectionTitles as $i => $title) {
                $sections->push(CourseSection::query()->create([
                    'tenant_id' => $course->tenant_id,
                    'course_id' => $course->id,
                    'parent_id' => null,
                    'type' => 'section',
                    'title' => $title,
                    'description' => $this->sectionDescription($course, $i),
                    'sort_order' => $i + 1,
                    'status' => $this->statusFor($course),
                    'settings' => $this->learningSettings($course, null, ($i + 1) * 90),
                ]));
            }
        }

        if ($rootUnits->isNotEmpty()) {
            $rootUnits->each(function (CourseSection $unit, int $i) use ($sections): void {
                $parent = $sections[$i % max(1, $sections->count())];
                $unit->forceFill(['parent_id' => $parent->id])->save();
            });
        }

        $sections->each(function (CourseSection $section, int $sectionIndex) use ($course): void {
            $units = CourseSection::query()
                ->where('course_id', $course->id)
                ->where('parent_id', $section->id)
                ->where('type', 'unit')
                ->orderBy('sort_order')
                ->get();

            if ($units->isEmpty()) {
                foreach (range(1, 3) as $i) {
                    CourseSection::query()->create([
                        'tenant_id' => $course->tenant_id,
                        'course_id' => $course->id,
                        'parent_id' => $section->id,
                        'type' => 'unit',
                        'title' => $this->unitTitle($course, ($sectionIndex * 3) + $i),
                        'description' => $this->unitDescription($course, ($sectionIndex * 3) + $i),
                        'sort_order' => $i,
                        'status' => $this->statusFor($course),
                        'settings' => $this->learningSettings($course, 'unit', 45),
                    ]);
                }
            }
        });

        return CourseSection::query()
            ->where('course_id', $course->id)
            ->where('type', 'unit')
            ->whereNotNull('parent_id')
            ->orderBy('parent_id')
            ->orderBy('sort_order')
            ->get();
    }

    private function enrichUnit(Course $course, CourseSection $unit, int $position): void
    {
        $isGeneric = preg_match('/^(Bài|Unit|Lesson|Chương)\s+\d+/iu', $unit->title) === 1
            || Str::contains($unit->title, ['Placement Test', 'Phân tích năng lực']);

        $title = $isGeneric ? $this->unitTitle($course, $position) : $unit->title;

        $unit->forceFill([
            'title' => $title,
            'description' => $isGeneric ? $this->unitDescription($course, $position) : ($unit->description ?: $this->unitDescription($course, $position)),
            'status' => $this->statusFor($course),
            'settings' => array_replace_recursive($unit->settings ?? [], $this->learningSettings($course, 'unit', 45 + (($position % 3) * 15)), [
                'outcomes' => $this->unitOutcomes($course, $title),
            ]),
        ])->save();
    }

    private function ensureUnitComponents(Course $course, CourseSection $unit): void
    {
        $existingTypes = CourseComponent::query()
            ->where('course_id', $course->id)
            ->where('section_id', $unit->id)
            ->pluck('component_type')
            ->all();

        foreach (self::COMPONENTS as $i => $type) {
            if (in_array($type, $existingTypes, true)) {
                continue;
            }

            CourseComponent::query()->create([
                'tenant_id' => $course->tenant_id,
                'course_id' => $course->id,
                'section_id' => $unit->id,
                'component_type' => $type,
                'title' => $this->componentTitle($type, $unit->title),
                'content_id' => null,
                'config' => $this->baseComponentConfig($course, $unit, $type),
                'sort_order' => ($i + 1) * 10,
                'required' => true,
                'status' => $this->statusFor($course),
            ]);
        }
    }

    private function enrichComponent(Course $course, CourseSection $unit, CourseComponent $component, QuestionBank $bank, Rubric $rubric): void
    {
        $type = $component->component_type ?: 'text';
        $currentConfig = $component->config ?? [];

        if (($currentConfig['meaningful_lesson_seeded'] ?? false) === true
            && $this->componentHasRequiredSource($component, $type)
            && $this->componentConfigComplete($currentConfig, $type)) {
            if ($type === 'quiz') {
                $exam = Exam::query()->where('component_id', $component->id)->first();
                if ($exam) {
                    $this->ensureExamEnrollments($course, $exam);
                }
            }

            return;
        }

        $title = $this->componentTitle($type, $unit->title);
        $config = array_replace_recursive($this->baseComponentConfig($course, $unit, $type), $component->config ?? []);

        if (in_array($type, ['video', 'pdf', 'file', 'scorm'], true)) {
            $item = $this->ensureRepositoryItem($course, $unit, $component, $type);
            $component->content_id = $item->id;
            $config['repository_item_id'] = $item->id;
            $config['source_policy'] = [
                'source' => 'shared_repository',
                'version_required' => true,
                'offline_ready' => $type !== 'video',
            ];

            if ($type === 'video') {
                $asset = $this->ensureVideoAsset($course, $component, $item);
                $config['video_asset_id'] = $asset->id;
                $config['duration_seconds'] = $asset->duration_seconds;
                $config['completion_rule'] = ['type' => 'watch_percent', 'percent' => 80];
            }
        }

        if ($type === 'quiz') {
            $exam = $this->ensureExam($course, $unit, $component, $bank);
            $this->ensureExamEnrollments($course, $exam);
            $config['exam_id'] = $exam->id;
            $config['exam_title'] = $exam->title;
            $config['exam_code'] = $exam->code;
            $config['exam_description'] = $exam->description;
            $config['exam_type'] = $exam->exam_type;
            $config['questions_count'] = 5;
            $config['question_count'] = 5;
            $config['duration_minutes'] = $exam->duration_minutes;
            $config['total_score'] = (float) $exam->total_score;
            $config['pass_score'] = (float) $exam->pass_score;
            $config['max_attempts'] = $exam->max_attempts;
            $config['shuffle_questions'] = $exam->shuffle_questions;
            $config['shuffle_options'] = $exam->shuffle_options;
            $config['show_result_mode'] = $exam->show_result_mode;
            $config['completion_rule'] = ['type' => 'score', 'score' => (float) $exam->pass_score];
        }

        if ($type === 'assignment') {
            $assignment = $this->ensureAssignment($course, $unit, $component, $rubric);
            $config['assignment_id'] = $assignment->id;
            $config['rubric_id'] = $rubric->id;
            $config['max_score'] = (float) $assignment->max_score;
            $config['pass_score'] = (float) $assignment->pass_score;
            $config['completion_rule'] = ['type' => 'submission'];
        }

        $component->forceFill([
            'title' => $this->componentLooksGeneric($component->title) ? $title : $component->title,
            'config' => $config,
            'required' => true,
            'status' => $this->statusFor($course),
        ])->save();
    }

    private function componentHasRequiredSource(CourseComponent $component, string $type): bool
    {
        return match ($type) {
            'video' => (bool) $component->content_id && VideoAsset::query()->where('component_id', $component->id)->exists(),
            'pdf', 'file', 'scorm' => (bool) $component->content_id,
            'quiz' => Exam::query()->where('component_id', $component->id)->exists(),
            'assignment' => Assignment::query()->where('component_id', $component->id)->exists(),
            default => true,
        };
    }

    private function componentConfigComplete(array $config, string $type): bool
    {
        return match ($type) {
            'quiz' => isset($config['exam_id'], $config['exam_title'], $config['exam_code'], $config['questions_count']),
            'assignment' => isset($config['assignment_id'], $config['rubric_id']),
            'video' => isset($config['repository_item_id'], $config['video_asset_id']),
            'pdf', 'file', 'scorm' => isset($config['repository_item_id']),
            default => isset($config['completion_rule']),
        };
    }

    private function ensureRepositoryItem(Course $course, CourseSection $unit, CourseComponent $component, string $type): ContentRepositoryItem
    {
        $extension = $type === 'video' ? 'mp4' : ($type === 'scorm' ? 'zip' : 'pdf');
        $mime = match ($type) {
            'video' => 'video/mp4',
            'scorm' => 'application/zip',
            default => 'application/pdf',
        };
        $storagePath = 'meaningful-lessons/'.$course->code.'/unit-'.$unit->id.'/component-'.$component->id.'.'.$extension;

        $item = ContentRepositoryItem::query()->firstOrNew([
            'tenant_id' => $course->tenant_id,
            'storage_path' => $storagePath,
        ]);

        $item->fill([
            'academic_unit_id' => $course->academic_unit_id,
            'item_type' => $type === 'video' ? 'video' : 'file',
            'title' => $this->componentTitle($type, $unit->title),
            'description' => 'Học liệu chính thức cho bài "'.$unit->title.'" trong khóa '.$course->title.'.',
            'mime_type' => $mime,
            'file_size' => $type === 'video' ? 180000000 : 2400000,
            'checksum' => sha1($storagePath),
            'owner_id' => $course->owner_id,
            'visibility' => 'internal',
            'status' => 'published',
            'metadata' => [
                'sample_repository_item' => true,
                'meaningful_lesson_seeded' => true,
                'course_id' => $course->id,
                'unit_id' => $unit->id,
                'component_id' => $component->id,
                'cap_do' => $course->level,
                'mon_hoc' => $course->title,
                'khoa' => $course->academic_unit_id,
                'CLO' => $this->clos($course),
                'PLO' => $this->plos($course),
                'thoi_luong_phut' => $type === 'video' ? 18 : 12,
                'version_history' => ['v1.0', 'v1.1'],
            ],
        ])->save();

        foreach ([1, 2] as $version) {
            ContentVersion::query()->updateOrCreate([
                'tenant_id' => $course->tenant_id,
                'content_item_id' => $item->id,
                'version' => $version,
            ], [
                'storage_path' => $storagePath,
                'checksum' => sha1($storagePath.'-v'.$version),
                'file_size' => $item->file_size,
                'change_note' => $version === 1 ? 'Khởi tạo học liệu bài học.' : 'Bổ sung mục tiêu, ràng buộc và ví dụ thực hành.',
                'created_by' => $course->owner_id,
                'created_at' => now()->subDays(2 - $version),
            ]);
        }

        return $item;
    }

    private function ensureVideoAsset(Course $course, CourseComponent $component, ContentRepositoryItem $item): VideoAsset
    {
        return VideoAsset::query()->updateOrCreate([
            'tenant_id' => $course->tenant_id,
            'component_id' => $component->id,
        ], [
            'repository_item_id' => $item->id,
            'course_id' => $course->id,
            'title' => $item->title,
            'description' => $item->description,
            'original_filename' => basename($item->storage_path),
            'original_storage_path' => $item->storage_path,
            'hls_master_path' => Str::replaceLast('.mp4', '/master.m3u8', $item->storage_path),
            'duration_seconds' => 18 * 60,
            'file_size' => $item->file_size ?? 180000000,
            'mime_type' => 'video/mp4',
            'processing_status' => 'ready',
            'visibility' => 'internal',
            'checksum' => $item->checksum,
            'thumbnail_url' => '/images/course-thumbnails/default-video.jpg',
            'subtitle_path' => Str::replaceLast('.mp4', '.vtt', $item->storage_path),
            'transcript_path' => Str::replaceLast('.mp4', '.txt', $item->storage_path),
            'settings' => ['quality' => ['720p', '480p'], 'captions' => true, 'sample_asset' => true],
            'uploaded_by' => $course->owner_id,
            'processed_at' => now(),
        ]);
    }

    private function ensureQuestionBank(Course $course): QuestionBank
    {
        return QuestionBank::query()->updateOrCreate([
            'tenant_id' => $course->tenant_id,
            'code' => 'ML-'.$course->code,
        ], [
            'academic_unit_id' => $course->academic_unit_id,
            'course_id' => $course->id,
            'name' => 'Ngân hàng câu hỏi theo bài - '.$course->title,
            'description' => 'Câu hỏi kiểm tra nhanh bám theo từng bài học, CLO và PLO.',
            'visibility' => 'internal',
            'status' => 'published',
            'owner_id' => $course->owner_id,
            'settings' => ['meaningful_lesson_seeded' => true, 'clo' => $this->clos($course), 'plo' => $this->plos($course)],
        ]);
    }

    private function ensureExam(Course $course, CourseSection $unit, CourseComponent $component, QuestionBank $bank): Exam
    {
        $questions = $this->ensureQuestions($course, $unit, $bank);
        $exam = Exam::query()->updateOrCreate([
            'tenant_id' => $course->tenant_id,
            'code' => 'ML-EX-'.$component->id,
        ], [
            'course_id' => $course->id,
            'component_id' => $component->id,
            'question_bank_id' => $bank->id,
            'title' => 'Quiz kiểm tra - '.$unit->title,
            'description' => 'Đánh giá nhanh mức đạt CLO của bài học "'.$unit->title.'".',
            'exam_type' => 'quiz',
            'delivery_mode' => 'self_paced',
            'status' => $this->statusFor($course),
            'total_score' => 10,
            'pass_score' => 7,
            'duration_minutes' => 15,
            'max_attempts' => 2,
            'shuffle_questions' => true,
            'shuffle_options' => true,
            'show_result_mode' => 'after_submit',
            'show_correct_answers' => true,
            'settings' => [
                'meaningful_lesson_seeded' => true,
                'constraints' => ['time_limit' => true, 'attempt_limit' => 2, 'required_score_percent' => 70],
                'clo' => $this->clos($course),
                'plo' => $this->plos($course),
            ],
            'created_by' => $course->owner_id,
            'approved_by' => $course->owner_id,
            'approved_at' => now(),
        ]);

        $section = ExamSection::query()->updateOrCreate([
            'tenant_id' => $course->tenant_id,
            'exam_id' => $exam->id,
            'sort_order' => 1,
        ], [
            'title' => 'Nội dung trọng tâm',
            'description' => 'Câu hỏi nhận biết, hiểu và vận dụng trực tiếp từ bài học.',
            'question_count' => $questions->count(),
            'score' => 10,
            'config' => ['randomize' => true, 'required' => true],
        ]);

        $questions->values()->each(function (Question $question, int $i) use ($course, $exam, $section): void {
            ExamQuestion::query()->updateOrCreate([
                'tenant_id' => $course->tenant_id,
                'exam_id' => $exam->id,
                'question_id' => $question->id,
            ], [
                'section_id' => $section->id,
                'score' => 2,
                'sort_order' => $i + 1,
                'required' => true,
                'metadata' => ['meaningful_lesson_seeded' => true],
            ]);
        });

        return $exam;
    }

    private function ensureExamEnrollments(Course $course, Exam $exam): void
    {
        $enrollmentRows = DB::table('enrollments')
            ->where('tenant_id', $course->tenant_id)
            ->where('course_id', $course->id)
            ->whereIn('status', ['active', 'completed'])
            ->orderBy('id')
            ->limit(100)
            ->get(['user_id', 'class_section_id']);

        $users = $enrollmentRows->mapWithKeys(fn ($row) => [(int) $row->user_id => $row->class_section_id])->all();

        $demoUsers = DB::table('lms_users')
            ->where('tenant_id', $course->tenant_id)
            ->whereIn('email', [
                'admin.lms@vabis.edu.vn',
                'daotao.lms@vabis.edu.vn',
                'khoa.lms@vabis.edu.vn',
                'gv.lms@vabis.edu.vn',
                'sv.lms@vabis.edu.vn',
            ])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($demoUsers as $userId) {
            $users[$userId] = $users[$userId] ?? null;
        }

        if ($users === []) {
            DB::table('lms_users')
                ->where('tenant_id', $course->tenant_id)
                ->where('user_type', 'student')
                ->orderBy('id')
                ->limit(25)
                ->pluck('id')
                ->each(function ($userId) use (&$users): void {
                    $users[(int) $userId] = null;
                });
        }

        foreach ($users as $userId => $classId) {
            ExamEnrollment::query()->updateOrCreate([
                'tenant_id' => $course->tenant_id,
                'exam_id' => $exam->id,
                'user_id' => (int) $userId,
            ], [
                'course_id' => $course->id,
                'class_id' => $classId,
                'status' => 'available',
                'assigned_by' => $course->owner_id,
                'available_from' => now()->subDay(),
                'available_until' => now()->addMonths(6),
                'metadata' => [
                    'meaningful_lesson_seeded' => true,
                    'source' => 'course_quiz_auto_enrollment',
                    'component_id' => $exam->component_id,
                ],
            ]);
        }
    }

    private function ensureQuestions(Course $course, CourseSection $unit, QuestionBank $bank)
    {
        return collect(range(1, 5))->map(function (int $i) use ($course, $unit, $bank): Question {
            $question = Question::query()->updateOrCreate([
                'tenant_id' => $course->tenant_id,
                'code' => 'ML-Q-'.$unit->id.'-'.$i,
            ], [
                'question_bank_id' => $bank->id,
                'question_type' => 'single_choice',
                'title' => 'Câu '.$i.' - '.$unit->title,
                'stem' => $this->questionStem($course, $unit, $i),
                'explanation' => 'Đáp án đúng thể hiện người học nắm được trọng tâm và có thể áp dụng vào tình huống của bài.',
                'difficulty' => $i <= 2 ? 'easy' : ($i <= 4 ? 'medium' : 'hard'),
                'bloom_level' => ['remember', 'understand', 'apply', 'analyze', 'evaluate'][$i - 1],
                'default_score' => 2,
                'penalty_score' => 0,
                'time_limit_seconds' => 120,
                'status' => 'approved',
                'owner_id' => $course->owner_id,
                'approved_by' => $course->owner_id,
                'approved_at' => now(),
                'metadata' => ['meaningful_lesson_seeded' => true, 'unit_id' => $unit->id, 'clo' => $this->clos($course), 'plo' => $this->plos($course)],
            ]);

            foreach (['A', 'B', 'C', 'D'] as $index => $key) {
                QuestionOption::query()->updateOrCreate([
                    'tenant_id' => $course->tenant_id,
                    'question_id' => $question->id,
                    'option_key' => $key,
                ], [
                    'content' => $this->optionText($course, $unit, $i, $index),
                    'is_correct' => $index === 0,
                    'score_weight' => $index === 0 ? 1 : 0,
                    'feedback' => $index === 0 ? 'Chính xác, phương án này bám sát mục tiêu bài học.' : 'Chưa đúng, cần xem lại ví dụ và tiêu chí trong bài.',
                    'sort_order' => $index + 1,
                    'metadata' => ['meaningful_lesson_seeded' => true],
                ]);
            }

            return $question;
        });
    }

    private function ensureRubric(Course $course): Rubric
    {
        $rubric = Rubric::query()->firstOrCreate([
            'tenant_id' => $course->tenant_id,
            'course_id' => $course->id,
            'title' => 'Rubric thực hành - '.$course->code,
        ], [
            'description' => 'Rubric đánh giá sản phẩm thực hành theo chuẩn đầu ra của khóa.',
            'max_score' => 100,
            'status' => 'published',
            'created_by' => $course->owner_id,
        ]);

        $criteria = [
            ['Hiểu yêu cầu', 25, 'Xác định đúng vấn đề, dữ liệu đầu vào và tiêu chí hoàn thành.'],
            ['Vận dụng kiến thức', 45, 'Áp dụng đúng quy trình, công cụ hoặc ngôn ngữ chuyên môn của bài.'],
            ['Trình bày và phản hồi', 30, 'Sản phẩm rõ ràng, có minh chứng và tự đánh giá cải tiến.'],
        ];

        foreach ($criteria as $index => [$title, $score, $description]) {
            $criterion = RubricCriterion::query()->updateOrCreate([
                'tenant_id' => $course->tenant_id,
                'rubric_id' => $rubric->id,
                'sort_order' => $index + 1,
            ], [
                'title' => $title,
                'description' => $description,
                'max_score' => $score,
                'metadata' => ['meaningful_lesson_seeded' => true],
            ]);

            foreach ([['Tốt', $score], ['Đạt', round($score * 0.7, 2)], ['Cần bổ sung', round($score * 0.4, 2)]] as $levelIndex => [$level, $levelScore]) {
                RubricLevel::query()->updateOrCreate([
                    'tenant_id' => $course->tenant_id,
                    'criterion_id' => $criterion->id,
                    'sort_order' => $levelIndex + 1,
                ], [
                    'level_name' => $level,
                    'description' => $this->rubricLevelDescription($level, $title),
                    'score' => $levelScore,
                ]);
            }
        }

        return $rubric;
    }

    private function ensureAssignment(Course $course, CourseSection $unit, CourseComponent $component, Rubric $rubric): Assignment
    {
        return Assignment::query()->updateOrCreate([
            'tenant_id' => $course->tenant_id,
            'component_id' => $component->id,
        ], [
            'course_id' => $course->id,
            'title' => 'Bài tập vận dụng - '.$unit->title,
            'description' => 'Người học hoàn thành sản phẩm ngắn chứng minh đã đạt mục tiêu của bài "'.$unit->title.'".',
            'assignment_type' => 'individual',
            'submission_type' => 'mixed',
            'status' => $this->statusFor($course),
            'open_at' => now()->subDay(),
            'due_at' => now()->addDays(14),
            'allow_late' => true,
            'late_penalty_config' => ['type' => 'percent_per_day', 'value' => 5, 'max_percent' => 30],
            'max_score' => 100,
            'pass_score' => 60,
            'max_submissions' => 2,
            'rubric_id' => $rubric->id,
            'settings' => [
                'meaningful_lesson_seeded' => true,
                'instructions' => [
                    'Đọc mục tiêu và tài liệu của bài.',
                    'Hoàn thành sản phẩm theo tình huống thực tế được giao.',
                    'Nộp file hoặc đường dẫn minh chứng, kèm tự đánh giá theo rubric.',
                ],
                'constraints' => ['max_files' => 3, 'allowed_types' => ['pdf', 'docx', 'pptx', 'zip'], 'requires_rubric_self_check' => true],
                'clo' => $this->clos($course),
                'plo' => $this->plos($course),
            ],
            'created_by' => $course->owner_id,
        ]);
    }

    private function baseComponentConfig(Course $course, CourseSection $unit, string $type): array
    {
        return [
            'meaningful_lesson_seeded' => true,
            'instructions' => $this->componentInstructions($type, $unit->title),
            'estimated_minutes' => match ($type) {
                'video' => 18,
                'quiz' => 15,
                'assignment' => 45,
                default => 12,
            },
            'completion_rule' => match ($type) {
                'quiz' => ['type' => 'score', 'score' => 7],
                'assignment' => ['type' => 'submission'],
                'video' => ['type' => 'watch_percent', 'percent' => 80],
                default => ['type' => 'view'],
            },
            'learning_metadata' => [
                'cap_do' => $course->level,
                'mon_hoc' => $course->title,
                'khoa' => $course->academic_unit_id,
                'CLO' => $this->clos($course),
                'PLO' => $this->plos($course),
                'thoi_luong_phut' => 45,
            ],
            'constraints' => [
                'required' => true,
                'available_after_previous' => true,
                'must_complete_for_certificate' => true,
            ],
        ];
    }

    private function learningSettings(Course $course, ?string $scope, int $minutes): array
    {
        return [
            'meaningful_lesson_seeded' => true,
            'scope' => $scope,
            'estimated_minutes' => $minutes,
            'CLO' => $this->clos($course),
            'PLO' => $this->plos($course),
            'constraints' => [
                'completion_required' => true,
                'sequential_release' => false,
                'minimum_progress_percent' => 80,
            ],
        ];
    }

    private function sectionTitles(Course $course): array
    {
        return [
            'Chương 1: Nền tảng '.$this->domainName($course),
            'Chương 2: Thực hành '.$this->domainName($course),
            'Chương 3: Đánh giá và vận dụng '.$this->domainName($course),
        ];
    }

    private function sectionDescription(Course $course, int $index): string
    {
        return [
            'Thiết lập kiến thức nền, thuật ngữ và tiêu chuẩn cần đạt trong khóa '.$course->title.'.',
            'Thực hành theo tình huống nghề nghiệp, bài tập có minh chứng và phản hồi.',
            'Tổng hợp năng lực, kiểm tra đạt chuẩn và chuẩn bị sản phẩm cuối khóa.',
        ][$index] ?? 'Nội dung học tập có mục tiêu, hoạt động và đánh giá rõ ràng.';
    }

    private function unitTitle(Course $course, int $position): string
    {
        $topics = $this->topicPool($course);
        return 'Bài '.$position.': '.$topics[($position - 1) % count($topics)];
    }

    private function unitDescription(Course $course, int $position): string
    {
        return 'Bài học giúp người học nắm trọng tâm "'.$this->topicPool($course)[($position - 1) % count($this->topicPool($course))].'", thực hành có hướng dẫn và hoàn thành đánh giá ngắn theo CLO/PLO.';
    }

    private function unitOutcomes(Course $course, string $unitTitle): array
    {
        $topic = trim(Str::after($unitTitle, ':')) ?: $unitTitle;

        return [
            'Giải thích được trọng tâm '.$topic.' trong bối cảnh '.$course->title.'.',
            'Thực hành được nhiệm vụ có minh chứng và tự đánh giá theo tiêu chí.',
            'Hoàn thành hoạt động bắt buộc để mở bài tiếp theo và tính tiến độ hoàn thành.',
        ];
    }

    private function componentTitle(string $type, string $unitTitle): string
    {
        return match ($type) {
            'video' => 'Video bài giảng - '.$unitTitle,
            'pdf', 'file' => 'Tài liệu đọc - '.$unitTitle,
            'quiz' => 'Quiz kiểm tra - '.$unitTitle,
            'assignment' => 'Bài tập vận dụng - '.$unitTitle,
            'scorm' => 'Gói học tương tác - '.$unitTitle,
            default => 'Nội dung trọng tâm - '.$unitTitle,
        };
    }

    private function componentInstructions(string $type, string $unitTitle): array
    {
        return match ($type) {
            'video' => ['Xem video và ghi lại khái niệm chính.', 'Hoàn thành tối thiểu 80% thời lượng để được tính đạt.'],
            'quiz' => ['Làm quiz sau khi học xong tài liệu.', 'Cần đạt tối thiểu 70% điểm.'],
            'assignment' => ['Hoàn thành sản phẩm vận dụng theo tình huống.', 'Tự đối chiếu rubric trước khi nộp.'],
            default => ['Đọc nội dung của '.$unitTitle.'.', 'Ghi chú thuật ngữ, ví dụ và tiêu chí cần đạt.'],
        };
    }

    private function topicPool(Course $course): array
    {
        $text = Str::lower($course->title.' '.$course->code.' '.$course->level.' '.$course->language);

        if (Str::contains($text, ['hsk', 'hoa', 'trung'])) {
            return ['Từ vựng trọng điểm HSK', 'Ngữ pháp câu thường dùng', 'Nghe hiểu hội thoại ngắn', 'Đọc hiểu đoạn văn', 'Viết câu theo ngữ cảnh', 'Giao tiếp theo chủ đề'];
        }

        if (Str::contains($text, ['hàn', 'korean', 'topik'])) {
            return ['Bảng âm và phát âm chuẩn', 'Mẫu câu giao tiếp cơ bản', 'Từ vựng theo tình huống', 'Nghe hiểu hội thoại', 'Đọc hiểu thông báo', 'Viết đoạn ngắn'];
        }

        if (Str::contains($text, ['anh', 'english', 'ielts', 'toeic'])) {
            return ['Vocabulary theo chủ đề', 'Grammar in use', 'Listening for main ideas', 'Reading comprehension', 'Speaking task practice', 'Writing task practice'];
        }

        if (Str::contains($text, ['cntt', 'công nghệ', 'lập trình', 'mạng', 'data'])) {
            return ['Phân tích yêu cầu kỹ thuật', 'Thiết kế giải pháp', 'Thực hành công cụ', 'Kiểm thử và xử lý lỗi', 'Bảo mật và vận hành', 'Báo cáo sản phẩm'];
        }

        if (Str::contains($text, ['du lịch', 'tour', 'lữ hành', 'khách sạn'])) {
            return ['Thiết kế hành trình', 'Giao tiếp với khách hàng', 'Nghiệp vụ điều phối', 'Xử lý tình huống dịch vụ', 'Tính chi phí tour', 'Đánh giá trải nghiệm'];
        }

        if (Str::contains($text, ['marketing', 'bán hàng', 'truyền thông'])) {
            return ['Phân tích khách hàng mục tiêu', 'Thông điệp và định vị', 'Kênh truyền thông', 'Nội dung chiến dịch', 'Đo lường hiệu quả', 'Tối ưu chuyển đổi'];
        }

        return ['Khái niệm nền tảng', 'Quy trình thực hiện', 'Tình huống minh họa', 'Thực hành có hướng dẫn', 'Đánh giá kết quả', 'Ứng dụng thực tế'];
    }

    private function domainName(Course $course): string
    {
        return $this->topicPool($course)[0];
    }

    private function questionStem(Course $course, CourseSection $unit, int $index): string
    {
        $verbs = ['nhận diện', 'giải thích', 'áp dụng', 'phân tích', 'đánh giá'];
        return 'Trong bài "'.$unit->title.'", lựa chọn nào thể hiện đúng năng lực '.$verbs[$index - 1].' theo mục tiêu của khóa '.$course->title.'?';
    }

    private function optionText(Course $course, CourseSection $unit, int $question, int $index): string
    {
        $correct = 'Liên hệ khái niệm chính với tình huống thực hành và tiêu chí đánh giá của bài.';
        $distractors = [
            'Chỉ ghi nhớ thuật ngữ mà chưa áp dụng vào tình huống.',
            'Bỏ qua yêu cầu đầu ra và chỉ làm theo cảm tính.',
            'Nộp sản phẩm không có minh chứng hoặc phản hồi tự đánh giá.',
        ];

        return $index === 0 ? $correct : $distractors[$index - 1];
    }

    private function rubricLevelDescription(string $level, string $criterion): string
    {
        return match ($level) {
            'Tốt' => 'Đáp ứng đầy đủ tiêu chí '.$criterion.', có minh chứng rõ và ít lỗi.',
            'Đạt' => 'Đáp ứng phần lớn tiêu chí '.$criterion.', còn một vài điểm cần chỉnh.',
            default => 'Chưa đáp ứng đủ tiêu chí '.$criterion.', cần bổ sung theo phản hồi.',
        };
    }

    private function componentLooksGeneric(?string $title): bool
    {
        if (! $title) {
            return true;
        }

        return preg_match('/^(Text|Video|Quiz|Assignment|PDF|Tài liệu|Bài tập|Placement Test|Nội dung)\b/iu', $title) === 1;
    }

    private function statusFor(Course $course): string
    {
        return $course->status === 'published' ? 'published' : 'draft';
    }

    private function clos(Course $course): array
    {
        return ['CLO1-'.$course->code, 'CLO2-'.$course->code, 'CLO3-'.$course->code];
    }

    private function plos(Course $course): array
    {
        return ['PLO1', 'PLO2', 'PLO3'];
    }
}
