<?php

use Illuminate\Support\Facades\Artisan;
use App\Services\LmsSystemService;
use App\Services\LmsActionRegistryService;

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
