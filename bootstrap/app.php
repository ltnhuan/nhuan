<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\ApiPerformanceLogger;
use App\Http\Middleware\TenantResolver;
use App\Providers\AppServiceProvider;
use App\Services\TenantContext;
use App\Support\ApiResponse;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Cookie\CookieServiceProvider;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\MigrationServiceProvider;
use Illuminate\Encryption\EncryptionServiceProvider;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Providers\ConsoleSupportServiceProvider;
use Illuminate\Foundation\Providers\FoundationServiceProvider;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Session\SessionServiceProvider;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        FilesystemServiceProvider::class,
        FoundationServiceProvider::class,
        CookieServiceProvider::class,
        EventServiceProvider::class,
        CacheServiceProvider::class,
        DatabaseServiceProvider::class,
        MigrationServiceProvider::class,
        EncryptionServiceProvider::class,
        QueueServiceProvider::class,
        SessionServiceProvider::class,
        TranslationServiceProvider::class,
        ViewServiceProvider::class,
        ConsoleSupportServiceProvider::class,
        AppServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.performance' => ApiPerformanceLogger::class,
            'tenant.resolve' => TenantResolver::class,
            'permission' => CheckPermission::class,
        ]);
    })
    ->withSingletons([
        TenantContext::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $exception, $request) {
            return $request->expectsJson() || str_starts_with($request->path(), 'api/')
                ? ApiResponse::validationError($exception->errors(), $exception->getMessage())
                : null;
        });

        $exceptions->render(function (AuthorizationException $exception, $request) {
            return $request->expectsJson() || str_starts_with($request->path(), 'api/')
                ? ApiResponse::forbidden($exception->getMessage())
                : null;
        });

        $exceptions->render(function (ModelNotFoundException $exception, $request) {
            return $request->expectsJson() || str_starts_with($request->path(), 'api/')
                ? ApiResponse::notFound()
                : null;
        });

        $exceptions->render(function (HttpExceptionInterface $exception, $request) {
            if (! ($request->expectsJson() || str_starts_with($request->path(), 'api/'))) {
                return null;
            }

            return match ($exception->getStatusCode()) {
                401 => ApiResponse::error($exception->getMessage() ?: 'Chưa đăng nhập.', [], 'UNAUTHENTICATED', 401),
                403 => ApiResponse::forbidden($exception->getMessage() ?: 'Bạn không có quyền thực hiện thao tác này'),
                404 => ApiResponse::notFound($exception->getMessage() ?: 'Không tìm thấy dữ liệu'),
                default => ApiResponse::error($exception->getMessage() ?: 'Không thể thực hiện thao tác', [], 'HTTP_ERROR', $exception->getStatusCode()),
            };
        });

        $exceptions->render(function (\InvalidArgumentException $exception, $request) {
            return $request->expectsJson() || str_starts_with($request->path(), 'api/')
                ? ApiResponse::error($exception->getMessage())
                : null;
        });

        $exceptions->render(function (\Throwable $exception, $request) {
            return $request->expectsJson() || str_starts_with($request->path(), 'api/')
                ? ApiResponse::serverError($exception->getMessage())
                : null;
        });
    })
    ->create();
