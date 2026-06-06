<?php

use App\Http\Controllers\Api\V1\CoreController;
use App\Http\Controllers\Api\V1\AssignmentController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AiLearningController;
use App\Http\Controllers\Api\V1\CareerPortfolioController;
use App\Http\Controllers\Api\V1\Core\RbacController;
use App\Http\Controllers\Api\V1\Core\UserController;
use App\Http\Controllers\Api\V1\Core\WhiteLabelController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\DigitalCredentialController;
use App\Http\Controllers\Api\V1\EnrollmentController;
use App\Http\Controllers\Api\V1\ExamController;
use App\Http\Controllers\Api\V1\GradebookController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\IntegrationController;
use App\Http\Controllers\Api\V1\LearningStandardsController;
use App\Http\Controllers\Api\V1\LearningAnalyticsController;
use App\Http\Controllers\Api\V1\LearningCommunityController;
use App\Http\Controllers\Api\V1\LearningPathController;
use App\Http\Controllers\Api\V1\MobileLearningController;
use App\Http\Controllers\Api\V1\OBEController;
use App\Http\Controllers\Api\V1\QuestionBankController;
use App\Http\Controllers\Api\V1\RepositoryController;
use App\Http\Controllers\Api\V1\SurveyController;
use App\Http\Controllers\Api\V1\VideoController;
use App\Services\LmsActionRegistryService;
use App\Services\LmsSystemService;
use App\Services\MoodleParityService;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::get('certificate/{code}', [DigitalCredentialController::class, 'portal']);
Route::get('v1/health', HealthController::class);

Route::prefix('v1/admin/lms')->middleware(['api', 'tenant.resolve'])->group(function () {
    Route::get('system-check', function (LmsSystemService $service) {
        return ApiResponse::success($service->systemCheck(), 'LMS system check completed.');
    })->name('api.admin.lms.system-check');

    Route::post('system-check/actions/{action}', function (string $action, LmsSystemService $service) {
        return ApiResponse::success($service->action($action), 'Action executed.', ['action' => $action]);
    })->name('api.admin.lms.system-check.action');

    Route::get('action-check', function (LmsActionRegistryService $service) {
        return ApiResponse::success($service->scanActions(), 'Action scan completed.');
    })->name('api.admin.lms.action-check');

    Route::post('action-check/scan', function (LmsActionRegistryService $service) {
        return ApiResponse::success($service->scanActions(), 'Action scan completed.');
    })->name('api.admin.lms.action-check.scan');

    Route::post('action-check/sync-actions', function (LmsActionRegistryService $service) {
        return ApiResponse::success($service->syncActions(), 'Action registry synced.');
    })->name('api.admin.lms.action-check.sync-actions');

    Route::post('action-check/fix-permissions', function (LmsActionRegistryService $service) {
        return ApiResponse::success($service->fixMissingPermissions(), 'Missing permissions fixed.');
    })->name('api.admin.lms.action-check.fix-permissions');

    Route::post('action-check/clear-cache', function (LmsActionRegistryService $service) {
        return ApiResponse::success($service->clearCache(), 'Action cache cleared.');
    })->name('api.admin.lms.action-check.clear-cache');

    Route::post('action-check/smoke-test', function (LmsActionRegistryService $service) {
        return ApiResponse::success($service->smokeActions(), 'Action smoke test completed.');
    })->name('api.admin.lms.action-check.smoke-test');

    Route::get('moodle-parity', function (MoodleParityService $service) {
        return ApiResponse::success($service->report(), 'Moodle parity report completed.');
    })->name('api.admin.lms.moodle-parity');

    Route::post('moodle-parity/sync', function (LmsActionRegistryService $actions, LmsSystemService $system, MoodleParityService $parity) {
        return ApiResponse::success([
            'parity' => $parity->syncPlan(),
            'actions' => $actions->syncActions(),
            'permissions' => $system->syncPermissions(),
            'menu' => $system->rebuildMenu(),
        ], 'Moodle parity sync completed.');
    })->name('api.admin.lms.moodle-parity.sync');
});

Route::prefix('v1')->middleware(['api', 'api.performance', 'tenant.resolve'])->group(function () {
    Route::post('editor/media-upload', [RepositoryController::class, 'editorUpload']);

    Route::prefix('mobile')->group(function () {
        Route::get('bootstrap', [MobileLearningController::class, 'bootstrap']);
        Route::post('sync', [MobileLearningController::class, 'sync']);
        Route::post('push-subscriptions', [MobileLearningController::class, 'pushSubscription']);
    });

    Route::prefix('core')->group(function () {
        Route::get('me', [CoreController::class, 'me']);
        Route::get('dashboard', [CoreController::class, 'dashboard']);
        Route::get('tenants', [CoreController::class, 'tenants'])->middleware('permission:core.tenant.view,tenant');
        Route::get('organizations', [CoreController::class, 'organizations']);
        Route::get('campuses', [CoreController::class, 'campuses']);
        Route::get('academic-units', [CoreController::class, 'academicUnits']);
        Route::get('settings', [CoreController::class, 'settings']);
        Route::put('settings', [CoreController::class, 'updateSettings'])->middleware('permission:core.tenant.manage,tenant');
        Route::get('audit-logs', [CoreController::class, 'auditLogs'])->middleware('permission:core.role.manage,tenant');
        Route::get('users', [UserController::class, 'index'])->middleware('permission:core.user.view,tenant');
        Route::post('users', [UserController::class, 'store'])->middleware('permission:core.user.create,tenant');
        Route::put('users/{user}', [UserController::class, 'update'])->middleware('permission:core.user.update,tenant');
        Route::post('users/{user}/lock', [UserController::class, 'lock'])->middleware('permission:core.user.lock,tenant');
        Route::post('users/{user}/unlock', [UserController::class, 'unlock'])->middleware('permission:core.user.lock,tenant');
        Route::get('roles', [RbacController::class, 'roles'])->middleware('permission:core.role.manage,tenant');
        Route::get('permissions', [RbacController::class, 'permissions'])->middleware('permission:core.role.manage,tenant');
        Route::post('users/{user}/roles', [RbacController::class, 'assignRole'])->middleware('permission:core.role.manage,tenant');
        Route::put('roles/{role}/permissions', [RbacController::class, 'syncRolePermissions'])->middleware('permission:core.role.manage,tenant');
        Route::put('white-label', [WhiteLabelController::class, 'update'])->middleware('permission:core.tenant.manage,tenant');
    });

    Route::get('course-categories', [CourseController::class, 'categories']);
    Route::get('activity-types', [CourseController::class, 'activityTypes']);
    Route::apiResource('courses', CourseController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('courses/{course}/clone', [CourseController::class, 'clone']);
    Route::post('courses/{course}/submit-review', [CourseController::class, 'submitReview']);
    Route::post('courses/{course}/approve', [CourseController::class, 'approve']);
    Route::post('courses/{course}/publish', [CourseController::class, 'publish']);
    Route::post('courses/{course}/archive', [CourseController::class, 'archive']);
    Route::get('courses/{course}/validate-publish', [CourseController::class, 'validateForPublish']);
    Route::get('courses/{course}/publish-checklist', [CourseController::class, 'publishChecklist']);
    Route::get('courses/{course}/studio', [CourseController::class, 'studio']);
    Route::post('courses/{course}/sections', [CourseController::class, 'storeSection']);
    Route::put('course-sections/{section}', [CourseController::class, 'updateSection']);
    Route::delete('course-sections/{section}', [CourseController::class, 'deleteSection']);
    Route::post('course-sections/reorder', [CourseController::class, 'reorderSections']);
    Route::post('course-components', [CourseController::class, 'storeComponent']);
    Route::put('course-components/{component}', [CourseController::class, 'updateComponent']);
    Route::delete('course-components/{component}', [CourseController::class, 'deleteComponent']);
    Route::post('course-components/reorder', [CourseController::class, 'reorderComponents']);
    Route::post('course-components/{component}/duplicate', [CourseController::class, 'duplicateComponent']);
    Route::get('exams/select-options', [CourseController::class, 'examSelectOptions']);
    Route::get('assignments/select-options', [CourseController::class, 'assignmentSelectOptions']);
    Route::get('learning-outcomes/select-options', [CourseController::class, 'learningOutcomeSelectOptions']);

    Route::prefix('repository')->group(function () {
        Route::get('items', [RepositoryController::class, 'index']);
        Route::get('tree', [RepositoryController::class, 'tree']);
        Route::get('items/{item}', [RepositoryController::class, 'show']);
        Route::post('folders', [RepositoryController::class, 'storeFolder']);
        Route::post('upload', [RepositoryController::class, 'upload']);
        Route::post('bulk-actions', [RepositoryController::class, 'bulkAction']);
        Route::put('items/{item}', [RepositoryController::class, 'update']);
        Route::post('items/{item}/new-version', [RepositoryController::class, 'newVersion']);
        Route::post('items/{item}/move', [RepositoryController::class, 'move']);
        Route::post('items/{item}/copy', [RepositoryController::class, 'copy']);
        Route::post('items/{item}/share', [RepositoryController::class, 'share']);
        Route::post('items/{item}/trash', [RepositoryController::class, 'trash']);
        Route::post('items/{item}/restore', [RepositoryController::class, 'restore']);
        Route::delete('items/{item}/permanent', [RepositoryController::class, 'permanentDelete']);
        Route::get('items/{item}/versions', [RepositoryController::class, 'versions']);
        Route::get('items/{item}/download-url', [RepositoryController::class, 'downloadUrl']);
        Route::post('items/{item}/submit-review', [RepositoryController::class, 'submitReview']);
        Route::post('items/{item}/approve', [RepositoryController::class, 'approve']);
        Route::post('items/{item}/reject', [RepositoryController::class, 'reject']);
        Route::post('items/{item}/return', [RepositoryController::class, 'returnForEdit']);
    });

    Route::get('courses/{course}/learning-path/rules', [LearningPathController::class, 'rules'])->middleware('permission:learning_path.view,course');
    Route::post('courses/{course}/learning-path/rules', [LearningPathController::class, 'storeRule'])->middleware('permission:learning_path.manage,course');
    Route::put('learning-path/rules/{rule}', [LearningPathController::class, 'updateRule'])->middleware('permission:learning_path.manage,course');
    Route::delete('learning-path/rules/{rule}', [LearningPathController::class, 'deleteRule'])->middleware('permission:learning_path.manage,course');
    Route::get('courses/{course}/progress/me', [LearningPathController::class, 'myProgress'])->middleware('permission:progress.view_own,course');
    Route::get('courses/{course}/progress/users', [LearningPathController::class, 'userProgress'])->middleware('permission:progress.view_class,course');
    Route::post('learning-events', [LearningPathController::class, 'appendEvent'])->middleware('permission:progress.view_own,course');
    Route::post('components/{component}/start', [LearningPathController::class, 'startComponent'])->middleware('permission:progress.view_own,course');
    Route::post('components/{component}/progress', [LearningPathController::class, 'componentProgress'])->middleware('permission:progress.view_own,course');
    Route::post('components/{component}/complete', [LearningPathController::class, 'completeComponent'])->middleware('permission:progress.view_own,course');
    Route::get('components/{component}/access-check', [LearningPathController::class, 'accessCheck'])->middleware('permission:progress.view_own,course');
    Route::post('completions/{completion}/request-approval', [LearningPathController::class, 'requestApproval'])->middleware('permission:progress.view_own,course');
    Route::post('completions/{completion}/approve', [LearningPathController::class, 'approveCompletion'])->middleware('permission:completion.approve,course');
    Route::post('completions/{completion}/reject', [LearningPathController::class, 'rejectCompletion'])->middleware('permission:completion.approve,course');

    Route::get('videos', [VideoController::class, 'index'])->middleware('permission:video.view,tenant');
    Route::post('videos/upload', [VideoController::class, 'upload'])->middleware('permission:video.upload,tenant');
    Route::get('videos/{video}', [VideoController::class, 'show'])->middleware('permission:video.view,tenant');
    Route::post('videos/{video}/attach-component', [VideoController::class, 'attachComponent'])->middleware('permission:video.attach,course');
    Route::post('videos/{video}/process', [VideoController::class, 'process'])->middleware('permission:video.process,tenant');
    Route::get('videos/{video}/playback-url', [VideoController::class, 'playbackUrl'])->middleware('permission:progress.view_own,course');
    Route::post('video-sessions/start', [VideoController::class, 'startSession'])->middleware('permission:progress.view_own,course');
    Route::post('video-sessions/{session}/event', [VideoController::class, 'event'])->middleware('permission:progress.view_own,course');
    Route::post('video-sessions/{session}/heartbeat', [VideoController::class, 'heartbeat'])->middleware('permission:progress.view_own,course');
    Route::post('video-sessions/{session}/end', [VideoController::class, 'endSession'])->middleware('permission:progress.view_own,course');
    Route::get('courses/{course}/video-progress', [VideoController::class, 'courseProgress'])->middleware('permission:video.analytics.view,course');
    Route::get('components/{component}/video-progress/me', [VideoController::class, 'myComponentProgress'])->middleware('permission:progress.view_own,course');

    Route::get('question-banks', [QuestionBankController::class, 'banks'])->middleware('permission:question_bank.view,tenant');
    Route::post('question-banks', [QuestionBankController::class, 'storeBank'])->middleware('permission:question_bank.manage,tenant');
    Route::get('question-banks/{bank}', [QuestionBankController::class, 'showBank'])->middleware('permission:question_bank.view,tenant');
    Route::put('question-banks/{bank}', [QuestionBankController::class, 'updateBank'])->middleware('permission:question_bank.manage,tenant');
    Route::post('question-banks/{bank}/clone', [QuestionBankController::class, 'cloneBank'])->middleware('permission:question_bank.manage,tenant');
    Route::post('question-banks/{bank}/submit-review', [QuestionBankController::class, 'submitBank'])->middleware('permission:question_bank.manage,tenant');
    Route::post('question-banks/{bank}/approve', [QuestionBankController::class, 'approveBank'])->middleware('permission:question_bank.approve,tenant');
    Route::get('question-categories', [QuestionBankController::class, 'categories'])->middleware('permission:question_bank.view,tenant');
    Route::post('question-categories', [QuestionBankController::class, 'storeCategory'])->middleware('permission:question_bank.manage,tenant');
    Route::put('question-categories/{category}', [QuestionBankController::class, 'updateCategory'])->middleware('permission:question_bank.manage,tenant');
    Route::delete('question-categories/{category}', [QuestionBankController::class, 'deleteCategory'])->middleware('permission:question_bank.manage,tenant');
    Route::get('questions', [QuestionBankController::class, 'questions'])->middleware('permission:question.view,tenant');
    Route::post('questions', [QuestionBankController::class, 'storeQuestion'])->middleware('permission:question.create,tenant');
    Route::get('questions/{question}', [QuestionBankController::class, 'showQuestion'])->middleware('permission:question.view,tenant');
    Route::put('questions/{question}', [QuestionBankController::class, 'updateQuestion'])->middleware('permission:question.update,tenant');
    Route::post('questions/{question}/clone', [QuestionBankController::class, 'cloneQuestion'])->middleware('permission:question.create,tenant');
    Route::post('questions/{question}/approve', [QuestionBankController::class, 'approveQuestion'])->middleware('permission:question.approve,tenant');
    Route::post('questions/{question}/publish', [QuestionBankController::class, 'publishQuestion'])->middleware('permission:question.publish,tenant');
    Route::post('questions/{question}/archive', [QuestionBankController::class, 'archiveQuestion'])->middleware('permission:question.update,tenant');
    Route::get('learning-outcomes', [QuestionBankController::class, 'outcomes'])->middleware('permission:question.view,tenant');
    Route::post('learning-outcomes', [QuestionBankController::class, 'storeOutcome'])->middleware('permission:question_bank.manage,tenant');
    Route::post('questions/{question}/outcomes', [QuestionBankController::class, 'mapOutcomes'])->middleware('permission:question.update,tenant');
    Route::get('question-coverage', [QuestionBankController::class, 'coverage'])->middleware('permission:question.view,tenant');
    Route::post('question-imports', [QuestionBankController::class, 'import'])->middleware('permission:question.import,tenant');
    Route::get('question-imports/{job}', [QuestionBankController::class, 'importShow'])->middleware('permission:question.import,tenant');
    Route::get('exam-blueprints', [QuestionBankController::class, 'blueprints'])->middleware('permission:blueprint.manage,tenant');
    Route::post('exam-blueprints', [QuestionBankController::class, 'storeBlueprint'])->middleware('permission:blueprint.manage,tenant');
    Route::put('exam-blueprints/{blueprint}', [QuestionBankController::class, 'updateBlueprint'])->middleware('permission:blueprint.manage,tenant');
    Route::post('exam-blueprints/{blueprint}/generate-preview', [QuestionBankController::class, 'generatePreview'])->middleware('permission:blueprint.manage,tenant');

    Route::get('exams', [ExamController::class, 'index'])->middleware('permission:exam.view,tenant');
    Route::post('exams', [ExamController::class, 'store'])->middleware('permission:exam.create,tenant');
    Route::get('exams/{exam}', [ExamController::class, 'show'])->middleware('permission:exam.view,tenant');
    Route::put('exams/{exam}', [ExamController::class, 'update'])->middleware('permission:exam.update,tenant');
    Route::post('exams/{exam}/build-from-blueprint', [ExamController::class, 'build'])->middleware('permission:exam.update,tenant');
    Route::post('exams/{exam}/publish', [ExamController::class, 'publish'])->middleware('permission:exam.publish,tenant');
    Route::post('exams/{exam}/close', [ExamController::class, 'close'])->middleware('permission:exam.publish,tenant');
    Route::post('exams/{exam}/assign-user', [ExamController::class, 'assignUser'])->middleware('permission:exam.assign,tenant');
    Route::post('exams/{exam}/assign-class', [ExamController::class, 'assignClass'])->middleware('permission:exam.assign,tenant');
    Route::get('my-exams', [ExamController::class, 'myExams'])->middleware('permission:exam.attempt,tenant');
    Route::post('exams/{exam}/attempts/start', [ExamController::class, 'start'])->middleware('permission:exam.attempt,tenant');
    Route::get('exam-attempts/{attempt}', [ExamController::class, 'attempt'])->middleware('permission:exam.attempt,tenant');
    Route::post('exam-attempts/{attempt}/answers', [ExamController::class, 'answer'])->middleware('permission:exam.attempt,tenant');
    Route::post('exam-attempts/{attempt}/autosave', [ExamController::class, 'autosave'])->middleware('permission:exam.attempt,tenant');
    Route::post('exam-attempts/{attempt}/mark-review', [ExamController::class, 'markReview'])->middleware('permission:exam.attempt,tenant');
    Route::post('exam-attempts/{attempt}/events', [ExamController::class, 'event'])->middleware('permission:exam.attempt,tenant');
    Route::post('exam-attempts/{attempt}/submit', [ExamController::class, 'submit'])->middleware('permission:exam.attempt,tenant');
    Route::get('exams/{exam}/attempts', [ExamController::class, 'attempts'])->middleware('permission:exam.result.view,tenant');
    Route::get('exams/{exam}/results', [ExamController::class, 'results'])->middleware('permission:exam.result.view,tenant');
    Route::get('exam-results/classes', [ExamController::class, 'resultClasses'])->middleware('permission:exam.result.view,tenant');
    Route::get('exam-results/classes/{class}/exams/{exam}/candidates', [ExamController::class, 'resultCandidates'])->middleware('permission:exam.result.view,tenant');
    Route::get('exam-results/attempts/{attempt}/detail', [ExamController::class, 'resultAttemptDetail'])->middleware('permission:exam.result.view,tenant');
    Route::get('manual-grading/pending', [ExamController::class, 'pending'])->middleware('permission:exam.grade,tenant');
    Route::post('exam-answers/{answer}/grade', [ExamController::class, 'grade'])->middleware('permission:exam.grade,tenant');
    Route::post('exam-results/{result}/publish', [ExamController::class, 'publishResult'])->middleware('permission:exam.result.publish,tenant');

    Route::get('assignments', [AssignmentController::class, 'index'])->middleware('permission:assignment.view,tenant');
    Route::post('assignments', [AssignmentController::class, 'store'])->middleware('permission:assignment.create,tenant');
    Route::get('assignments/{assignment}', [AssignmentController::class, 'show'])->middleware('permission:assignment.view,tenant');
    Route::put('assignments/{assignment}', [AssignmentController::class, 'update'])->middleware('permission:assignment.update,tenant');
    Route::put('assignments/{assignment}/schedule', [AssignmentController::class, 'schedule'])->middleware('permission:assignment.update,tenant');
    Route::get('assignments/{assignment}/deadline', [AssignmentController::class, 'deadline'])->middleware('permission:assignment.view,tenant');
    Route::post('assignments/{assignment}/publish', [AssignmentController::class, 'publish'])->middleware('permission:assignment.update,tenant');
    Route::post('assignments/{assignment}/close', [AssignmentController::class, 'close'])->middleware('permission:assignment.update,tenant');
    Route::post('assignments/{assignment}/submit', [AssignmentController::class, 'submit'])->middleware('permission:assignment.submit,tenant');
    Route::post('assignment-submissions/{submission}/files', [AssignmentController::class, 'attachFiles'])->middleware('permission:assignment.submit,tenant');
    Route::post('assignment-submissions/{submission}/resubmit', [AssignmentController::class, 'resubmit'])->middleware('permission:assignment.submit,tenant');
    Route::get('assignments/{assignment}/submissions', [AssignmentController::class, 'submissions'])->middleware('permission:assignment.grade,tenant');
    Route::post('assignment-submissions/{submission}/grade', [AssignmentController::class, 'grade'])->middleware('permission:assignment.grade,tenant');
    Route::post('assignment-grades/{grade}/approve', [AssignmentController::class, 'approve'])->middleware('permission:assignment.approve,tenant');
    Route::post('assignment-submissions/{submission}/return', [AssignmentController::class, 'returnSubmission'])->middleware('permission:assignment.grade,tenant');
    Route::get('rubrics', [AssignmentController::class, 'rubrics'])->middleware('permission:rubric.manage,tenant');
    Route::post('rubrics', [AssignmentController::class, 'storeRubric'])->middleware('permission:rubric.manage,tenant');
    Route::put('rubrics/{rubric}', [AssignmentController::class, 'updateRubric'])->middleware('permission:rubric.manage,tenant');

    Route::get('gradebooks', [GradebookController::class, 'index'])->middleware('permission:gradebook.view,tenant');
    Route::post('gradebooks', [GradebookController::class, 'store'])->middleware('permission:gradebook.manage,tenant');
    Route::get('gradebooks/{gradebook}', [GradebookController::class, 'show'])->middleware('permission:gradebook.view,tenant');
    Route::put('gradebooks/{gradebook}', [GradebookController::class, 'update'])->middleware('permission:gradebook.manage,tenant');
    Route::post('gradebooks/{gradebook}/activate', [GradebookController::class, 'activate'])->middleware('permission:gradebook.manage,tenant');
    Route::post('gradebooks/{gradebook}/lock', [GradebookController::class, 'lock'])->middleware('permission:grade.lock,tenant');
    Route::post('gradebooks/{gradebook}/categories', [GradebookController::class, 'category'])->middleware('permission:gradebook.manage,tenant');
    Route::post('gradebooks/{gradebook}/items', [GradebookController::class, 'item'])->middleware('permission:gradebook.manage,tenant');
    Route::post('gradebooks/{gradebook}/reorder', [GradebookController::class, 'reorder'])->middleware('permission:gradebook.manage,tenant');
    Route::post('gradebooks/{gradebook}/pull-sources', [GradebookController::class, 'pullSources'])->middleware('permission:gradebook.calculate,tenant');
    Route::post('gradebooks/{gradebook}/recalculate', [GradebookController::class, 'recalculate'])->middleware('permission:gradebook.calculate,tenant');
    Route::get('gradebooks/{gradebook}/matrix', [GradebookController::class, 'matrix'])->middleware('permission:gradebook.view,tenant');
    Route::get('gradebooks/{gradebook}/logs', [GradebookController::class, 'logs'])->middleware('permission:gradebook.view,tenant');
    Route::post('learner-grades/{grade}/override', [GradebookController::class, 'override'])->middleware('permission:grade.override,tenant');
    Route::post('gradebooks/{gradebook}/submit-approval', [GradebookController::class, 'submit'])->middleware('permission:gradebook.manage,tenant');
    Route::post('gradebooks/{gradebook}/approve', [GradebookController::class, 'approve'])->middleware('permission:grade.approve,tenant');
    Route::post('gradebooks/{gradebook}/reject', [GradebookController::class, 'reject'])->middleware('permission:grade.approve,tenant');
    Route::post('gradebooks/{gradebook}/sync-to-sis', [GradebookController::class, 'sync'])->middleware('permission:grade.sync_sis,tenant');

    Route::get('career/dashboard', [CareerPortfolioController::class, 'dashboard'])->middleware('permission:career.view,tenant');
    Route::post('career/profile', [CareerPortfolioController::class, 'profile'])->middleware('permission:career.manage_own,tenant');
    Route::put('career/profiles/{profile}', [CareerPortfolioController::class, 'updateProfile'])->middleware('permission:career.manage_own,tenant');
    Route::post('digital-portfolios/{portfolio}/items', [CareerPortfolioController::class, 'addItem'])->middleware('permission:portfolio.manage_own,tenant');
    Route::get('skill-definitions', [CareerPortfolioController::class, 'skills'])->middleware('permission:career.view,tenant');
    Route::post('learner-skills', [CareerPortfolioController::class, 'upsertSkill'])->middleware('permission:skill.manage,tenant');
    Route::post('competency-records', [CareerPortfolioController::class, 'competency'])->middleware('permission:competency.manage,tenant');
    Route::post('career-timeline-events', [CareerPortfolioController::class, 'timeline'])->middleware('permission:career.manage_own,tenant');
    Route::get('public-portfolios/{slug}', [CareerPortfolioController::class, 'publicProfile']);
    Route::get('employer/students', [CareerPortfolioController::class, 'employerSearch'])->middleware('permission:employer.view,tenant');
    Route::get('employer/certificates/{code}/verify', [CareerPortfolioController::class, 'verifyCertificate'])->middleware('permission:employer.view,tenant');

    Route::prefix('credentials')->group(function () {
        Route::get('dashboard', [DigitalCredentialController::class, 'dashboard'])->middleware('permission:credential.view,tenant');
        Route::get('builder', [DigitalCredentialController::class, 'builder'])->middleware('permission:credential.manage,tenant');
        Route::post('certificate-templates', [DigitalCredentialController::class, 'storeTemplate'])->middleware('permission:credential.manage,tenant');
        Route::get('certificates', [DigitalCredentialController::class, 'certificates'])->middleware('permission:credential.view,tenant');
        Route::post('certificates', [DigitalCredentialController::class, 'storeCertificate'])->middleware('permission:credential.manage,tenant');
        Route::post('certificates/issue', [DigitalCredentialController::class, 'issueCertificate'])->middleware('permission:credential.issue,tenant');
        Route::post('certificates/{issue}/revoke', [DigitalCredentialController::class, 'revokeCertificate'])->middleware('permission:credential.revoke,tenant');
        Route::get('verify/{code}', [DigitalCredentialController::class, 'verify']);
        Route::get('badges', [DigitalCredentialController::class, 'badges'])->middleware('permission:credential.view,tenant');
        Route::post('badges', [DigitalCredentialController::class, 'storeBadge'])->middleware('permission:credential.manage,tenant');
        Route::post('badge-rules', [DigitalCredentialController::class, 'storeBadgeRule'])->middleware('permission:credential.manage,tenant');
        Route::post('badges/issue', [DigitalCredentialController::class, 'issueBadge'])->middleware('permission:credential.issue,tenant');
        Route::post('badges/auto-issue', [DigitalCredentialController::class, 'autoIssueBadges'])->middleware('permission:credential.issue,tenant');
        Route::get('micro-credentials', [DigitalCredentialController::class, 'microCredentials'])->middleware('permission:credential.view,tenant');
        Route::post('micro-credentials', [DigitalCredentialController::class, 'issueCredential'])->middleware('permission:credential.issue,tenant');
        Route::get('wallet', [DigitalCredentialController::class, 'wallet'])->middleware('permission:credential.wallet,tenant');
    });

    Route::get('live-sessions', [AttendanceController::class, 'liveSessions'])->middleware('permission:live_session.view,tenant');
    Route::post('live-sessions', [AttendanceController::class, 'storeLiveSession'])->middleware('permission:live_session.manage,tenant');
    Route::put('live-sessions/{liveSession}', [AttendanceController::class, 'updateLiveSession'])->middleware('permission:live_session.manage,tenant');
    Route::post('live-sessions/{liveSession}/start', [AttendanceController::class, 'startLiveSession'])->middleware('permission:live_session.manage,tenant');
    Route::post('live-sessions/{liveSession}/complete', [AttendanceController::class, 'completeLiveSession'])->middleware('permission:live_session.manage,tenant');
    Route::get('attendance-sessions', [AttendanceController::class, 'attendanceSessions'])->middleware('permission:attendance.view,tenant');
    Route::post('attendance-sessions', [AttendanceController::class, 'storeAttendanceSession'])->middleware('permission:attendance.manage,tenant');
    Route::post('attendance-sessions/{attendanceSession}/open', [AttendanceController::class, 'openAttendanceSession'])->middleware('permission:attendance.manage,tenant');
    Route::post('attendance-sessions/{attendanceSession}/close', [AttendanceController::class, 'closeAttendanceSession'])->middleware('permission:attendance.manage,tenant');
    Route::post('attendance-sessions/{attendanceSession}/lock', [AttendanceController::class, 'lockAttendanceSession'])->middleware('permission:attendance.lock,tenant');
    Route::post('attendance/checkin-qr', [AttendanceController::class, 'checkinQr'])->middleware('permission:attendance.checkin,tenant');
    Route::post('attendance/checkin-otp', [AttendanceController::class, 'checkinOtp'])->middleware('permission:attendance.checkin,tenant');
    Route::post('attendance/manual-update', [AttendanceController::class, 'manualUpdate'])->middleware('permission:attendance.manage,tenant');
    Route::get('courses/{course}/attendance-summary', [AttendanceController::class, 'attendanceSummary'])->middleware('permission:eligibility.view,course');
    Route::post('courses/{course}/recalculate-eligibility', [AttendanceController::class, 'recalculateEligibility'])->middleware('permission:eligibility.recalculate,course');

    Route::get('integrations/systems', [IntegrationController::class, 'systems'])->middleware('permission:integration.view,tenant');
    Route::post('integrations/systems', [IntegrationController::class, 'storeSystem'])->middleware('permission:integration.manage,tenant');
    Route::put('integrations/systems/{system}', [IntegrationController::class, 'updateSystem'])->middleware('permission:integration.manage,tenant');
    Route::post('integrations/systems/{system}/test-connection', [IntegrationController::class, 'testConnection'])->middleware('permission:integration.manage,tenant');
    Route::get('integrations/mappings', [IntegrationController::class, 'mappings'])->middleware('permission:integration.view,tenant');
    Route::post('integrations/mappings', [IntegrationController::class, 'storeMapping'])->middleware('permission:integration.manage,tenant');
    Route::post('integrations/mappings/resolve-conflict', [IntegrationController::class, 'resolveConflict'])->middleware('permission:integration.manage,tenant');
    Route::post('integrations/webhooks/inbound/{systemCode}', [IntegrationController::class, 'inbound'])->middleware('throttle:120,1');
    Route::get('integrations/events', [IntegrationController::class, 'events'])->middleware('permission:integration.view,tenant');
    Route::post('integrations/events/{event}/retry', [IntegrationController::class, 'retryEvent'])->middleware('permission:integration.manage,tenant');
    Route::get('integrations/sync-jobs', [IntegrationController::class, 'syncJobs'])->middleware('permission:integration.view,tenant');
    Route::post('integrations/sync/users', [IntegrationController::class, 'syncUsers'])->middleware('permission:integration.manage,tenant');
    Route::post('integrations/sync/classes', [IntegrationController::class, 'syncClasses'])->middleware('permission:integration.manage,tenant');
    Route::post('integrations/sync/enrollments', [IntegrationController::class, 'syncEnrollments'])->middleware('permission:integration.manage,tenant');
    Route::post('integrations/push/grades', [IntegrationController::class, 'pushGrades'])->middleware('permission:integration.push,tenant');
    Route::post('integrations/push/attendance', [IntegrationController::class, 'pushAttendance'])->middleware('permission:integration.push,tenant');
    Route::post('integrations/push/progress', [IntegrationController::class, 'pushProgress'])->middleware('permission:integration.push,tenant');
    Route::get('integrations/health', [IntegrationController::class, 'health'])->middleware('permission:integration.view,tenant');

    Route::prefix('learning-standards')->group(function () {
        Route::get('scorm/packages', [LearningStandardsController::class, 'packages']);
        Route::post('scorm/packages', [LearningStandardsController::class, 'uploadScorm']);
        Route::post('scorm/packages/{package}/launch', [LearningStandardsController::class, 'launchScorm']);
        Route::post('scorm/attempts/{attempt}/track', [LearningStandardsController::class, 'trackScorm']);
        Route::get('scorm/events', [LearningStandardsController::class, 'scormEvents']);
        Route::get('xapi/statements', [LearningStandardsController::class, 'xapiStatements']);
        Route::post('xapi/statements', [LearningStandardsController::class, 'storeXapi']);
        Route::get('lti/registrations', [LearningStandardsController::class, 'ltiRegistrations']);
        Route::post('lti/registrations', [LearningStandardsController::class, 'storeLtiRegistration']);
        Route::post('lti/registrations/{registration}/launch', [LearningStandardsController::class, 'launchLti']);
        Route::get('lti/launches', [LearningStandardsController::class, 'ltiLaunches']);
        Route::get('external-tools', [LearningStandardsController::class, 'externalTools']);
        Route::post('external-tools', [LearningStandardsController::class, 'storeExternalTool']);
        Route::get('analytics', [LearningStandardsController::class, 'analytics']);
    });

    Route::get('obe/outcomes', [OBEController::class, 'outcomes'])->middleware('permission:obe.view,tenant');
    Route::post('obe/outcomes', [OBEController::class, 'storeOutcome'])->middleware('permission:obe.manage,tenant');
    Route::put('obe/outcomes/{outcome}', [OBEController::class, 'updateOutcome'])->middleware('permission:obe.manage,tenant');
    Route::get('obe/competency-frameworks', [OBEController::class, 'frameworks'])->middleware('permission:obe.view,tenant');
    Route::post('obe/competency-frameworks', [OBEController::class, 'storeFramework'])->middleware('permission:obe.manage,tenant');
    Route::post('obe/competency-frameworks/{framework}/items', [OBEController::class, 'storeFrameworkItem'])->middleware('permission:obe.manage,tenant');
    Route::post('obe/outcome-mappings', [OBEController::class, 'mapOutcomePath'])->middleware('permission:obe.manage,tenant');
    Route::post('obe/assessment-mappings', [OBEController::class, 'mapAssessment'])->middleware('permission:obe.manage,tenant');
    Route::get('obe/outcome-matrix', [OBEController::class, 'matrix'])->middleware('permission:obe.view,tenant');
    Route::get('obe/coverage-analysis', [OBEController::class, 'coverage'])->middleware('permission:obe.view,tenant');
    Route::post('obe/recalculate-achievement', [OBEController::class, 'recalculate'])->middleware('permission:obe.analytics,tenant');
    Route::get('obe/dashboard', [OBEController::class, 'dashboard'])->middleware('permission:obe.analytics,tenant');
    Route::get('obe/accreditation-reports', [OBEController::class, 'reports'])->middleware('permission:accreditation.report,tenant');
    Route::post('obe/accreditation-reports', [OBEController::class, 'generateReport'])->middleware('permission:accreditation.report,tenant');

    Route::prefix('enrollment')->group(function () {
        Route::get('cohorts', [EnrollmentController::class, 'cohorts'])->middleware('permission:enrollment.view,tenant');
        Route::post('cohorts', [EnrollmentController::class, 'storeCohort'])->middleware('permission:enrollment.manage,tenant');
        Route::post('cohorts/{cohort}/groups', [EnrollmentController::class, 'storeCohortGroup'])->middleware('permission:enrollment.manage,tenant');
        Route::post('cohorts/{cohort}/rules', [EnrollmentController::class, 'storeCohortRule'])->middleware('permission:enrollment.manage,tenant');
        Route::get('sections', [EnrollmentController::class, 'sections'])->middleware('permission:enrollment.view,tenant');
        Route::post('sections', [EnrollmentController::class, 'storeSection'])->middleware('permission:enrollment.manage,tenant');
        Route::post('sections/{section}/self-enroll', [EnrollmentController::class, 'selfEnroll'])->middleware('permission:enrollment.self,tenant');
        Route::post('sections/{section}/invite', [EnrollmentController::class, 'invite'])->middleware('permission:enrollment.manage,tenant');
        Route::post('sections/{section}/teachers', [EnrollmentController::class, 'assignTeacher'])->middleware('permission:enrollment.assign_teacher,tenant');
        Route::get('records', [EnrollmentController::class, 'enrollments'])->middleware('permission:enrollment.view,tenant');
        Route::post('records', [EnrollmentController::class, 'manualEnroll'])->middleware('permission:enrollment.manage,tenant');
        Route::patch('records/{enrollment}', [EnrollmentController::class, 'updateEnrollment'])->middleware('permission:enrollment.manage,tenant');
        Route::post('records/bulk', [EnrollmentController::class, 'bulkEnroll'])->middleware('permission:enrollment.manage,tenant');
        Route::post('records/bulk-action', [EnrollmentController::class, 'bulkAction'])->middleware('permission:enrollment.manage,tenant');
        Route::post('records/{enrollment}/transition', [EnrollmentController::class, 'transition'])->middleware('permission:enrollment.manage,tenant');
        Route::post('imports', [EnrollmentController::class, 'import'])->middleware('permission:enrollment.import,tenant');
        Route::get('imports/{job}', [EnrollmentController::class, 'importShow'])->middleware('permission:enrollment.import,tenant');
        Route::get('analytics', [EnrollmentController::class, 'analytics'])->middleware('permission:enrollment.analytics,tenant');
    });

    Route::prefix('analytics')->group(function () {
        Route::get('dashboard', [LearningAnalyticsController::class, 'dashboard'])->middleware('permission:analytics.dashboard,tenant');
        Route::get('metrics', [LearningAnalyticsController::class, 'metrics'])->middleware('permission:analytics.dashboard,tenant');
        Route::get('risks', [LearningAnalyticsController::class, 'risks'])->middleware('permission:analytics.risk,tenant');
        Route::post('risks/calculate', [LearningAnalyticsController::class, 'calculateRisk'])->middleware('permission:analytics.risk,tenant');
        Route::get('alerts', [LearningAnalyticsController::class, 'alerts'])->middleware('permission:analytics.alerts,tenant');
        Route::post('alerts/{alert}/acknowledge', [LearningAnalyticsController::class, 'acknowledge'])->middleware('permission:analytics.alerts,tenant');
        Route::post('alerts/{alert}/resolve', [LearningAnalyticsController::class, 'resolve'])->middleware('permission:analytics.alerts,tenant');
        Route::post('summaries/build', [LearningAnalyticsController::class, 'buildSummary'])->middleware('permission:analytics.warehouse,tenant');
    });

    Route::prefix('surveys')->group(function () {
        Route::get('forms', [SurveyController::class, 'forms'])->middleware('permission:survey.view,tenant');
        Route::post('forms', [SurveyController::class, 'storeForm'])->middleware('permission:survey.manage,tenant');
        Route::get('forms/{form}', [SurveyController::class, 'showForm'])->middleware('permission:survey.view,tenant');
        Route::put('forms/{form}', [SurveyController::class, 'updateForm'])->middleware('permission:survey.manage,tenant');
        Route::post('forms/{form}/reorder-questions', [SurveyController::class, 'reorderQuestions'])->middleware('permission:survey.manage,tenant');
        Route::get('campaigns', [SurveyController::class, 'campaigns'])->middleware('permission:survey.view,tenant');
        Route::post('campaigns', [SurveyController::class, 'storeCampaign'])->middleware('permission:survey.manage,tenant');
        Route::get('campaigns/{campaign}', [SurveyController::class, 'showCampaign'])->middleware('permission:survey.view,tenant');
        Route::post('campaigns/{campaign}/launch', [SurveyController::class, 'launch'])->middleware('permission:survey.manage,tenant');
        Route::post('campaigns/{campaign}/close', [SurveyController::class, 'close'])->middleware('permission:survey.manage,tenant');
        Route::post('campaigns/{campaign}/responses', [SurveyController::class, 'submitResponse'])->middleware('permission:survey.respond,tenant');
        Route::get('campaigns/{campaign}/export', [SurveyController::class, 'export'])->middleware('permission:survey.export,tenant');
        Route::get('analytics', [SurveyController::class, 'analytics'])->middleware('permission:survey.analytics,tenant');
        Route::get('improvements', [SurveyController::class, 'improvements'])->middleware('permission:improvement.view,tenant');
        Route::post('improvements', [SurveyController::class, 'storeImprovement'])->middleware('permission:improvement.manage,tenant');
        Route::put('improvements/{improvement}', [SurveyController::class, 'updateImprovement'])->middleware('permission:improvement.manage,tenant');
        Route::get('evidence', [SurveyController::class, 'evidence'])->middleware('permission:accreditation.evidence.view,tenant');
        Route::post('evidence', [SurveyController::class, 'storeEvidence'])->middleware('permission:accreditation.evidence.manage,tenant');
    });

    Route::prefix('community')->group(function () {
        Route::get('forums', [LearningCommunityController::class, 'forums'])->middleware('permission:community.view,tenant');
        Route::post('forums', [LearningCommunityController::class, 'storeForum'])->middleware('permission:community.manage,tenant');
        Route::get('forums/{forum}/threads', [LearningCommunityController::class, 'threads'])->middleware('permission:community.view,tenant');
        Route::post('forums/{forum}/threads', [LearningCommunityController::class, 'storeThread'])->middleware('permission:community.post,tenant');
        Route::get('threads/{thread}', [LearningCommunityController::class, 'showThread'])->middleware('permission:community.view,tenant');
        Route::post('threads/{thread}/replies', [LearningCommunityController::class, 'reply'])->middleware('permission:community.post,tenant');
        Route::post('posts/{post}/react', [LearningCommunityController::class, 'react'])->middleware('permission:community.post,tenant');
        Route::post('posts/{post}/mark-correct', [LearningCommunityController::class, 'markCorrect'])->middleware('permission:community.moderate,tenant');
        Route::post('moderation', [LearningCommunityController::class, 'moderate'])->middleware('permission:community.moderate,tenant');
        Route::post('reports', [LearningCommunityController::class, 'report'])->middleware('permission:community.post,tenant');
        Route::get('reports', [LearningCommunityController::class, 'reports'])->middleware('permission:community.moderate,tenant');
        Route::get('wikis', [LearningCommunityController::class, 'wikis'])->middleware('permission:community.wiki,tenant');
        Route::post('wikis', [LearningCommunityController::class, 'storeWiki'])->middleware('permission:community.wiki,tenant');
        Route::put('wikis/{wiki}', [LearningCommunityController::class, 'updateWiki'])->middleware('permission:community.wiki,tenant');
        Route::get('blogs', [LearningCommunityController::class, 'blogs'])->middleware('permission:community.blog,tenant');
        Route::post('blogs', [LearningCommunityController::class, 'storeBlog'])->middleware('permission:community.blog,tenant');
        Route::get('groups', [LearningCommunityController::class, 'groups'])->middleware('permission:community.group,tenant');
        Route::post('groups', [LearningCommunityController::class, 'storeGroup'])->middleware('permission:community.group,tenant');
        Route::get('notifications', [LearningCommunityController::class, 'notifications'])->middleware('permission:community.view,tenant');
        Route::get('reputation', [LearningCommunityController::class, 'reputation'])->middleware('permission:community.reputation,tenant');
        Route::get('threads/{thread}/ai', [LearningCommunityController::class, 'ai'])->middleware('permission:ai.use,tenant');
        Route::get('analytics', [LearningCommunityController::class, 'analytics'])->middleware('permission:community.analytics,tenant');
    });

    Route::prefix('ai')->middleware('permission:ai.use,tenant')->group(function () {
        Route::get('documents', [AiLearningController::class, 'documents']);
        Route::post('ingest', [AiLearningController::class, 'ingest']);
        Route::post('ask', [AiLearningController::class, 'ask']);
        Route::post('summary', [AiLearningController::class, 'summary']);
        Route::post('quizzes', [AiLearningController::class, 'quiz']);
        Route::post('flashcards', [AiLearningController::class, 'flashcards']);
        Route::get('coach', [AiLearningController::class, 'coach']);
        Route::get('outcomes', [AiLearningController::class, 'outcomes']);
        Route::get('analytics', [AiLearningController::class, 'analytics']);
    });

    Route::post('forums', [LearningCommunityController::class, 'storeForum'])->middleware('permission:community.manage,tenant');
});
