<?php

use App\Http\Controllers\Api\V1\CoreController;
use App\Http\Controllers\Api\V1\Core\RbacController;
use App\Http\Controllers\Api\V1\Core\UserController;
use App\Http\Controllers\Api\V1\Core\WhiteLabelController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\LearningPathController;
use App\Http\Controllers\Api\V1\RepositoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['api', 'tenant.resolve'])->group(function () {
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
    Route::get('courses/{course}/studio', [CourseController::class, 'studio']);
    Route::post('courses/{course}/sections', [CourseController::class, 'storeSection']);
    Route::put('course-sections/{section}', [CourseController::class, 'updateSection']);
    Route::delete('course-sections/{section}', [CourseController::class, 'deleteSection']);
    Route::post('course-sections/reorder', [CourseController::class, 'reorderSections']);
    Route::post('course-components', [CourseController::class, 'storeComponent']);
    Route::put('course-components/{component}', [CourseController::class, 'updateComponent']);
    Route::delete('course-components/{component}', [CourseController::class, 'deleteComponent']);
    Route::post('course-components/reorder', [CourseController::class, 'reorderComponents']);

    Route::prefix('repository')->group(function () {
        Route::get('items', [RepositoryController::class, 'index']);
        Route::get('tree', [RepositoryController::class, 'tree']);
        Route::get('items/{item}', [RepositoryController::class, 'show']);
        Route::post('folders', [RepositoryController::class, 'storeFolder']);
        Route::post('upload', [RepositoryController::class, 'upload']);
        Route::put('items/{item}', [RepositoryController::class, 'update']);
        Route::post('items/{item}/new-version', [RepositoryController::class, 'newVersion']);
        Route::post('items/{item}/move', [RepositoryController::class, 'move']);
        Route::post('items/{item}/copy', [RepositoryController::class, 'copy']);
        Route::post('items/{item}/share', [RepositoryController::class, 'share']);
        Route::get('items/{item}/versions', [RepositoryController::class, 'versions']);
        Route::get('items/{item}/download-url', [RepositoryController::class, 'downloadUrl']);
        Route::post('items/{item}/submit-review', [RepositoryController::class, 'submitReview']);
        Route::post('items/{item}/approve', [RepositoryController::class, 'approve']);
        Route::post('items/{item}/reject', [RepositoryController::class, 'reject']);
        Route::post('items/{item}/return', [RepositoryController::class, 'returnForEdit']);
    });

    Route::get('courses/{course}/learning-path/rules', [LearningPathController::class, 'rules']);
    Route::post('courses/{course}/learning-path/rules', [LearningPathController::class, 'storeRule']);
    Route::put('learning-path/rules/{rule}', [LearningPathController::class, 'updateRule']);
    Route::delete('learning-path/rules/{rule}', [LearningPathController::class, 'deleteRule']);
    Route::get('courses/{course}/progress/me', [LearningPathController::class, 'myProgress']);
    Route::get('courses/{course}/progress/users', [LearningPathController::class, 'userProgress']);
    Route::post('learning-events', [LearningPathController::class, 'appendEvent']);
    Route::post('components/{component}/start', [LearningPathController::class, 'startComponent']);
    Route::post('components/{component}/progress', [LearningPathController::class, 'componentProgress']);
    Route::post('components/{component}/complete', [LearningPathController::class, 'completeComponent']);
    Route::get('components/{component}/access-check', [LearningPathController::class, 'accessCheck']);
    Route::post('completions/{completion}/request-approval', [LearningPathController::class, 'requestApproval']);
    Route::post('completions/{completion}/approve', [LearningPathController::class, 'approveCompletion']);
    Route::post('completions/{completion}/reject', [LearningPathController::class, 'rejectCompletion']);
});
