<?php

namespace App\Services;

use App\Models\LmsActionRegistry;
use App\Models\Permission;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class LmsActionRegistryService
{
    public function getActionsForPage(?string $module = null): array
    {
        return LmsActionRegistry::query()
            ->when($module, fn ($query) => $query->where('module', $module))
            ->where('is_active', true)
            ->orderBy('module')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (LmsActionRegistry $action) => $this->validateActionBinding($action))
            ->values()
            ->all();
    }

    public function syncActions(): array
    {
        $created = 0;
        $updated = 0;
        $definitions = $this->definitions();
        $activeKeys = collect($definitions)
            ->map(fn (array $definition) => $definition['module'].'|'.$definition['action_key'])
            ->all();

        foreach ($definitions as $index => $definition) {
            $action = LmsActionRegistry::query()->updateOrCreate(
                ['module' => $definition['module'], 'action_key' => $definition['action_key']],
                [
                    'label' => $definition['label'],
                    'route_name' => $definition['route_name'],
                    'http_method' => $definition['http_method'] ?? 'POST',
                    'permission_key' => $definition['permission_key'] ?? null,
                    'confirm_required' => $definition['confirm_required'] ?? false,
                    'confirm_message' => $definition['confirm_message'] ?? null,
                    'success_message' => $definition['success_message'] ?? 'Thao tác thành công',
                    'failure_message' => $definition['failure_message'] ?? 'Không thể thực hiện thao tác',
                    'is_active' => true,
                    'sort_order' => $definition['sort_order'] ?? (($index + 1) * 10),
                ]
            );

            $action->wasRecentlyCreated ? $created++ : $updated++;
        }

        $activeKeyLookup = array_fill_keys($activeKeys, true);
        $staleIds = LmsActionRegistry::query()
            ->where('is_active', true)
            ->get(['id', 'module', 'action_key'])
            ->reject(fn (LmsActionRegistry $action) => isset($activeKeyLookup[$action->module.'|'.$action->action_key]))
            ->pluck('id');
        $deactivated = $staleIds->isEmpty()
            ? 0
            : LmsActionRegistry::query()->whereIn('id', $staleIds)->update(['is_active' => false]);

        return ['created' => $created, 'updated' => $updated, 'deactivated' => $deactivated, 'total' => count($definitions)];
    }

    public function syncPermissions(): array
    {
        $keys = LmsActionRegistry::query()->pluck('permission_key')->filter()->unique()->values();

        foreach ($keys as $key) {
            [$module, $action] = array_pad(explode('.', $key, 2), 2, 'access');
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['module' => $module, 'action' => $action, 'description' => 'Cho phép '.$key]
            );
        }

        return ['permissions' => $keys->count(), 'keys' => $keys->all()];
    }

    public function checkRouteExists(LmsActionRegistry|string $action): bool
    {
        return (bool) $this->resolveRoute($action instanceof LmsActionRegistry ? $action : null, is_string($action) ? $action : $action->route_name);
    }

    public function checkPermissionExists(LmsActionRegistry|string $action): bool
    {
        $permission = $action instanceof LmsActionRegistry ? $action->permission_key : $action;

        return ! $permission || Permission::query()->where('key', $permission)->exists();
    }

    public function checkControllerExists(LmsActionRegistry $action): bool
    {
        $route = $this->resolveRoute($action, $action->route_name);

        if (! $route) {
            return false;
        }

        $uses = $route->getAction('uses');
        if (! is_string($uses) || ! str_contains($uses, '@')) {
            return is_callable($route->getAction('uses'));
        }

        [$controller, $method] = explode('@', $uses, 2);

        return class_exists($controller) && method_exists($controller, $method);
    }

    public function validateActionBinding(LmsActionRegistry $action): array
    {
        $route = $this->resolveRoute($action, $action->route_name);
        $permissionExists = $this->checkPermissionExists($action);
        $controllerExists = $route ? $this->checkControllerExists($action) : false;
        $status = 'OK';

        if (! $route) {
            $status = 'Missing route';
        } elseif (! $permissionExists) {
            $status = 'Missing permission';
        } elseif (! $controllerExists) {
            $status = 'Missing controller';
        } elseif ($this->needsFrontendHandler($action) && ! $this->frontendHandlerExists($action)) {
            $status = 'Missing frontend handler';
        }

        return [
            'id' => $action->id,
            'module' => $action->module,
            'action_key' => $action->action_key,
            'label' => $action->label,
            'route_name' => $action->route_name,
            'api_endpoint' => $route ? implode('|', $route->methods()).' /'.$route->uri() : null,
            'http_method' => $action->http_method,
            'permission_key' => $action->permission_key,
            'controller_method' => $route ? $this->controllerMethod($route) : null,
            'confirm_required' => $action->confirm_required,
            'status' => $status,
        ];
    }

    public function scanActions(): array
    {
        $actions = LmsActionRegistry::query()->where('is_active', true)->orderBy('module')->orderBy('sort_order')->get();
        $rows = $actions->map(fn (LmsActionRegistry $action) => $this->validateActionBinding($action))->values();

        return [
            'summary' => [
                'total' => $rows->count(),
                'ok' => $rows->where('status', 'OK')->count(),
                'issues' => $rows->where('status', '!=', 'OK')->count(),
            ],
            'actions' => $rows->all(),
        ];
    }

    public function fixMissingPermissions(): array
    {
        $this->syncActions();

        return $this->syncPermissions();
    }

    public function clearCache(): array
    {
        Cache::flush();
        Artisan::call('route:clear');
        Artisan::call('config:clear');

        return ['cleared' => true];
    }

    public function smokeActions(): array
    {
        $scan = $this->scanActions();
        $dangerWithoutConfirm = LmsActionRegistry::query()
            ->where('is_active', true)
            ->where('confirm_required', false)
            ->where('http_method', '!=', 'GET')
            ->where(function ($query) {
                foreach (['delete', 'archive', 'lock', 'revoke', 'force', 'sync', 'submit', 'publish', 'approve'] as $word) {
                    $query->orWhere('action_key', 'like', "%{$word}%");
                }
            })
            ->pluck('action_key')
            ->values()
            ->all();

        return [
            'summary' => $scan['summary'] + [
                'danger_without_confirm' => count($dangerWithoutConfirm),
            ],
            'issues' => collect($scan['actions'])->where('status', '!=', 'OK')->values()->all(),
            'danger_without_confirm' => $dangerWithoutConfirm,
            'checks' => [
                'button_action_key' => 'registry enforced',
                'route_backend' => 'scanned',
                'controller' => 'scanned',
                'permission' => 'scanned',
                'confirm' => 'scanned',
                'toast_reload' => 'frontend ActionButton/useLmsAction enforced',
            ],
        ];
    }

    private function resolveRoute(?LmsActionRegistry $action, string $routeName): ?RoutingRoute
    {
        $routes = Route::getRoutes();

        if ($route = $routes->getByName($routeName)) {
            return $route;
        }

        if (preg_match('/^(GET|POST|PUT|PATCH|DELETE)\s+(.+)$/', $routeName, $matches)) {
            $method = $matches[1];
            $uri = ltrim($matches[2], '/');

            foreach ($routes as $route) {
                if (in_array($method, $route->methods(), true) && $route->uri() === $uri) {
                    return $route;
                }
            }
        }

        if ($action) {
            $method = strtoupper($action->http_method);
            foreach ($routes as $route) {
                if (in_array($method, $route->methods(), true) && Str::contains($route->uri(), Str::after($action->action_key, '.'))) {
                    return $route;
                }
            }
        }

        return null;
    }

    private function controllerMethod(RoutingRoute $route): string
    {
        $uses = $route->getAction('uses');

        return is_string($uses) ? $uses : 'closure';
    }

    private function needsFrontendHandler(LmsActionRegistry $action): bool
    {
        return ! in_array($action->http_method, ['GET'], true);
    }

    private function frontendHandlerExists(LmsActionRegistry $action): bool
    {
        $paths = [
            resource_path('js/Composables/useLmsAction.ts'),
            resource_path('js/Components/Lms/ActionButton.vue'),
            resource_path('js/Components/Lms/ActionBar.vue'),
        ];

        foreach ($paths as $path) {
            if (! is_file($path)) {
                return false;
            }
        }

        return true;
    }

    private function definitions(): array
    {
        $danger = fn (string $message) => ['confirm_required' => true, 'confirm_message' => $message];

        $manual = [
            $this->def('course', 'course.create', 'Tạo khóa học', 'POST api/v1/courses', 'course.create'),
            $this->def('course', 'course.update', 'Sửa khóa học', 'PUT api/v1/courses/{course}', 'course.update'),
            $this->def('course', 'course.clone', 'Clone khóa học', 'POST api/v1/courses/{course}/clone', 'course.create'),
            $this->def('course', 'course.submit_review', 'Submit review', 'POST api/v1/courses/{course}/submit-review', 'course.review', $danger('Gửi khóa học sang bước review?')),
            $this->def('course', 'course.approve', 'Approve', 'POST api/v1/courses/{course}/approve', 'course.approve', $danger('Duyệt khóa học này?')),
            $this->def('course', 'course.publish', 'Publish', 'POST api/v1/courses/{course}/publish', 'course.publish', $danger('Publish khóa học cho người học?')),
            $this->def('course', 'course.archive', 'Archive', 'POST api/v1/courses/{course}/archive', 'course.update', $danger('Archive khóa học này?')),
            $this->def('course', 'course.section.create', 'Tạo section/unit', 'POST api/v1/courses/{course}/sections', 'lesson.manage'),
            $this->def('course', 'course.component.create', 'Tạo component', 'POST api/v1/course-components', 'lesson.manage'),
            $this->def('course', 'course.reorder', 'Reorder', 'POST api/v1/course-components/reorder', 'lesson.manage'),
            $this->def('course', 'course.preview', 'Preview', 'GET api/v1/courses/{course}/studio', 'course.view'),

            $this->def('repository', 'repository.folder.create', 'Tạo folder', 'POST api/v1/repository/folders', 'repository.manage'),
            $this->def('repository', 'repository.upload', 'Upload file', 'POST api/v1/repository/upload', 'repository.upload'),
            $this->def('repository', 'repository.move', 'Move', 'POST api/v1/repository/items/{item}/move', 'repository.manage'),
            $this->def('repository', 'repository.copy', 'Copy', 'POST api/v1/repository/items/{item}/copy', 'repository.manage'),
            $this->def('repository', 'repository.share', 'Share', 'POST api/v1/repository/items/{item}/share', 'repository.manage'),
            $this->def('repository', 'repository.new_version', 'New version', 'POST api/v1/repository/items/{item}/new-version', 'repository.upload'),
            $this->def('repository', 'repository.bulk_actions', 'Bulk action', 'POST api/v1/repository/bulk-actions', 'repository.manage', $danger('Thực hiện thao tác hàng loạt trên kho học liệu?')),
            $this->def('repository', 'repository.trash', 'Move to trash', 'POST api/v1/repository/items/{item}/trash', 'repository.manage', $danger('Đưa học liệu vào thùng rác?')),
            $this->def('repository', 'repository.restore', 'Restore from trash', 'POST api/v1/repository/items/{item}/restore', 'repository.manage'),
            $this->def('repository', 'repository.permanent_delete', 'Permanent delete', 'DELETE api/v1/repository/items/{item}/permanent', 'repository.manage', $danger('Xóa vĩnh viễn học liệu này?')),
            $this->def('repository', 'repository.submit_review', 'Submit review', 'POST api/v1/repository/items/{item}/submit-review', 'repository.manage', $danger('Gửi học liệu sang review?')),
            $this->def('repository', 'repository.approve', 'Approve', 'POST api/v1/repository/items/{item}/approve', 'repository.approve', $danger('Duyệt học liệu này?')),

            $this->def('learning_path', 'learning_path.rule.create', 'Tạo rule', 'POST api/v1/courses/{course}/learning-path/rules', 'learning_path.manage'),
            $this->def('learning_path', 'learning_path.rule.update', 'Sửa rule', 'PUT api/v1/learning-path/rules/{rule}', 'learning_path.manage'),
            $this->def('learning_path', 'learning_path.rule.delete', 'Xóa rule', 'DELETE api/v1/learning-path/rules/{rule}', 'learning_path.manage', $danger('Xóa rule này?')),
            $this->def('learning_path', 'learning_path.event.append', 'Ghi tiến độ', 'POST api/v1/learning-events', 'progress.view_own'),
            $this->def('learning_path', 'learning_path.completion.approve', 'Approve completion', 'POST api/v1/completions/{completion}/approve', 'completion.approve', $danger('Duyệt hoàn thành bài học?')),

            $this->def('video', 'video.upload', 'Upload video', 'POST api/v1/videos/upload', 'video.upload'),
            $this->def('video', 'video.process', 'Process video', 'POST api/v1/videos/{video}/process', 'video.process', $danger('Đưa video vào hàng xử lý?')),
            $this->def('video', 'video.attach', 'Attach video', 'POST api/v1/videos/{video}/attach-component', 'video.attach'),
            $this->def('video', 'video.playback_url', 'Generate playback URL', 'GET api/v1/videos/{video}/playback-url', 'progress.view_own'),
            $this->def('video', 'video.session.start', 'Start session', 'POST api/v1/video-sessions/start', 'progress.view_own'),
            $this->def('video', 'video.heartbeat', 'Heartbeat', 'POST api/v1/video-sessions/{session}/heartbeat', 'progress.view_own'),
            $this->def('video', 'video.complete', 'Complete video', 'POST api/v1/video-sessions/{session}/end', 'progress.view_own'),

            $this->def('question_bank', 'question_bank.create', 'Tạo ngân hàng', 'POST api/v1/question-banks', 'question_bank.manage'),
            $this->def('question_bank', 'question.create', 'Tạo câu hỏi', 'POST api/v1/questions', 'question.create'),
            $this->def('question_bank', 'question.approve', 'Approve', 'POST api/v1/questions/{question}/approve', 'question.approve', $danger('Duyệt câu hỏi này?')),
            $this->def('question_bank', 'question.publish', 'Publish', 'POST api/v1/questions/{question}/publish', 'question.publish', $danger('Publish câu hỏi này?')),
            $this->def('question_bank', 'question.import', 'Import', 'POST api/v1/question-imports', 'question.import'),
            $this->def('question_bank', 'blueprint.preview', 'Generate blueprint preview', 'POST api/v1/exam-blueprints/{blueprint}/generate-preview', 'blueprint.manage'),

            $this->def('exam', 'exam.create', 'Tạo exam', 'POST api/v1/exams', 'exam.create'),
            $this->def('exam', 'exam.build', 'Build from blueprint', 'POST api/v1/exams/{exam}/build-from-blueprint', 'exam.update'),
            $this->def('exam', 'exam.assign_user', 'Gán thí sinh', 'POST api/v1/exams/{exam}/assign-user', 'exam.assign'),
            $this->def('exam', 'exam.publish', 'Publish exam', 'POST api/v1/exams/{exam}/publish', 'exam.publish', $danger('Publish exam này?')),
            $this->def('exam', 'attempt.start', 'Start attempt', 'POST api/v1/exams/{exam}/attempts/start', 'exam.attempt'),
            $this->def('exam', 'attempt.autosave', 'Autosave answer', 'POST api/v1/exam-attempts/{attempt}/autosave', 'exam.attempt'),
            $this->def('exam', 'attempt.submit', 'Submit attempt', 'POST api/v1/exam-attempts/{attempt}/submit', 'exam.attempt', $danger('Nộp bài thi?')),
            $this->def('exam', 'answer.grade', 'Manual grade', 'POST api/v1/exam-answers/{answer}/grade', 'exam.grade'),
            $this->def('exam', 'result.publish', 'Publish result', 'POST api/v1/exam-results/{result}/publish', 'exam.result.publish', $danger('Công bố kết quả?')),

            $this->def('assignment', 'assignment.create', 'Tạo bài tập', 'POST api/v1/assignments', 'assignment.create'),
            $this->def('assignment', 'assignment.update', 'Cập nhật bài tập', 'PUT api/v1/assignments/{assignment}', 'assignment.update'),
            $this->def('assignment', 'assignment.schedule', 'Cấu hình deadline', 'PUT api/v1/assignments/{assignment}/schedule', 'assignment.update'),
            $this->def('assignment', 'assignment.deadline', 'Xem deadline learner', 'GET api/v1/assignments/{assignment}/deadline', 'assignment.view'),
            $this->def('assignment', 'assignment.publish', 'Publish', 'POST api/v1/assignments/{assignment}/publish', 'assignment.update', $danger('Publish bài tập?')),
            $this->def('assignment', 'assignment.close', 'Close assignment', 'POST api/v1/assignments/{assignment}/close', 'assignment.update', $danger('Đóng bài tập?')),
            $this->def('assignment', 'submission.submit', 'Submit assignment', 'POST api/v1/assignments/{assignment}/submit', 'assignment.submit', $danger('Nộp bài tập?')),
            $this->def('assignment', 'submission.files', 'Upload file', 'POST api/v1/assignment-submissions/{submission}/files', 'assignment.submit'),
            $this->def('assignment', 'submission.resubmit', 'Resubmit assignment', 'POST api/v1/assignment-submissions/{submission}/resubmit', 'assignment.submit', $danger('Nộp lại bài tập?')),
            $this->def('assignment', 'submission.grade', 'Grade', 'POST api/v1/assignment-submissions/{submission}/grade', 'assignment.grade'),
            $this->def('assignment', 'submission.return', 'Return', 'POST api/v1/assignment-submissions/{submission}/return', 'assignment.grade', $danger('Trả bài cho học viên?')),
            $this->def('assignment', 'grade.approve', 'Approve grade', 'POST api/v1/assignment-grades/{grade}/approve', 'assignment.approve', $danger('Duyệt điểm bài tập?')),

            $this->def('gradebook', 'gradebook.create', 'Tạo gradebook', 'POST api/v1/gradebooks', 'gradebook.manage'),
            $this->def('gradebook', 'gradebook.recalculate', 'Recalculate', 'POST api/v1/gradebooks/{gradebook}/recalculate', 'gradebook.calculate', $danger('Tính lại sổ điểm?')),
            $this->def('gradebook', 'gradebook.submit_approval', 'Submit approval', 'POST api/v1/gradebooks/{gradebook}/submit-approval', 'grade.approve', $danger('Gửi duyệt sổ điểm?')),
            $this->def('gradebook', 'gradebook.approve', 'Approve', 'POST api/v1/gradebooks/{gradebook}/approve', 'grade.approve', $danger('Duyệt sổ điểm?')),
            $this->def('gradebook', 'gradebook.lock', 'Lock', 'POST api/v1/gradebooks/{gradebook}/lock', 'grade.lock', $danger('Khóa sổ điểm?')),
            $this->def('gradebook', 'gradebook.sync_sis', 'Sync SIS', 'POST api/v1/gradebooks/{gradebook}/sync-to-sis', 'grade.sync_sis', $danger('Đồng bộ điểm sang SIS?')),

            $this->def('attendance', 'live_session.create', 'Tạo live session', 'POST api/v1/live-sessions', 'live_session.manage'),
            $this->def('attendance', 'live_session.start', 'Start session', 'POST api/v1/live-sessions/{liveSession}/start', 'live_session.manage'),
            $this->def('attendance', 'attendance.open', 'Open', 'POST api/v1/attendance-sessions/{attendanceSession}/open', 'attendance.manage'),
            $this->def('attendance', 'attendance.close', 'Close', 'POST api/v1/attendance-sessions/{attendanceSession}/close', 'attendance.manage', $danger('Đóng phiên điểm danh?')),
            $this->def('attendance', 'attendance.lock', 'Lock', 'POST api/v1/attendance-sessions/{attendanceSession}/lock', 'attendance.lock', $danger('Khóa phiên điểm danh?')),
            $this->def('attendance', 'attendance.checkin_qr', 'QR checkin', 'POST api/v1/attendance/checkin-qr', 'attendance.checkin'),
            $this->def('attendance', 'attendance.checkin_otp', 'OTP checkin', 'POST api/v1/attendance/checkin-otp', 'attendance.checkin'),
            $this->def('attendance', 'eligibility.recalculate', 'Recalculate eligibility', 'POST api/v1/courses/{course}/recalculate-eligibility', 'eligibility.recalculate', $danger('Tính lại điều kiện dự thi?')),

            $this->def('enrollment', 'cohort.create', 'Tạo cohort', 'POST api/v1/enrollment/cohorts', 'enrollment.manage'),
            $this->def('enrollment', 'section.self_enroll', 'Self enrol', 'POST api/v1/enrollment/sections/{section}/self-enroll', 'enrollment.self'),
            $this->def('enrollment', 'records.bulk', 'Bulk enrol', 'POST api/v1/enrollment/records/bulk', 'enrollment.manage', $danger('Ghi danh hàng loạt?')),
            $this->def('enrollment', 'records.import', 'Import enrolments', 'POST api/v1/enrollment/imports', 'enrollment.import'),

            $this->def('community', 'forum.create', 'Tạo forum', 'POST api/v1/community/forums', 'community.manage'),
            $this->def('community', 'thread.create', 'Tạo discussion', 'POST api/v1/community/forums/{forum}/threads', 'community.post'),
            $this->def('community', 'reply.create', 'Trả lời discussion', 'POST api/v1/community/threads/{thread}/replies', 'community.post'),
            $this->def('community', 'post.mark_correct', 'Mark correct', 'POST api/v1/community/posts/{post}/mark-correct', 'community.moderate'),
            $this->def('community', 'wiki.create', 'Tạo wiki', 'POST api/v1/community/wikis', 'community.wiki'),
            $this->def('community', 'blog.create', 'Tạo blog', 'POST api/v1/community/blogs', 'community.blog'),

            $this->def('survey', 'form.create', 'Tạo survey form', 'POST api/v1/surveys/forms', 'survey.manage'),
            $this->def('survey', 'campaign.launch', 'Launch campaign', 'POST api/v1/surveys/campaigns/{campaign}/launch', 'survey.manage', $danger('Mở chiến dịch khảo sát?')),
            $this->def('survey', 'response.submit', 'Submit response', 'POST api/v1/surveys/campaigns/{campaign}/responses', 'survey.respond', $danger('Gửi phản hồi khảo sát?')),
            $this->def('survey', 'campaign.export', 'Export responses', 'GET api/v1/surveys/campaigns/{campaign}/export', 'survey.export'),

            $this->def('standards', 'scorm.upload', 'Upload SCORM', 'POST api/v1/learning-standards/scorm/packages', 'course.view'),
            $this->def('standards', 'scorm.launch', 'Launch SCORM', 'POST api/v1/learning-standards/scorm/packages/{package}/launch', 'course.view'),
            $this->def('standards', 'xapi.store', 'Store xAPI statement', 'POST api/v1/learning-standards/xapi/statements', 'course.view'),
            $this->def('standards', 'lti.register', 'Register LTI tool', 'POST api/v1/learning-standards/lti/registrations', 'course.view'),
            $this->def('standards', 'external_tool.create', 'Create external tool', 'POST api/v1/learning-standards/external-tools', 'course.view'),

            $this->def('obe', 'outcome.create', 'Tạo outcome', 'POST api/v1/obe/outcomes', 'obe.manage'),
            $this->def('obe', 'outcome.map', 'Map outcome', 'POST api/v1/obe/outcome-mappings', 'obe.manage'),
            $this->def('obe', 'assessment.map', 'Map assessment', 'POST api/v1/obe/assessment-mappings', 'obe.manage'),
            $this->def('obe', 'achievement.recalculate', 'Recalculate achievement', 'POST api/v1/obe/recalculate-achievement', 'obe.analytics', $danger('Tính lại achievement OBE?')),
            $this->def('obe', 'report.generate', 'Generate accreditation report', 'POST api/v1/obe/accreditation-reports', 'accreditation.report', $danger('Tạo báo cáo kiểm định?')),

            $this->def('sis', 'sis.test_connection', 'Test connection', 'POST api/v1/integrations/systems/{system}/test-connection', 'integration.manage'),
            $this->def('sis', 'sis.sync_users', 'Sync users', 'POST api/v1/integrations/sync/users', 'integration.manage', $danger('Đồng bộ users từ SIS?')),
            $this->def('sis', 'sis.sync_classes', 'Sync classes', 'POST api/v1/integrations/sync/classes', 'integration.manage', $danger('Đồng bộ lớp từ SIS?')),
            $this->def('sis', 'sis.sync_enrollments', 'Sync enrollments', 'POST api/v1/integrations/sync/enrollments', 'integration.manage', $danger('Đồng bộ ghi danh từ SIS?')),
            $this->def('sis', 'sis.push_grades', 'Push grades', 'POST api/v1/integrations/push/grades', 'integration.push', $danger('Đẩy điểm sang SIS?')),
            $this->def('sis', 'sis.retry_event', 'Retry event', 'POST api/v1/integrations/events/{event}/retry', 'integration.manage', $danger('Retry integration event?')),

            $this->def('certificate', 'certificate.issue', 'Issue certificate', 'POST api/v1/credentials/certificates/issue', 'credential.issue', $danger('Cấp certificate?')),
            $this->def('certificate', 'certificate.revoke', 'Revoke', 'POST api/v1/credentials/certificates/{issue}/revoke', 'credential.revoke', $danger('Thu hồi certificate?')),
            $this->def('certificate', 'badge.issue', 'Issue badge', 'POST api/v1/credentials/badges/issue', 'credential.issue', $danger('Cấp badge?')),

            $this->def('ai', 'ai.ingest', 'Ingest document', 'POST api/v1/ai/ingest', 'ai.use'),
            $this->def('ai', 'ai.ask', 'Ask course', 'POST api/v1/ai/ask', 'ai.use'),
            $this->def('ai', 'ai.quiz', 'Generate quiz', 'POST api/v1/ai/quizzes', 'ai.use'),
            $this->def('ai', 'ai.flashcards', 'Generate flashcard', 'POST api/v1/ai/flashcards', 'ai.use'),
            $this->def('ai', 'ai.summary', 'Generate summary', 'POST api/v1/ai/summary', 'ai.use'),

            $this->def('analytics', 'analytics.snapshot', 'Run snapshot', 'POST api/v1/analytics/summaries/build', 'analytics.warehouse', $danger('Chạy lại analytics snapshot?')),
            $this->def('analytics', 'analytics.risk', 'Recalculate risk score', 'POST api/v1/analytics/risks/calculate', 'analytics.risk', $danger('Tính lại risk score?')),
            $this->def('analytics', 'analytics.alert.resolve', 'Resolve alert', 'POST api/v1/analytics/alerts/{alert}/resolve', 'analytics.alerts', $danger('Resolve alert?')),

            $this->def('moodle_parity', 'moodle.parity.report', 'Moodle parity report', 'GET api/v1/admin/lms/moodle-parity', 'core.role.manage'),
            $this->def('moodle_parity', 'moodle.parity.sync', 'Sync Moodle parity', 'POST api/v1/admin/lms/moodle-parity/sync', 'core.role.manage', $danger('Đồng bộ Moodle parity actions, permissions và menu?')),
            $this->def('security', 'security.audit_scan', 'Security audit scan', 'POST api/v1/admin/lms/action-check/smoke-test', 'security.manage', $danger('Chạy kiểm tra bảo mật/action?')),
            $this->def('plugins', 'plugin.registry_scan', 'Plugin registry scan', 'GET api/v1/admin/lms/moodle-parity', 'plugin.manage'),
            $this->def('backup', 'backup.restore_plan', 'Backup restore plan', 'GET api/v1/admin/lms/moodle-parity', 'backup.manage'),

            $this->def('admin', 'action.scan', 'Scan actions', 'GET api/v1/admin/lms/action-check', 'core.role.manage'),
            $this->def('admin', 'action.sync', 'Sync actions', 'POST api/v1/admin/lms/action-check/sync-actions', 'core.role.manage', $danger('Đồng bộ action registry?')),
            $this->def('admin', 'action.fix_permissions', 'Fix missing permissions', 'POST api/v1/admin/lms/action-check/fix-permissions', 'core.role.manage', $danger('Tạo quyền còn thiếu?')),
            $this->def('admin', 'action.clear_cache', 'Clear cache', 'POST api/v1/admin/lms/action-check/clear-cache', 'core.role.manage', $danger('Xóa cache hệ thống?')),
            $this->def('admin', 'action.smoke', 'Run smoke test', 'POST api/v1/admin/lms/action-check/smoke-test', 'core.role.manage', $danger('Chạy smoke test action flow?')),
        ];

        return $this->mergeDefinitions($manual, $this->routeDefinitions());
    }

    private function def(string $module, string $key, string $label, string $route, ?string $permission = null, array $options = []): array
    {
        [$method] = explode(' ', $route, 2);

        return [
            'module' => $module,
            'action_key' => $key,
            'label' => $label,
            'route_name' => $route,
            'http_method' => $method,
            'permission_key' => $permission,
            ...$options,
        ];
    }

    private function mergeDefinitions(array $manual, array $generated): array
    {
        $seenKeys = [];
        $seenRoutes = [];
        foreach ($manual as $definition) {
            $seenKeys[$definition['module'].'|'.$definition['action_key']] = true;
            $seenRoutes[$definition['route_name']] = true;
        }

        foreach ($generated as $definition) {
            $key = $definition['module'].'|'.$definition['action_key'];
            if (isset($seenKeys[$key]) || isset($seenRoutes[$definition['route_name']])) {
                continue;
            }

            $manual[] = $definition;
            $seenKeys[$key] = true;
            $seenRoutes[$definition['route_name']] = true;
        }

        return $manual;
    }

    private function routeDefinitions(): array
    {
        $definitions = [];
        foreach (Route::getRoutes() as $route) {
            $method = $this->primaryRouteMethod($route);
            $uri = $route->uri();

            if (! $method || ! $this->shouldRegisterRoute($uri, $method)) {
                continue;
            }

            $module = $this->moduleForRoute($uri);
            $actionKey = $this->actionKeyForRoute($module, $method, $uri);
            $label = $this->labelForRoute($method, $uri);
            $permission = $this->permissionForRoute($route);
            $options = $this->confirmOptionsForRoute($method, $uri, $label);

            $definitions[] = $this->def(
                $module,
                $actionKey,
                $label,
                $method.' '.$uri,
                $permission,
                $options
            );
        }

        return $definitions;
    }

    private function primaryRouteMethod(RoutingRoute $route): ?string
    {
        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            if (in_array($method, $route->methods(), true)) {
                return $method;
            }
        }

        return null;
    }

    private function shouldRegisterRoute(string $uri, string $method): bool
    {
        if (! Str::startsWith($uri, 'api/v1/')) {
            return false;
        }

        if (Str::contains($uri, [
            'health',
            'select-options',
            'certificate/',
            'credentials/verify/',
        ])) {
            return false;
        }

        if ($method === 'GET' && Str::contains($uri, [
            '/download-url',
            '/playback-url',
        ])) {
            return false;
        }

        return true;
    }

    private function permissionForRoute(RoutingRoute $route): ?string
    {
        foreach ($route->middleware() as $middleware) {
            if (preg_match('/^permission:([^,]+)/', $middleware, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function moduleForRoute(string $uri): string
    {
        $path = Str::after($uri, 'api/v1/');

        foreach ([
            'admin/lms' => 'admin',
            'core' => 'core',
            'mobile' => 'mobile',
            'repository' => 'repository',
            'learning-path' => 'learning_path',
            'learning-events' => 'learning_path',
            'components' => 'learning_path',
            'completions' => 'learning_path',
            'videos' => 'video',
            'video-sessions' => 'video',
            'question-banks' => 'question_bank',
            'question-categories' => 'question_bank',
            'questions' => 'question_bank',
            'question-imports' => 'question_bank',
            'exam-blueprints' => 'question_bank',
            'learning-outcomes' => 'question_bank',
            'question-coverage' => 'question_bank',
            'exams' => 'exam',
            'my-exams' => 'exam',
            'exam-attempts' => 'exam',
            'exam-answers' => 'exam',
            'exam-results' => 'exam',
            'manual-grading' => 'exam',
            'assignments' => 'assignment',
            'assignment-submissions' => 'assignment',
            'assignment-grades' => 'assignment',
            'rubrics' => 'assignment',
            'gradebooks' => 'gradebook',
            'learner-grades' => 'gradebook',
            'career' => 'career',
            'portfolio' => 'career',
            'public-portfolios' => 'career',
            'skill-definitions' => 'career',
            'credentials' => 'certificate',
            'live-sessions' => 'attendance',
            'attendance-sessions' => 'attendance',
            'attendance' => 'attendance',
            'integrations' => 'sis',
            'learning-standards' => 'standards',
            'obe' => 'obe',
            'enrollment' => 'enrollment',
            'analytics' => 'analytics',
            'surveys' => 'survey',
            'community' => 'community',
            'ai' => 'ai',
            'courses' => 'course',
            'course-sections' => 'course',
            'course-components' => 'course',
            'course-categories' => 'course',
            'activity-types' => 'course',
            'editor' => 'repository',
            'forums' => 'community',
        ] as $prefix => $module) {
            if ($path === $prefix || Str::startsWith($path, $prefix.'/')) {
                return $module;
            }
        }

        return Str::slug(Str::before($path, '/'), '_') ?: 'core';
    }

    private function actionKeyForRoute(string $module, string $method, string $uri): string
    {
        $path = Str::after($uri, 'api/v1/');
        $segments = collect(explode('/', $path))
            ->reject(fn (string $segment) => $segment === '' || preg_match('/^\{.+\}$/', $segment))
            ->map(fn (string $segment) => Str::slug($segment, '_'))
            ->values();

        $verb = $this->verbForRoute($method, $uri);
        $base = $segments->isEmpty() ? $module : $segments->implode('.');
        if ($segments->last() === $verb) {
            return Str::limit($base, 190, '');
        }

        return Str::limit($base.'.'.$verb, 190, '');
    }

    private function verbForRoute(string $method, string $uri): string
    {
        $tail = collect(explode('/', Str::after($uri, 'api/v1/')))
            ->reject(fn (string $segment) => $segment === '' || preg_match('/^\{.+\}$/', $segment))
            ->last();

        $tail = $tail ? Str::slug($tail, '_') : 'action';
        $hasParameter = (bool) preg_match('/\{[^}]+\}/', $uri);

        return match ($method) {
            'GET' => $hasParameter ? 'view' : 'list',
            'POST' => $hasParameter ? $tail : 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => $tail,
        };
    }

    private function labelForRoute(string $method, string $uri): string
    {
        $verb = $this->verbForRoute($method, $uri);
        $resource = $this->resourceNameForRoute($method, $uri, $verb);

        return match ($verb) {
            'list' => 'Xem danh sách '.$resource,
            'view' => 'Xem chi tiết '.$resource,
            'create' => 'Tạo '.$resource,
            'update' => 'Sửa '.$resource,
            'delete' => 'Xóa '.$resource,
            default => Str::headline($verb).' '.$resource,
        };
    }

    private function resourceNameForRoute(string $method, string $uri, string $verb): string
    {
        $segments = collect(explode('/', Str::after($uri, 'api/v1/')))
            ->reject(fn (string $segment) => $segment === '' || preg_match('/^\{.+\}$/', $segment))
            ->values();

        $segment = $segments->last();
        if ($method === 'POST' && $segment && Str::slug($segment, '_') === $verb && $segments->count() > 1) {
            $segment = $segments->slice(-2, 1)->first();
        }

        return Str::headline($segment ?: 'action');
    }

    private function confirmOptionsForRoute(string $method, string $uri, string $label): array
    {
        $dangerWords = [
            'delete', 'permanent', 'trash', 'archive', 'close', 'lock', 'revoke',
            'sync', 'publish', 'approve', 'reject', 'submit', 'resolve', 'retry',
            'bulk', 'recalculate', 'override', 'return', 'launch',
        ];

        if ($method === 'GET') {
            return [];
        }

        $key = Str::lower($method.' '.$uri);
        foreach ($dangerWords as $word) {
            if (str_contains($key, $word)) {
                return ['confirm_required' => true, 'confirm_message' => $label.'?'];
            }
        }

        return [];
    }
}
