<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\AlertEngineService;
use App\Services\AnalyticsSnapshotService;
use App\Services\ForecastService;
use App\Services\LmsSystemService;
use App\Services\LmsActionRegistryService;
use App\Models\Tenant;
use App\Services\DataIntegrityService;
use App\Models\DataIntegrityIssue;

Artisan::command('eralms:about', function (): void {
    $this->info('EraLMS runtime is installed.');
})->purpose('Show EraLMS runtime status');

Artisan::command('lms:system-check {--smoke}', function (LmsSystemService $service): void {
    $result = $service->systemCheck((bool) $this->option('smoke'));
    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
})->purpose('Run EraLMS route, menu, permission and API diagnostics');

Artisan::command('lms:sync-permissions', function (LmsSystemService $service): void {
    $result = $service->syncPermissions();
    $this->info("Synced {$result['permissions']} permissions.");
})->purpose('Sync LMS permission keys from menu and route middleware');

Artisan::command('lms:rebuild-menu', function (LmsSystemService $service): void {
    $result = $service->rebuildMenu();
    $this->info("Rebuilt {$result['menu']} menu items.");
})->purpose('Rebuild LMS menu setting from config');

Artisan::command('lms:grant-super-admin {email}', function (string $email, LmsSystemService $service): void {
    $result = $service->grantSuperAdmin($email);
    $this->info("Granted {$result['role']} to {$result['email']}.");
})->purpose('Grant full LMS super admin permissions to an email');

Artisan::command('lms:smoke-test-ui', function (LmsSystemService $service): void {
    $result = $service->smokeTestUi();
    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
})->purpose('Run LMS UI/menu/API smoke diagnostics');

Artisan::command('lms:scan-actions', function (LmsActionRegistryService $service): void {
    $this->line(json_encode($service->scanActions(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
})->purpose('Scan LMS action registry bindings');

Artisan::command('lms:sync-actions', function (LmsActionRegistryService $service): void {
    $result = $service->syncActions();
    $this->info("Synced {$result['total']} actions ({$result['created']} created, {$result['updated']} updated).");
})->purpose('Sync LMS action registry');

Artisan::command('lms:check-routes', function (LmsActionRegistryService $service): void {
    $scan = $service->scanActions();
    $issues = collect($scan['actions'])->where('status', 'Missing route')->values();
    $this->line(json_encode(['missing_routes' => $issues], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
})->purpose('Check action registry routes');

Artisan::command('lms:check-permissions', function (LmsActionRegistryService $service): void {
    $scan = $service->scanActions();
    $issues = collect($scan['actions'])->where('status', 'Missing permission')->values();
    $this->line(json_encode(['missing_permissions' => $issues], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
})->purpose('Check action registry permissions');

Artisan::command('lms:grant-admin-actions {email}', function (string $email, LmsSystemService $system, LmsActionRegistryService $actions): void {
    $actions->syncActions();
    $actions->syncPermissions();
    $result = $system->grantSuperAdmin($email);
    $this->info("Granted {$result['role']} action permissions to {$result['email']}.");
})->purpose('Grant all LMS action permissions to admin user');

Artisan::command('lms:smoke-actions', function (LmsActionRegistryService $service): void {
    $service->syncActions();
    $service->syncPermissions();
    $this->line(json_encode($service->smokeActions(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
})->purpose('Run LMS action flow smoke diagnostics');

Artisan::command('lms:analytics-build-daily {--tenant_id=}', function (AnalyticsSnapshotService $service): void {
    $result = $service->buildDailySnapshots($this->option('tenant_id') ? (int) $this->option('tenant_id') : null);
    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
})->purpose('Build daily dashboard analytics snapshots');

Artisan::command('lms:analytics-build-weekly {--tenant_id=}', function (AnalyticsSnapshotService $service): void {
    $result = $service->buildWeeklySnapshots($this->option('tenant_id') ? (int) $this->option('tenant_id') : null);
    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
})->purpose('Build weekly dashboard analytics snapshots');

Artisan::command('lms:analytics-rebuild {--scope=tenant} {--tenant_id=}', function (AnalyticsSnapshotService $service): void {
    $result = $service->rebuildByScope((string) $this->option('scope'), $this->option('tenant_id') ? (int) $this->option('tenant_id') : null);
    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
})->purpose('Rebuild dashboard analytics snapshots by scope');

Artisan::command('lms:forecast-build {--tenant_id=}', function (ForecastService $service): void {
    $tenantIds = Tenant::query()
        ->where('status', 'active')
        ->when($this->option('tenant_id'), fn ($query) => $query->where('id', (int) $this->option('tenant_id')))
        ->pluck('id');

    $result = [];
    foreach ($tenantIds as $tenantId) {
        $result[$tenantId] = [
            'course_completion' => count($service->calculateCourseCompletionForecast((int) $tenantId)),
            'risk' => count($service->calculateRiskForecast((int) $tenantId)),
            'exam_load' => count($service->calculateExamLoadForecast((int) $tenantId)),
            'grading_backlog' => count($service->calculateGradingBacklogForecast((int) $tenantId)),
        ];
    }

    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
})->purpose('Build baseline dashboard analytics forecasts');

Artisan::command('lms:alerts-detect {--tenant_id=}', function (AlertEngineService $service): void {
    $tenantIds = Tenant::query()
        ->where('status', 'active')
        ->when($this->option('tenant_id'), fn ($query) => $query->where('id', (int) $this->option('tenant_id')))
        ->pluck('id');

    $result = [];
    foreach ($tenantIds as $tenantId) {
        $alerts = array_merge(
            $service->detectHighRiskLearners((int) $tenantId),
            $service->detectClassBehindSchedule((int) $tenantId),
            $service->detectLowAttendance((int) $tenantId),
            $service->detectPendingGradeApproval((int) $tenantId),
            $service->detectSyncFailures((int) $tenantId)
        );
        $result[$tenantId] = count($alerts);
    }

    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
})->purpose('Detect dashboard analytics alerts');

Artisan::command('lms:dashboard-health {--tenant_id=}', function (): void {
    $tenantIds = Tenant::query()
        ->where('status', 'active')
        ->when($this->option('tenant_id'), fn ($query) => $query->where('id', (int) $this->option('tenant_id')))
        ->pluck('id');

    $result = [];
    foreach ($tenantIds as $tenantId) {
        $latest = \App\Models\DashboardMetricSnapshot::query()
            ->where('tenant_id', $tenantId)
            ->max('snapshot_date');
        $result[$tenantId] = [
            'latest_snapshot_date' => $latest,
            'metric_rows' => \App\Models\DashboardMetricSnapshot::query()->where('tenant_id', $tenantId)->count(),
            'open_alerts' => \App\Models\AnalyticsAlert::query()->where('tenant_id', $tenantId)->where('status', 'open')->count(),
            'status' => $latest ? 'ok' : 'missing',
        ];
    }

    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
})->purpose('Check dashboard summary freshness and alert health');

Schedule::command('lms:analytics-build-daily')->dailyAt('01:00');
Schedule::command('lms:alerts-detect')->hourly();
Schedule::command('lms:forecast-build')->dailyAt('02:00');
Schedule::command('lms:dashboard-health')->everyFifteenMinutes();

Artisan::command('lms:integrity-check {--module=} {--tenant-id=}', function (DataIntegrityService $service): void {
    $tenantId = $this->option('tenant-id') ? (int) $this->option('tenant-id') : null;
    $module = $this->option('module');
    $result = $module
        ? $service->runModuleChecks((string) $module, $tenantId ?: (int) \App\Models\Tenant::query()->value('id'))
        : $service->runAllChecks($tenantId);
    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
})->purpose('Run EraLMS data integrity checks');

Artisan::command('lms:integrity-fix {--tenant-id=}', function (DataIntegrityService $service): void {
    $tenantId = $this->option('tenant-id') ? (int) $this->option('tenant-id') : null;
    $query = DataIntegrityIssue::query()->where('status', 'open')->where('auto_fix_available', true);
    if ($tenantId) {
        $query->where('tenant_id', $tenantId);
    }
    $fixed = 0;
    foreach ($query->get() as $issue) {
        $service->autoFixIssue($issue);
        $fixed++;
    }
    $this->info("Fixed {$fixed} integrity issue(s).");
})->purpose('Auto-fix available EraLMS data integrity issues');

Artisan::command('lms:integrity-report {--tenant-id=}', function (DataIntegrityService $service): void {
    $tenantId = $this->option('tenant-id') ? (int) $this->option('tenant-id') : (int) \App\Models\Tenant::query()->value('id');
    $this->line(json_encode($service->exportReport($tenantId), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
})->purpose('Export EraLMS data integrity report as JSON');

Artisan::command('api-ops:build-snapshots {--tenant-id=}', function (\App\Services\ApiOperations\ApiHealthService $health): void {
    $tenantId = $this->option('tenant-id') ? (int) $this->option('tenant-id') : null;
    $this->line(json_encode($health->buildSnapshots($tenantId), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
})->purpose('Build API Operations health and dashboard snapshots');

Artisan::command('api-ops:health-check {--tenant-id=}', function (\App\Services\ApiOperations\ApiHealthService $health): void {
    $tenantId = $this->option('tenant-id') ? (int) $this->option('tenant-id') : null;
    $result = $health->buildSnapshots($tenantId);
    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
})->purpose('Check API system and endpoint health now');

Artisan::command('api-ops:retry-failed {--tenant-id=}', function (\App\Services\ApiOperations\EventBusService $events, \App\Services\ApiOperations\WebhookService $webhooks): void {
    $tenantIds = \App\Models\Tenant::query()
        ->when($this->option('tenant-id'), fn ($query) => $query->where('id', (int) $this->option('tenant-id')))
        ->pluck('id');

    $result = [];
    foreach ($tenantIds as $tenantId) {
        $result[$tenantId] = [
            'events' => $events->retryFailed((int) $tenantId),
            'webhooks' => $webhooks->retryFailedWebhook(),
        ];
    }

    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
})->purpose('Retry failed API events and webhook deliveries');

Artisan::command('api-ops:cleanup-logs {--tenant-id=} {--days=30}', function (\App\Services\ApiOperations\ApiHealthService $health): void {
    $tenantIds = \App\Models\Tenant::query()
        ->when($this->option('tenant-id'), fn ($query) => $query->where('id', (int) $this->option('tenant-id')))
        ->pluck('id');

    $result = [];
    foreach ($tenantIds as $tenantId) {
        $result[$tenantId] = $health->cleanupLogs((int) $tenantId, (int) $this->option('days'));
    }

    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
})->purpose('Clean old API request logs');

Artisan::command('api-ops:system-check {--tenant-id=}', function (\App\Services\ApiOperations\ApiHealthService $health): void {
    $tenantId = $this->option('tenant-id') ? (int) $this->option('tenant-id') : (int) \App\Models\Tenant::query()->value('id');
    $dashboard = $health->dashboard($tenantId);
    $result = [
        'tenant_id' => $tenantId,
        'systems' => \App\Models\ApiSystem::query()->where('tenant_id', $tenantId)->count(),
        'endpoints' => \App\Models\ApiEndpoint::query()->where('tenant_id', $tenantId)->count(),
        'pending_events' => $dashboard['cards']['event_pending'] ?? 0,
        'failed_sync_jobs' => $dashboard['cards']['sync_jobs_failed'] ?? 0,
        'failed_webhooks' => $dashboard['cards']['webhook_failed'] ?? 0,
        'status' => ($dashboard['cards']['down'] ?? 0) > 0 ? 'attention_required' : 'ok',
    ];

    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
})->purpose('Run API Operations Center diagnostics');
