<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\DataIntegrityCheck;
use App\Models\DataIntegrityIssue;
use App\Models\Tenant;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class DataIntegrityService
{
    private const MODULES = [
        'course',
        'repository',
        'question_bank',
        'exam',
        'assignment',
        'gradebook',
        'attendance',
        'learning_path',
        'sis',
        'certificate',
        'user_permission',
    ];

    public function runAllChecks(?int $tenantId = null): array
    {
        $tenantIds = $tenantId ? [$tenantId] : Tenant::query()->pluck('id')->all();
        $results = [];

        foreach ($tenantIds as $id) {
            foreach (self::MODULES as $module) {
                $results[$id][$module] = $this->runModuleChecks($module, (int) $id);
            }
        }

        return [
            'summary' => $this->summary($tenantId),
            'results' => $results,
        ];
    }

    public function runModuleChecks(string $module, int $tenantId): array
    {
        return match ($module) {
            'course' => $this->detectMissingRequiredRelations($tenantId),
            'repository' => $this->detectRepositoryIssues($tenantId),
            'question_bank' => $this->detectQuestionBankIssues($tenantId),
            'exam' => $this->detectExamIssues($tenantId),
            'assignment' => $this->detectAssignmentIssues($tenantId),
            'gradebook' => $this->detectInvalidGradeSources($tenantId),
            'attendance' => $this->detectAttendanceIssues($tenantId),
            'learning_path' => $this->detectLearningPathIssues($tenantId),
            'sis' => array_merge($this->detectDuplicateCodes($tenantId), $this->detectSisIssues($tenantId)),
            'certificate' => $this->detectInvalidCertificateIssues($tenantId),
            'user_permission' => $this->detectUserPermissionIssues($tenantId),
            default => throw new \InvalidArgumentException("Module integrity không hợp lệ: {$module}"),
        };
    }

    public function detectOrphanRecords(?int $tenantId = null): array
    {
        $tenantIds = $tenantId ? [$tenantId] : Tenant::query()->pluck('id')->all();
        $results = [];

        foreach ($tenantIds as $id) {
            $results[$id] = array_merge(
                $this->detectMissingRequiredRelations((int) $id),
                $this->detectExamIssues((int) $id),
                $this->detectAssignmentIssues((int) $id),
                $this->detectAttendanceIssues((int) $id)
            );
        }

        return $results;
    }

    public function detectBrokenMappings(?int $tenantId = null): array
    {
        $tenantIds = $tenantId ? [$tenantId] : Tenant::query()->pluck('id')->all();
        $results = [];

        foreach ($tenantIds as $id) {
            $results[$id] = array_merge(
                $this->detectInvalidGradeSources((int) $id),
                $this->detectSisIssues((int) $id),
                $this->detectUserPermissionIssues((int) $id)
            );
        }

        return $results;
    }

    public function detectInvalidStatusTransitions(?int $tenantId = null): array
    {
        return [
            'allowed_transitions' => app(StatusTransitionValidator::class)->allowedMap(),
            'tenant_id' => $tenantId,
        ];
    }

    public function detectMissingRequiredRelations(int $tenantId): array
    {
        $results = [];

        $results[] = $this->recordCheck($tenantId, 'course', 'course_without_section', 'Course không có section', 'Khóa học phải có ít nhất một section.', 'error',
            $this->queryIssues('courses', 'course', $tenantId, function (Builder $query) {
                $query->leftJoin('course_sections', function ($join) {
                    $join->on('course_sections.course_id', '=', 'courses.id')
                        ->where('course_sections.type', '=', 'section');
                })->whereNull('course_sections.id')
                    ->select('courses.id', 'courses.title');
            }, 'Course chưa có section: ')
        );

        $results[] = $this->recordCheck($tenantId, 'course', 'section_without_unit', 'Section không có unit', 'Section/chương phải có ít nhất một unit.', 'error',
            $this->queryIssues('course_sections', 'course_section', $tenantId, function (Builder $query) {
                $query->leftJoin('course_sections as units', function ($join) {
                    $join->on('units.parent_id', '=', 'course_sections.id')
                        ->where('units.type', '=', 'unit');
                })->where('course_sections.type', 'section')
                    ->whereNull('units.id')
                    ->select('course_sections.id', 'course_sections.title');
            }, 'Section chưa có unit: ')
        );

        $results[] = $this->recordCheck($tenantId, 'course', 'unit_without_component', 'Unit không có component', 'Unit/bài học phải có ít nhất một component.', 'error',
            $this->queryIssues('course_sections', 'course_section', $tenantId, function (Builder $query) {
                $query->leftJoin('course_components', 'course_components.section_id', '=', 'course_sections.id')
                    ->where('course_sections.type', 'unit')
                    ->whereNull('course_components.id')
                    ->select('course_sections.id', 'course_sections.title');
            }, 'Unit chưa có component: ')
        );

        $results[] = $this->recordCheck($tenantId, 'course', 'component_missing_repository_item', 'Component thiếu repository item', 'Video/PDF/File/SCORM component phải liên kết repository item.', 'error',
            $this->componentMissingContentIssues($tenantId)
        );

        $results[] = $this->recordCheck($tenantId, 'course', 'component_video_without_video_asset', 'Component video không có video asset', 'Component video phải có video_assets tương ứng.', 'error',
            $this->queryIssues('course_components', 'course_component', $tenantId, function (Builder $query) {
                $query->leftJoin('video_assets', 'video_assets.component_id', '=', 'course_components.id')
                    ->where('course_components.component_type', 'video')
                    ->whereNull('video_assets.id')
                    ->select('course_components.id', 'course_components.title');
            }, 'Video component chưa có video asset: ')
        );

        $results[] = $this->recordCheck($tenantId, 'course', 'component_quiz_without_exam', 'Component quiz không có exam', 'Component quiz phải liên kết exam.', 'error',
            $this->queryIssues('course_components', 'course_component', $tenantId, function (Builder $query) {
                $query->leftJoin('exams', 'exams.component_id', '=', 'course_components.id')
                    ->where('course_components.component_type', 'quiz')
                    ->whereNull('exams.id')
                    ->select('course_components.id', 'course_components.title');
            }, 'Quiz component chưa có exam: ')
        );

        $results[] = $this->recordCheck($tenantId, 'course', 'component_assignment_without_assignment', 'Component assignment không có assignment', 'Component assignment phải liên kết assignment.', 'error',
            $this->queryIssues('course_components', 'course_component', $tenantId, function (Builder $query) {
                $query->leftJoin('assignments', 'assignments.component_id', '=', 'course_components.id')
                    ->where('course_components.component_type', 'assignment')
                    ->whereNull('assignments.id')
                    ->select('course_components.id', 'course_components.title');
            }, 'Assignment component chưa có assignment: ')
        );

        $results[] = $this->recordCheck($tenantId, 'course', 'component_required_without_completion_rule', 'Component required thiếu completion rule', 'Component required phải có config.completion_rule.', 'warning',
            $this->requiredComponentMissingCompletionRule($tenantId)
        );

        $publishedIssues = [];
        if ($this->hasTable('courses')) {
            Course::query()->where('tenant_id', $tenantId)->where('status', 'published')->each(function (Course $course) use (&$publishedIssues): void {
                $errors = app(CourseStudioService::class)->validateCourseStructure($course);
                if ($errors !== []) {
                    $publishedIssues[] = $this->issue('course', $course->id, 'Course published nhưng checklist lỗi: '.$course->title, 'critical', false, ['errors' => $errors]);
                }
            });
        }
        $results[] = $this->recordCheck($tenantId, 'course', 'published_course_invalid_checklist', 'Course published nhưng checklist lỗi', 'Published course vẫn phải pass publish checklist.', 'critical', $publishedIssues);

        return $results;
    }

    public function detectRepositoryIssues(int $tenantId): array
    {
        $results = [];

        $results[] = $this->recordCheck($tenantId, 'repository', 'repository_item_missing_file_path', 'Repository item không có file path', 'File item phải có storage_path.', 'error',
            $this->queryIssues('content_repository_items', 'content_repository_item', $tenantId, function (Builder $query) {
                $query->where('item_type', '!=', 'folder')
                    ->where(function ($query) {
                        $query->whereNull('storage_path')->orWhere('storage_path', '');
                    })
                    ->select('id', 'title');
            }, 'Repository item thiếu storage_path: ')
        );

        $missingFiles = [];
        if ($this->hasTable('content_repository_items')) {
            DB::table('content_repository_items')
                ->where('tenant_id', $tenantId)
                ->where('item_type', '!=', 'folder')
                ->whereNotNull('storage_path')
                ->orderBy('id')
                ->select(['id', 'title', 'storage_path', 'metadata'])
                ->cursor()
                ->each(function ($item) use (&$missingFiles): void {
                    $metadata = json_decode((string) $item->metadata, true) ?: [];
                    if (($metadata['sample_repository_item'] ?? false) === true) {
                        return;
                    }
                    if (! Storage::disk(config('eralms.repository_disk', 'local'))->exists($item->storage_path)) {
                        $missingFiles[] = $this->issue('content_repository_item', $item->id, 'File path không tồn tại: '.$item->title, 'warning', false, ['storage_path' => $item->storage_path]);
                    }
                });
        }
        $results[] = $this->recordCheck($tenantId, 'repository', 'repository_file_path_missing_on_disk', 'File path không tồn tại', 'storage_path phải trỏ tới file tồn tại trên disk cấu hình.', 'warning', $missingFiles);

        $versionIssues = [];
        if ($this->hasTable('content_repository_items') && $this->hasTable('content_versions')) {
            DB::table('content_repository_items')
                ->where('tenant_id', $tenantId)
                ->where('item_type', '!=', 'folder')
                ->orderBy('id')
                ->select(['id', 'title', 'metadata'])
                ->cursor()
                ->each(function ($item) use (&$versionIssues): void {
                    $metadata = json_decode((string) $item->metadata, true) ?: [];
                    $currentVersion = $metadata['current_version'] ?? null;
                    $maxVersion = DB::table('content_versions')->where('content_item_id', $item->id)->max('version');
                    if ($currentVersion !== null && $maxVersion !== null && (string) $currentVersion !== (string) $maxVersion) {
                        $versionIssues[] = $this->issue('content_repository_item', $item->id, 'Item chưa cập nhật current_version mới nhất: '.$item->title, 'warning', false, ['current_version' => $currentVersion, 'max_version' => $maxVersion]);
                    }
                });
        }
        $results[] = $this->recordCheck($tenantId, 'repository', 'repository_item_outdated_version', 'Version mới hơn nhưng item chưa cập nhật', 'metadata.current_version phải khớp version mới nhất.', 'warning', $versionIssues);

        $results[] = $this->recordCheck($tenantId, 'repository', 'repository_duplicate_checksum', 'File trùng checksum', 'Checksum trùng cần được deduplicate hoặc xác nhận.', 'warning',
            $this->duplicateValueIssues('content_repository_items', $tenantId, 'checksum', 'content_repository_item', 'Checksum trùng: ')
        );

        return $results;
    }

    public function detectQuestionBankIssues(int $tenantId): array
    {
        $results = [];

        $results[] = $this->recordCheck($tenantId, 'question_bank', 'question_bank_without_questions', 'Question bank không có câu hỏi', 'Question bank active/published cần có câu hỏi.', 'error',
            $this->queryIssues('question_banks', 'question_bank', $tenantId, function (Builder $query) {
                $query->leftJoin('questions', 'questions.question_bank_id', '=', 'question_banks.id')
                    ->whereNull('questions.id')
                    ->select('question_banks.id', 'question_banks.name as title');
            }, 'Question bank chưa có câu hỏi: ')
        );

        $results[] = $this->recordCheck($tenantId, 'question_bank', 'mcq_without_correct_answer', 'MCQ không có đáp án đúng', 'MCQ phải có ít nhất một option đúng.', 'error',
            $this->choiceQuestionIssues($tenantId, ['mcq', 'multiple_choice'], 'zero', 'MCQ chưa có đáp án đúng: ')
        );

        $results[] = $this->recordCheck($tenantId, 'question_bank', 'single_choice_multiple_correct_answers', 'Single choice có nhiều đáp án đúng', 'Single choice chỉ được có một option đúng.', 'error',
            $this->choiceQuestionIssues($tenantId, ['single_choice', 'single'], 'many', 'Single choice có nhiều đáp án đúng: ')
        );

        $results[] = $this->recordCheck($tenantId, 'question_bank', 'question_published_not_approved', 'Question published chưa approved', 'Published question phải có approved_at hoặc approved_by.', 'error',
            $this->queryIssues('questions', 'question', $tenantId, function (Builder $query) {
                $query->where('status', 'published')
                    ->whereNull('approved_at')
                    ->whereNull('approved_by')
                    ->select('id', 'title');
            }, 'Question published chưa approved: ')
        );

        $blueprintIssues = [];
        if ($this->hasTable('exam_blueprints')) {
            DB::table('exam_blueprints')->where('tenant_id', $tenantId)->orderBy('id')->cursor()->each(function ($blueprint) use (&$blueprintIssues): void {
                $available = DB::table('questions')
                    ->where('tenant_id', $blueprint->tenant_id)
                    ->when($blueprint->question_bank_id, fn ($query) => $query->where('question_bank_id', $blueprint->question_bank_id))
                    ->count();
                if ((int) $blueprint->total_questions > $available) {
                    $blueprintIssues[] = $this->issue('exam_blueprint', $blueprint->id, 'Blueprint yêu cầu nhiều câu hơn số câu có sẵn: '.$blueprint->name, 'error', false, ['required' => $blueprint->total_questions, 'available' => $available]);
                }
            });
        }
        $results[] = $this->recordCheck($tenantId, 'question_bank', 'blueprint_requires_unavailable_questions', 'Blueprint yêu cầu quá số câu có sẵn', 'Blueprint không được vượt quá nguồn câu hỏi khả dụng.', 'error', $blueprintIssues);

        return $results;
    }

    public function detectExamIssues(int $tenantId): array
    {
        $results = [];

        $results[] = $this->recordCheck($tenantId, 'exam', 'exam_published_without_questions', 'Exam published không có câu hỏi', 'Exam published phải có exam_questions.', 'critical',
            $this->queryIssues('exams', 'exam', $tenantId, function (Builder $query) {
                $query->leftJoin('exam_questions', 'exam_questions.exam_id', '=', 'exams.id')
                    ->whereIn('exams.status', ['published', 'open'])
                    ->whereNull('exam_questions.id')
                    ->select('exams.id', 'exams.title');
            }, 'Exam published/open chưa có câu hỏi: ')
        );

        $results[] = $this->recordCheck($tenantId, 'exam', 'exam_without_enrollment', 'Exam không có enrollment', 'Exam đã publish/open cần exam_enrollments.', 'warning',
            $this->queryIssues('exams', 'exam', $tenantId, function (Builder $query) {
                $query->leftJoin('exam_enrollments', 'exam_enrollments.exam_id', '=', 'exams.id')
                    ->whereIn('exams.status', ['published', 'open'])
                    ->whereNull('exam_enrollments.id')
                    ->select('exams.id', 'exams.title');
            }, 'Exam chưa có enrollment: ')
        );

        $results[] = $this->recordCheck($tenantId, 'exam', 'attempt_without_question_snapshot', 'Attempt không có snapshot câu hỏi', 'Attempt phải có exam_attempt_questions.', 'error',
            $this->queryIssues('exam_attempts', 'exam_attempt', $tenantId, function (Builder $query) {
                $query->leftJoin('exam_attempt_questions', 'exam_attempt_questions.attempt_id', '=', 'exam_attempts.id')
                    ->whereIn('exam_attempts.status', ['in_progress', 'submitted', 'auto_submitted', 'graded', 'published'])
                    ->whereNull('exam_attempt_questions.id')
                    ->select('exam_attempts.id', DB::raw("exam_attempts.session_uuid as title"));
            }, 'Attempt chưa có snapshot câu hỏi: ')
        );

        $results[] = $this->recordCheck($tenantId, 'exam', 'submitted_attempt_without_answer', 'Attempt submitted không có answer', 'Submitted attempt phải có exam_answers.', 'error',
            $this->queryIssues('exam_attempts', 'exam_attempt', $tenantId, function (Builder $query) {
                $query->leftJoin('exam_answers', 'exam_answers.attempt_id', '=', 'exam_attempts.id')
                    ->whereIn('exam_attempts.status', ['submitted', 'auto_submitted', 'graded', 'published'])
                    ->whereNull('exam_answers.id')
                    ->select('exam_attempts.id', DB::raw("exam_attempts.session_uuid as title"));
            }, 'Submitted attempt chưa có answer: ')
        );

        $results[] = $this->recordCheck($tenantId, 'exam', 'result_without_attempt', 'Result không map attempt', 'Exam result phải trỏ tới attempt tồn tại.', 'error',
            $this->queryIssues('exam_results', 'exam_result', $tenantId, function (Builder $query) {
                $query->leftJoin('exam_attempts', 'exam_attempts.id', '=', 'exam_results.attempt_id')
                    ->whereNull('exam_attempts.id')
                    ->select('exam_results.id', DB::raw("exam_results.id as title"));
            }, 'Exam result không có attempt: ')
        );

        $results[] = $this->recordCheck($tenantId, 'exam', 'flagged_attempt_unresolved', 'Flagged attempt chưa xử lý', 'Attempt suspicious_score cao phải có metadata.reviewed_at.', 'warning',
            $this->flaggedAttemptIssues($tenantId)
        );

        return $results;
    }

    public function detectAssignmentIssues(int $tenantId): array
    {
        $results = [];

        $results[] = $this->recordCheck($tenantId, 'assignment', 'published_assignment_without_max_score', 'Assignment published không có max_score', 'Assignment published phải có max_score > 0.', 'error',
            $this->queryIssues('assignments', 'assignment', $tenantId, function (Builder $query) {
                $query->where('status', 'published')
                    ->where(function ($query) {
                        $query->whereNull('max_score')->orWhere('max_score', '<=', 0);
                    })
                    ->select('id', 'title');
            }, 'Assignment published thiếu max_score: ')
        );

        $results[] = $this->recordCheck($tenantId, 'assignment', 'submission_without_assignment', 'Submission không có assignment', 'Submission phải trỏ tới assignment tồn tại.', 'error',
            $this->queryIssues('assignment_submissions', 'assignment_submission', $tenantId, function (Builder $query) {
                $query->leftJoin('assignments', 'assignments.id', '=', 'assignment_submissions.assignment_id')
                    ->whereNull('assignments.id')
                    ->select('assignment_submissions.id', DB::raw("assignment_submissions.id as title"));
            }, 'Submission không có assignment: ')
        );

        $results[] = $this->recordCheck($tenantId, 'assignment', 'graded_submission_without_grade', 'Submission graded không có grade', 'Submission graded phải có assignment_grade.', 'error',
            $this->queryIssues('assignment_submissions', 'assignment_submission', $tenantId, function (Builder $query) {
                $query->leftJoin('assignment_grades', 'assignment_grades.submission_id', '=', 'assignment_submissions.id')
                    ->whereIn('assignment_submissions.status', ['graded', 'approved'])
                    ->whereNull('assignment_grades.id')
                    ->select('assignment_submissions.id', DB::raw("assignment_submissions.id as title"));
            }, 'Submission graded chưa có grade: ')
        );

        $results[] = $this->recordCheck($tenantId, 'assignment', 'assignment_grade_exceeds_max_score', 'Grade vượt max_score', 'Assignment grade không được vượt max_score.', 'critical',
            $this->queryIssues('assignment_grades', 'assignment_grade', $tenantId, function (Builder $query) {
                $query->whereColumn('score', '>', 'max_score')
                    ->select('id', DB::raw("id as title"));
            }, 'Assignment grade vượt max_score: ')
        );

        return $results;
    }

    public function detectInvalidGradeSources(int $tenantId): array
    {
        $results = [];

        $results[] = $this->recordCheck($tenantId, 'gradebook', 'active_gradebook_without_items', 'Gradebook active không có grade item', 'Gradebook active phải có grade_items.', 'error',
            $this->queryIssues('gradebooks', 'gradebook', $tenantId, function (Builder $query) {
                $query->leftJoin('grade_items', 'grade_items.gradebook_id', '=', 'gradebooks.id')
                    ->whereIn('gradebooks.status', ['active', 'pending_approval', 'approved', 'locked', 'synced_to_sis'])
                    ->whereNull('grade_items.id')
                    ->select('gradebooks.id', 'gradebooks.title');
            }, 'Gradebook active chưa có grade item: ')
        );

        $results[] = $this->recordCheck($tenantId, 'gradebook', 'grade_item_invalid_source', 'Grade item source_id không tồn tại', 'Grade item phải map đúng source quiz/assignment/attendance/manual.', 'error',
            $this->gradeItemSourceIssues($tenantId)
        );

        $results[] = $this->recordCheck($tenantId, 'gradebook', 'gradebook_weight_not_100', 'Tổng weight khác 100%', 'Tổng weight grade_items trong gradebook phải bằng 100 nếu dùng weight.', 'warning',
            $this->gradebookWeightIssues($tenantId)
        );

        $results[] = $this->recordCheck($tenantId, 'gradebook', 'learner_grade_exceeds_item_max_score', 'Learner grade vượt max_score', 'Learner grade không được vượt grade item max_score.', 'critical',
            $this->queryIssues('learner_grades', 'learner_grade', $tenantId, function (Builder $query) {
                $query->join('grade_items', 'grade_items.id', '=', 'learner_grades.grade_item_id')
                    ->where(function ($query) {
                        $query->whereColumn('learner_grades.final_score', '>', 'grade_items.max_score')
                            ->orWhereColumn('learner_grades.raw_score', '>', 'grade_items.max_score');
                    })
                    ->select('learner_grades.id', DB::raw("learner_grades.id as title"));
            }, 'Learner grade vượt max_score: ')
        );

        $results[] = $this->recordCheck($tenantId, 'gradebook', 'locked_grade_changed_after_lock', 'Grade locked nhưng vẫn có thay đổi sau locked_at', 'Không được sửa điểm sau khi gradebook locked.', 'critical',
            $this->queryIssues('grade_change_logs', 'grade_change_log', $tenantId, function (Builder $query) {
                $query->join('gradebooks', 'gradebooks.id', '=', 'grade_change_logs.gradebook_id')
                    ->whereNotNull('gradebooks.locked_at')
                    ->whereColumn('grade_change_logs.created_at', '>', 'gradebooks.locked_at')
                    ->select('grade_change_logs.id', DB::raw("grade_change_logs.id as title"));
            }, 'Grade change sau locked_at: ')
        );

        $results[] = $this->recordCheck($tenantId, 'gradebook', 'grade_sync_sis_without_approval_lock', 'Grade sync SIS khi chưa approved/locked', 'Chỉ gradebook approved/locked mới được sync SIS.', 'error',
            $this->queryIssues('grade_approval_batches', 'grade_approval_batch', $tenantId, function (Builder $query) {
                $query->join('gradebooks', 'gradebooks.id', '=', 'grade_approval_batches.gradebook_id')
                    ->where('grade_approval_batches.sync_status', 'synced')
                    ->whereNotIn('gradebooks.status', ['approved', 'locked', 'synced_to_sis'])
                    ->select('grade_approval_batches.id', 'grade_approval_batches.title');
            }, 'Grade sync SIS chưa approved/locked: ')
        );

        return $results;
    }

    public function detectAttendanceIssues(int $tenantId): array
    {
        $results = [];

        $results[] = $this->recordCheck($tenantId, 'attendance', 'attendance_record_without_session', 'Attendance record không có session', 'Attendance record phải trỏ tới attendance_session.', 'error',
            $this->queryIssues('attendance_records', 'attendance_record', $tenantId, function (Builder $query) {
                $query->leftJoin('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
                    ->whereNull('attendance_sessions.id')
                    ->select('attendance_records.id', DB::raw("attendance_records.id as title"));
            }, 'Attendance record không có session: ')
        );

        $results[] = $this->recordCheck($tenantId, 'attendance', 'attendance_session_open_overdue', 'Attendance session open quá hạn chưa close', 'Open session quá close_at phải được close.', 'warning',
            $this->queryIssues('attendance_sessions', 'attendance_session', $tenantId, function (Builder $query) {
                $query->where('status', 'open')
                    ->where('close_at', '<', now())
                    ->select('id', 'title');
            }, 'Attendance session open quá hạn: ')
        );

        $results[] = $this->recordCheck($tenantId, 'attendance', 'attendance_locked_changed_after_lock', 'Attendance locked nhưng vẫn sửa record', 'Không được sửa attendance record sau locked_at.', 'critical',
            $this->queryIssues('attendance_records', 'attendance_record', $tenantId, function (Builder $query) {
                $query->join('attendance_sessions', 'attendance_sessions.id', '=', 'attendance_records.attendance_session_id')
                    ->whereNotNull('attendance_sessions.locked_at')
                    ->whereColumn('attendance_records.updated_at', '>', 'attendance_sessions.locked_at')
                    ->select('attendance_records.id', DB::raw("attendance_records.id as title"));
            }, 'Attendance record sửa sau locked_at: ')
        );

        return $results;
    }

    public function detectLearningPathIssues(int $tenantId): array
    {
        $results = [];
        $issues = [];

        if ($this->hasTable('learning_path_rules')) {
            DB::table('learning_path_rules')->where('tenant_id', $tenantId)->orderBy('id')->cursor()->each(function ($rule) use (&$issues): void {
                $config = json_decode((string) ($rule->rule_config ?? $rule->config ?? '{}'), true) ?: [];
                $target = $config['target'] ?? null;
                if (is_array($target) && isset($target['type'], $target['id']) && ! $this->targetExists($target['type'], $target['id'])) {
                    $issues[] = $this->issue('learning_path_rule', $rule->id, 'Rule target không tồn tại.', 'error', false, ['target' => $target]);
                }
            });
        }

        $results[] = $this->recordCheck($tenantId, 'learning_path', 'learning_path_rule_target_missing', 'Rule target không tồn tại', 'Learning path rule target phải trỏ tới entity tồn tại.', 'error', $issues);

        return $results;
    }

    public function detectDuplicateCodes(int $tenantId): array
    {
        $results = [];

        foreach ([
            ['courses', 'course', 'code', 'duplicate_course_code', 'Course trùng code'],
            ['class_sections', 'class_section', 'code', 'duplicate_class_code', 'Class trùng code'],
            ['lms_users', 'lms_user', 'code', 'duplicate_user_code', 'User trùng code'],
            ['question_banks', 'question_bank', 'code', 'duplicate_question_bank_code', 'Question bank trùng code'],
            ['exams', 'exam', 'code', 'duplicate_exam_code', 'Exam trùng code'],
        ] as [$table, $entity, $column, $key, $title]) {
            $results[] = $this->recordCheck($tenantId, 'sis', $key, $title, "{$title} trong cùng tenant.", 'critical',
                $this->duplicateValueIssues($table, $tenantId, $column, $entity, "{$title}: ")
            );
        }

        return $results;
    }

    public function detectSisIssues(int $tenantId): array
    {
        $results = [];

        $results[] = $this->recordCheck($tenantId, 'sis', 'sis_duplicate_external_id', 'Mapping trùng external_id', 'SIS mapping không được trùng external_id trong cùng system/entity.', 'critical',
            $this->duplicateMappingIssues($tenantId)
        );

        $results[] = $this->recordCheck($tenantId, 'sis', 'sis_mapping_missing_local_id', 'Mapping thiếu local_id', 'SIS mapping phải có local_id.', 'error',
            $this->queryIssues('integration_mappings', 'integration_mapping', $tenantId, function (Builder $query) {
                $query->where(function ($query) {
                    $query->whereNull('local_id')->orWhere('local_id', '');
                })->select('id', DB::raw("external_id as title"));
            }, 'SIS mapping thiếu local_id: ')
        );

        $results[] = $this->recordCheck($tenantId, 'sis', 'sync_event_failed_too_many_times', 'Sync event failed quá 3 lần', 'Sync event failed quá 3 lần cần xử lý.', 'warning',
            $this->queryIssues('integration_events', 'integration_event', $tenantId, function (Builder $query) {
                $query->where('status', 'failed')->where('attempts', '>', 3)->select('id', 'event_key as title');
            }, 'Sync event failed quá 3 lần: ')
        );

        $results[] = $this->recordCheck($tenantId, 'sis', 'sis_removed_enrollment_still_active', 'Enrollment SIS removed nhưng LMS vẫn active', 'Enrollment bị SIS remove không được active trong LMS.', 'critical',
            $this->queryIssues('enrollments', 'enrollment', $tenantId, function (Builder $query) {
                $query->whereIn('status', ['active', 'enrolled'])
                    ->where(function ($query) {
                        $query->where('source', 'sis_removed')
                            ->orWhere('metadata->sis_status', 'removed');
                    })
                    ->select('id', DB::raw("sis_enrollment_id as title"));
            }, 'Enrollment SIS removed vẫn active: ')
        );

        return $results;
    }

    public function detectInvalidCertificateIssues(int $tenantId): array
    {
        $results = [];

        $invalidCompletion = [];
        if ($this->hasTable('certificate_issues')) {
            DB::table('certificate_issues')
                ->where('tenant_id', $tenantId)
                ->where('status', 'issued')
                ->whereNotNull('course_id')
                ->orderBy('id')
                ->cursor()
                ->each(function ($issue) use (&$invalidCompletion): void {
                    $completed = DB::table('learning_completions')
                        ->where('tenant_id', $issue->tenant_id)
                        ->where('user_id', $issue->user_id)
                        ->where('course_id', $issue->course_id)
                        ->where('completion_type', 'course')
                        ->where('status', 'completed')
                        ->exists();
                    if (! $completed) {
                        $invalidCompletion[] = $this->issue('certificate_issue', $issue->id, 'Certificate issued khi course chưa completed: '.$issue->issue_code, 'critical', false);
                    }
                });
        }
        $results[] = $this->recordCheck($tenantId, 'certificate', 'certificate_issued_without_completion', 'Certificate issued khi course chưa completed', 'Certificate course chỉ được cấp khi course completed.', 'critical', $invalidCompletion);

        $invalidGrade = [];
        if ($this->hasTable('certificate_issues')) {
            DB::table('certificate_issues')
                ->where('tenant_id', $tenantId)
                ->where('status', 'issued')
                ->whereNotNull('course_id')
                ->orderBy('id')
                ->cursor()
                ->each(function ($issue) use (&$invalidGrade): void {
                    $hasGrade = DB::table('grade_summaries')
                        ->join('gradebooks', 'gradebooks.id', '=', 'grade_summaries.gradebook_id')
                        ->where('gradebooks.course_id', $issue->course_id)
                        ->where('grade_summaries.tenant_id', $issue->tenant_id)
                        ->where('grade_summaries.user_id', $issue->user_id)
                        ->exists();
                    $passed = DB::table('grade_summaries')
                        ->join('gradebooks', 'gradebooks.id', '=', 'grade_summaries.gradebook_id')
                        ->where('gradebooks.course_id', $issue->course_id)
                        ->where('grade_summaries.tenant_id', $issue->tenant_id)
                        ->where('grade_summaries.user_id', $issue->user_id)
                        ->whereIn('grade_summaries.pass_status', ['passed', 'pass'])
                        ->exists();
                    if ($hasGrade && ! $passed) {
                        $invalidGrade[] = $this->issue('certificate_issue', $issue->id, 'Certificate issued khi grade chưa passed: '.$issue->issue_code, 'critical', false);
                    }
                });
        }
        $results[] = $this->recordCheck($tenantId, 'certificate', 'certificate_issued_without_passed_grade', 'Certificate issued khi grade chưa passed', 'Nếu có grade summary thì learner phải passed.', 'critical', $invalidGrade);

        $hashIssues = [];
        if ($this->hasTable('certificate_issues')) {
            DB::table('certificate_issues')->where('tenant_id', $tenantId)->where('status', 'issued')->orderBy('id')->cursor()->each(function ($issue) use (&$hashIssues): void {
                if (! $issue->verification_hash || strlen((string) $issue->verification_hash) < 32) {
                    $hashIssues[] = $this->issue('certificate_issue', $issue->id, 'Certificate verify hash sai: '.$issue->issue_code, 'error', false);
                }
            });
        }
        $results[] = $this->recordCheck($tenantId, 'certificate', 'certificate_invalid_verify_hash', 'Certificate verify hash sai', 'verification_hash phải tồn tại và đủ độ dài.', 'error', $hashIssues);

        $results[] = $this->recordCheck($tenantId, 'certificate', 'certificate_revoked_verifies_active', 'Certificate revoked nhưng verify vẫn active', 'Revoked certificate không được có verification valid mới.', 'critical',
            $this->queryIssues('certificate_verifications', 'certificate_verification', $tenantId, function (Builder $query) {
                $query->join('certificate_issues', 'certificate_issues.id', '=', 'certificate_verifications.certificate_issue_id')
                    ->where('certificate_issues.status', 'revoked')
                    ->where('certificate_verifications.valid', true)
                    ->select('certificate_verifications.id', 'certificate_verifications.issue_code as title');
            }, 'Revoked certificate vẫn verify valid: ')
        );

        return $results;
    }

    public function detectUserPermissionIssues(int $tenantId): array
    {
        $results = [];

        $results[] = $this->recordCheck($tenantId, 'user_permission', 'user_without_tenant', 'User không có tenant', 'User phải trỏ tới tenant tồn tại.', 'critical',
            $this->queryIssues('lms_users', 'lms_user', $tenantId, function (Builder $query) {
                $query->leftJoin('tenants', 'tenants.id', '=', 'lms_users.tenant_id')
                    ->whereNull('tenants.id')
                    ->select('lms_users.id', 'lms_users.email as title');
            }, 'User không có tenant: ', false)
        );

        $results[] = $this->recordCheck($tenantId, 'user_permission', 'user_without_role', 'User không có role', 'User active phải có ít nhất một role scope.', 'warning',
            $this->queryIssues('lms_users', 'lms_user', $tenantId, function (Builder $query) {
                $query->leftJoin('user_role_scope', 'user_role_scope.user_id', '=', 'lms_users.id')
                    ->where('lms_users.status', 'active')
                    ->whereNull('user_role_scope.id')
                    ->select('lms_users.id', 'lms_users.email as title');
            }, 'User active chưa có role: ')
        );

        $results[] = $this->recordCheck($tenantId, 'user_permission', 'role_permission_key_missing', 'Role có permission key không tồn tại', 'role_permission phải trỏ permission tồn tại.', 'critical',
            $this->queryIssues('role_permission', 'role_permission', $tenantId, function (Builder $query) use ($tenantId) {
                $query->leftJoin('permissions', 'permissions.id', '=', 'role_permission.permission_id')
                    ->leftJoin('roles', 'roles.id', '=', 'role_permission.role_id')
                    ->where('roles.tenant_id', $tenantId)
                    ->whereNull('permissions.id')
                    ->select('role_permission.role_id as id', DB::raw("role_permission.permission_id as title"));
            }, 'Role permission không tồn tại: ', false)
        );

        $menuIssues = [];
        $menu = config('eralms.menu', []);
        foreach ($menu as $item) {
            $routeName = $item['route_name'] ?? null;
            if ($routeName && ! Route::has($routeName)) {
                $menuIssues[] = $this->issue('menu_item', $routeName, 'Menu trỏ route không tồn tại: '.$routeName, 'error', false, ['item' => $item]);
            }
        }
        $results[] = $this->recordCheck($tenantId, 'user_permission', 'menu_route_missing', 'Menu trỏ route không tồn tại', 'Menu route_name phải tồn tại trong router.', 'error', $menuIssues);

        return $results;
    }

    public function autoFixIssue(DataIntegrityIssue $issue, ?int $actorId = null): DataIntegrityIssue
    {
        if (! $issue->auto_fix_available || $issue->status !== 'open') {
            throw new \RuntimeException('Issue không thể auto-fix.');
        }

        if ($issue->issue_key === 'component_required_without_completion_rule' && $issue->entity_type === 'course_component') {
            $component = CourseComponent::query()->where('tenant_id', $issue->tenant_id)->findOrFail($issue->entity_id);
            $config = $component->config ?? [];
            $config['completion_rule'] ??= ['type' => 'view'];
            $component->forceFill(['config' => $config])->save();

            $issue->forceFill(['status' => 'fixed', 'fixed_by' => $actorId, 'fixed_at' => now()])->save();

            return $issue->fresh();
        }

        throw new \RuntimeException('Chưa có auto-fix handler cho issue này.');
    }

    public function ignoreIssue(DataIntegrityIssue $issue, ?int $actorId = null): DataIntegrityIssue
    {
        $metadata = $issue->metadata ?? [];
        $metadata['ignored_by'] = $actorId;
        $metadata['ignored_at'] = now()->toISOString();

        $issue->forceFill(['status' => 'ignored', 'metadata' => $metadata])->save();

        return $issue->fresh();
    }

    public function dashboard(int $tenantId): array
    {
        return [
            'summary' => $this->summary($tenantId),
            'checks' => DataIntegrityCheck::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('module')
                ->orderBy('severity')
                ->get(),
            'issues_by_module' => DataIntegrityIssue::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'open')
                ->select('module', DB::raw('count(*) as total'))
                ->groupBy('module')
                ->pluck('total', 'module'),
            'issues_by_severity' => DataIntegrityIssue::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'open')
                ->select('severity', DB::raw('count(*) as total'))
                ->groupBy('severity')
                ->pluck('total', 'severity'),
            'latest_issues' => DataIntegrityIssue::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'open')
                ->latest()
                ->limit(25)
                ->get(),
            'auto_fixable' => DataIntegrityIssue::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'open')
                ->where('auto_fix_available', true)
                ->latest()
                ->limit(50)
                ->get(),
        ];
    }

    public function summary(?int $tenantId = null): array
    {
        $checks = DataIntegrityCheck::query()->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId));
        $issues = DataIntegrityIssue::query()->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))->where('status', 'open');

        return [
            'total_checks' => (clone $checks)->count(),
            'passed' => (clone $checks)->where('status', 'passed')->count(),
            'failed' => (clone $checks)->where('status', 'failed')->count(),
            'warning' => (clone $issues)->where('severity', 'warning')->count(),
            'error' => (clone $issues)->where('severity', 'error')->count(),
            'critical' => (clone $issues)->where('severity', 'critical')->count(),
            'auto_fixable' => (clone $issues)->where('auto_fix_available', true)->count(),
        ];
    }

    public function exportReport(int $tenantId): array
    {
        return [
            'generated_at' => now()->toISOString(),
            'tenant_id' => $tenantId,
            'summary' => $this->summary($tenantId),
            'checks' => DataIntegrityCheck::query()->where('tenant_id', $tenantId)->orderBy('module')->get()->toArray(),
            'issues' => DataIntegrityIssue::query()->where('tenant_id', $tenantId)->orderBy('module')->orderByDesc('severity')->get()->toArray(),
        ];
    }

    private function recordCheck(int $tenantId, string $module, string $checkKey, string $title, string $description, string $severity, array $issues): array
    {
        $check = DataIntegrityCheck::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'check_key' => $checkKey],
            [
                'module' => $module,
                'title' => $title,
                'description' => $description,
                'severity' => $severity,
                'status' => $issues === [] ? 'passed' : 'failed',
                'failed_count' => count($issues),
                'last_run_at' => now(),
                'metadata' => ['source' => 'DataIntegrityService'],
            ]
        );

        $currentKeys = [];
        foreach ($issues as $issue) {
            $issueKey = $issue['issue_key'] ?? $checkKey;
            $entityType = $issue['entity_type'];
            $entityId = (string) ($issue['entity_id'] ?? '');
            $currentKeys[] = $entityType.'|'.$entityId.'|'.$issueKey;

            DataIntegrityIssue::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'issue_key' => $issueKey,
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                ],
                [
                    'check_id' => $check->id,
                    'module' => $module,
                    'message' => $issue['message'],
                    'severity' => $issue['severity'] ?? $severity,
                    'auto_fix_available' => $issue['auto_fix_available'] ?? false,
                    'status' => 'open',
                    'metadata' => $issue['metadata'] ?? [],
                ]
            );
        }

        DataIntegrityIssue::query()
            ->where('tenant_id', $tenantId)
            ->where('check_id', $check->id)
            ->where('status', 'open')
            ->get()
            ->each(function (DataIntegrityIssue $issue) use ($currentKeys): void {
                $key = $issue->entity_type.'|'.$issue->entity_id.'|'.$issue->issue_key;
                if (! in_array($key, $currentKeys, true)) {
                    $issue->forceFill(['status' => 'fixed', 'fixed_at' => now()])->save();
                }
            });

        return [
            'check_key' => $checkKey,
            'module' => $module,
            'status' => $issues === [] ? 'passed' : 'failed',
            'failed_count' => count($issues),
        ];
    }

    private function queryIssues(string $table, string $entityType, int $tenantId, callable $scope, string $prefix, bool $tenantScoped = true): array
    {
        if (! $this->hasTable($table)) {
            return [];
        }

        $query = DB::table($table);
        if ($tenantScoped && Schema::hasColumn($table, 'tenant_id')) {
            $query->where("{$table}.tenant_id", $tenantId);
        }
        $scope($query);

        return $query->limit(1000)->get()->map(fn ($row) => $this->issue($entityType, $row->id, $prefix.($row->title ?? $row->id)))->all();
    }

    private function componentMissingContentIssues(int $tenantId): array
    {
        if (! $this->hasTable('course_components')) {
            return [];
        }

        return DB::table('course_components')
            ->leftJoin('content_repository_items', 'content_repository_items.id', '=', 'course_components.content_id')
            ->where('course_components.tenant_id', $tenantId)
            ->whereIn('course_components.component_type', ['video', 'pdf', 'file', 'scorm'])
            ->where(function ($query) {
                $query->whereNull('course_components.content_id')->orWhereNull('content_repository_items.id');
            })
            ->select('course_components.id', 'course_components.title')
            ->limit(1000)
            ->get()
            ->map(fn ($row) => $this->issue('course_component', $row->id, 'Component thiếu repository item: '.$row->title))
            ->all();
    }

    private function requiredComponentMissingCompletionRule(int $tenantId): array
    {
        if (! $this->hasTable('course_components')) {
            return [];
        }

        $issues = [];
        CourseComponent::query()
            ->where('tenant_id', $tenantId)
            ->where('required', true)
            ->orderBy('id')
            ->each(function (CourseComponent $component) use (&$issues): void {
                if (! data_get($component->config ?? [], 'completion_rule')) {
                    $issues[] = $this->issue('course_component', $component->id, 'Required component thiếu completion_rule: '.$component->title, 'warning', true);
                }
            });

        return $issues;
    }

    private function choiceQuestionIssues(int $tenantId, array $types, string $mode, string $prefix): array
    {
        if (! $this->hasTable('questions') || ! $this->hasTable('question_options')) {
            return [];
        }

        $issues = [];
        DB::table('questions')
            ->where('tenant_id', $tenantId)
            ->whereIn('question_type', $types)
            ->orderBy('id')
            ->cursor()
            ->each(function ($question) use (&$issues, $mode, $prefix): void {
                $correct = DB::table('question_options')->where('question_id', $question->id)->where('is_correct', true)->count();
                if (($mode === 'zero' && $correct === 0) || ($mode === 'many' && $correct > 1)) {
                    $issues[] = $this->issue('question', $question->id, $prefix.$question->title, 'error', false, ['correct_count' => $correct]);
                }
            });

        return $issues;
    }

    private function flaggedAttemptIssues(int $tenantId): array
    {
        if (! $this->hasTable('exam_attempts')) {
            return [];
        }

        $issues = [];
        DB::table('exam_attempts')
            ->where('tenant_id', $tenantId)
            ->where('suspicious_score', '>=', 70)
            ->orderBy('id')
            ->cursor()
            ->each(function ($attempt) use (&$issues): void {
                $metadata = json_decode((string) $attempt->metadata, true) ?: [];
                if (! ($metadata['reviewed_at'] ?? null)) {
                    $issues[] = $this->issue('exam_attempt', $attempt->id, 'Flagged attempt chưa được xử lý: '.$attempt->session_uuid, 'warning', false, ['suspicious_score' => $attempt->suspicious_score]);
                }
            });

        return $issues;
    }

    private function gradeItemSourceIssues(int $tenantId): array
    {
        if (! $this->hasTable('grade_items')) {
            return [];
        }

        $map = [
            'quiz' => 'exams',
            'exam' => 'exams',
            'assignment' => 'assignments',
            'attendance' => 'attendance_sessions',
        ];
        $issues = [];

        DB::table('grade_items')->where('tenant_id', $tenantId)->orderBy('id')->cursor()->each(function ($item) use (&$issues, $map): void {
            if ($item->source_type === 'manual') {
                return;
            }
            $table = $map[$item->source_type] ?? null;
            if (! $table || ! $item->source_id || ! $this->hasTable($table) || ! DB::table($table)->where('id', $item->source_id)->exists()) {
                $issues[] = $this->issue('grade_item', $item->id, 'Grade item source_id không tồn tại: '.$item->title, 'error', false, ['source_type' => $item->source_type, 'source_id' => $item->source_id]);
            }
        });

        return $issues;
    }

    private function gradebookWeightIssues(int $tenantId): array
    {
        if (! $this->hasTable('gradebooks') || ! $this->hasTable('grade_items')) {
            return [];
        }

        $issues = [];
        DB::table('gradebooks')->where('tenant_id', $tenantId)->orderBy('id')->cursor()->each(function ($gradebook) use (&$issues): void {
            $weights = DB::table('grade_items')->where('gradebook_id', $gradebook->id)->whereNotNull('weight');
            if ((clone $weights)->count() === 0) {
                return;
            }
            $total = (float) (clone $weights)->sum('weight');
            if (abs($total - 100.0) > 0.01) {
                $issues[] = $this->issue('gradebook', $gradebook->id, 'Tổng weight gradebook khác 100%: '.$gradebook->title, 'warning', false, ['total_weight' => $total]);
            }
        });

        return $issues;
    }

    private function duplicateValueIssues(string $table, int $tenantId, string $column, string $entityType, string $prefix): array
    {
        if (! $this->hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return [];
        }

        return DB::table($table)
            ->select($column, DB::raw('count(*) as total'), DB::raw('min(id) as id'))
            ->where('tenant_id', $tenantId)
            ->whereNotNull($column)
            ->groupBy($column)
            ->having('total', '>', 1)
            ->limit(1000)
            ->get()
            ->map(fn ($row) => $this->issue($entityType, $row->id, $prefix.$row->{$column}, 'critical', false, ['value' => $row->{$column}, 'count' => $row->total]))
            ->all();
    }

    private function duplicateMappingIssues(int $tenantId): array
    {
        if (! $this->hasTable('integration_mappings')) {
            return [];
        }

        return DB::table('integration_mappings')
            ->select('entity_type', 'external_id', DB::raw('count(*) as total'), DB::raw('min(id) as id'))
            ->where('tenant_id', $tenantId)
            ->whereNotNull('external_id')
            ->groupBy('entity_type', 'external_id')
            ->having('total', '>', 1)
            ->limit(1000)
            ->get()
            ->map(fn ($row) => $this->issue('integration_mapping', $row->id, 'Mapping trùng external_id: '.$row->external_id, 'critical', false, ['entity_type' => $row->entity_type, 'external_id' => $row->external_id, 'count' => $row->total]))
            ->all();
    }

    private function targetExists(string $type, int|string $id): bool
    {
        $table = match ($type) {
            'course' => 'courses',
            'section', 'course_section' => 'course_sections',
            'component', 'course_component' => 'course_components',
            default => null,
        };

        return $table && $this->hasTable($table) && DB::table($table)->where('id', $id)->exists();
    }

    private function issue(string $entityType, int|string|null $entityId, string $message, string $severity = 'error', bool $autoFix = false, array $metadata = []): array
    {
        return [
            'entity_type' => $entityType,
            'entity_id' => (string) $entityId,
            'message' => $message,
            'severity' => $severity,
            'auto_fix_available' => $autoFix,
            'metadata' => $metadata,
        ];
    }

    private function hasTable(string $table): bool
    {
        return Schema::hasTable($table);
    }
}
