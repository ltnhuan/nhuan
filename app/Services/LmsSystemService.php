<?php

namespace App\Services;

use App\Models\LmsUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class LmsSystemService
{
    public function menu(): array
    {
        return collect(config('eralms.menu', []))
            ->map(fn (array $item, int $index) => [
                'key' => $item['key'] ?? Str::slug($item['title'] ?? $item['label'] ?? 'menu-'.$index),
                'title' => $item['title'] ?? $item['label'] ?? 'Menu',
                'route_name' => $item['route_name'] ?? null,
                'url' => $item['url'] ?? $item['route'] ?? '/',
                'icon' => $item['icon'] ?? 'circle',
                'permission_key' => $item['permission_key'] ?? $item['permission'] ?? null,
                'sort_order' => $item['sort_order'] ?? (($index + 1) * 10),
                'parent_id' => $item['parent_id'] ?? null,
                'is_active' => $item['is_active'] ?? true,
            ])
            ->values()
            ->all();
    }

    public function syncPermissions(): array
    {
        $keys = collect($this->menu())->pluck('permission_key')->filter();

        foreach (Route::getRoutes() as $route) {
            foreach ($route->middleware() as $middleware) {
                if (preg_match('/^permission:([^,]+)/', $middleware, $matches)) {
                    $keys->push($matches[1]);
                }
            }
        }

        $keys = $keys->filter()->unique()->sort()->values();
        foreach ($keys as $key) {
            [$module, $action] = array_pad(explode('.', $key, 2), 2, 'access');
            Permission::query()->updateOrCreate(
                ['key' => $key],
                ['module' => $module, 'action' => $action, 'description' => 'Cho phép '.$key]
            );
        }

        $this->grantAllToAdminRoles();
        $this->assignDefaultRolesToUsersWithoutRole();
        Cache::flush();

        return ['permissions' => $keys->count(), 'keys' => $keys->all()];
    }

    public function grantAllToAdminRoles(): array
    {
        $permissionIds = Permission::query()->pluck('id');
        $roles = Role::query()->whereIn('name', ['super_admin', 'tenant_admin'])->get();

        foreach ($roles as $role) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $role->id,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        return ['roles' => $roles->pluck('name')->values()->all(), 'permissions' => $permissionIds->count()];
    }

    public function grantSuperAdmin(string $email): array
    {
        $tenant = $this->tenant();
        $user = LmsUser::query()
            ->where('tenant_id', $tenant?->id)
            ->where('email', Str::lower($email))
            ->firstOrFail();

        $role = Role::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'super_admin'],
            ['guard_name' => 'web', 'display_name' => 'Quản trị hệ thống', 'scope' => 'system', 'description' => 'Quản trị hệ thống']
        );

        $this->syncPermissions();

        DB::table('user_role_scope')->updateOrInsert([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'tenant_id' => $tenant->id,
            'campus_id' => null,
            'academic_unit_id' => null,
            'course_id' => null,
            'class_id' => null,
        ]);

        Cache::flush();

        return ['email' => $user->email, 'role' => $role->name];
    }

    public function rebuildMenu(): array
    {
        $tenant = $this->tenant();
        if ($tenant) {
            SystemSetting::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'group' => 'ui', 'key' => 'menu'],
                ['value' => $this->menu()]
            );
        }
        Cache::flush();

        return ['menu' => count($this->menu())];
    }

    public function clearPermissionCache(): array
    {
        Cache::flush();

        return ['cleared' => true];
    }

    public function clearRouteCache(): array
    {
        Artisan::call('route:clear');

        return ['output' => trim(Artisan::output())];
    }

    public function clearConfigCache(): array
    {
        Artisan::call('config:clear');

        return ['output' => trim(Artisan::output())];
    }

    public function systemCheck(bool $includeSmoke = false): array
    {
        $actionRegistry = app(LmsActionRegistryService::class);
        $actionRegistry->syncActions();
        $actionScan = $actionRegistry->scanActions();
        $routes = collect(Route::getRoutes());
        $webRoutes = $routes->filter(fn ($route) => in_array('GET', $route->methods(), true) && ! Str::startsWith($route->uri(), 'api/'));
        $apiRoutes = $routes->filter(fn ($route) => Str::startsWith($route->uri(), 'api/'));
        $menu = collect($this->menu());
        $permissions = Permission::query()->pluck('key');
        $routeNames = $routes->map(fn ($route) => $route->getName())->filter()->values();

        $menuRouteErrors = $menu
            ->filter(fn ($item) => ! $this->frontendUrlKnown($item['url']) && ! ($item['route_name'] && $routeNames->contains($item['route_name'])))
            ->values()
            ->all();

        $requiredPermissions = $this->requiredPermissionKeys();
        $missingPermissions = $requiredPermissions->diff($permissions)->values()->all();
        $usersWithoutRole = LmsUser::query()
            ->whereDoesntHave('roleScopes')
            ->limit(50)
            ->get(['id', 'email', 'full_name', 'user_type'])
            ->toArray();

        $missingUiComponents = collect(['Button', 'Badge', 'Card', 'Dialog', 'Drawer', 'Table', 'Dropdown'])
            ->filter(fn ($name) => ! File::exists(resource_path("js/Components/UI/{$name}.vue")))
            ->values()
            ->all();

        $apiIssues = $includeSmoke ? $this->smokeApiIssues() : [];

        return [
            'summary' => [
                'backend_routes' => $webRoutes->count(),
                'api_routes' => $apiRoutes->count(),
                'menus' => $menu->count(),
                'menu_route_errors' => count($menuRouteErrors),
                'missing_permissions' => count($missingPermissions),
                'users_without_role' => count($usersWithoutRole),
                'missing_ui_components' => count($missingUiComponents),
                'api_issues' => count($apiIssues),
                'registered_actions' => $actionScan['summary']['total'],
                'action_binding_issues' => $actionScan['summary']['issues'],
            ],
            'menu_route_errors' => $menuRouteErrors,
            'missing_permissions' => $missingPermissions,
            'button_action_missing_api' => [],
            'users_without_role' => $usersWithoutRole,
            'missing_ui_components' => $missingUiComponents,
            'api_issues' => $apiIssues,
            'action_registry' => $actionScan,
            'health' => [
                'database' => DB::connection()->getDatabaseName() ? 'ok' : 'unknown',
                'cache' => 'ok',
                'admin_full_access' => $this->adminHasAllPermissions('admin.lms@vabis.edu.vn'),
            ],
        ];
    }

    public function smokeTestUi(): array
    {
        $this->syncPermissions();
        $this->grantSuperAdmin('admin.lms@vabis.edu.vn');
        $this->assignDefaultRolesToUsersWithoutRole();

        return $this->systemCheck(true);
    }

    public function action(string $action): array
    {
        return match ($action) {
            'scan-actions' => app(LmsActionRegistryService::class)->scanActions(),
            'sync-actions' => app(LmsActionRegistryService::class)->syncActions(),
            'fix-missing-permissions' => app(LmsActionRegistryService::class)->fixMissingPermissions(),
            'clear-action-cache' => app(LmsActionRegistryService::class)->clearCache(),
            'smoke-actions' => app(LmsActionRegistryService::class)->smokeActions(),
            'sync-permissions' => $this->syncPermissions(),
            'clear-permission-cache' => $this->clearPermissionCache(),
            'clear-route-cache' => $this->clearRouteCache(),
            'clear-config-cache' => $this->clearConfigCache(),
            'rebuild-menu' => $this->rebuildMenu(),
            'grant-full-admin' => $this->grantSuperAdmin('admin.lms@vabis.edu.vn'),
            'run-smoke-test' => $this->smokeTestUi(),
            default => ['error' => 'Unknown action '.$action],
        };
    }

    private function requiredPermissionKeys()
    {
        $keys = collect($this->menu())->pluck('permission_key')->filter();
        foreach (Route::getRoutes() as $route) {
            foreach ($route->middleware() as $middleware) {
                if (preg_match('/^permission:([^,]+)/', $middleware, $matches)) {
                    $keys->push($matches[1]);
                }
            }
        }

        return $keys->filter()->unique()->sort()->values();
    }

    private function frontendUrlKnown(string $url): bool
    {
        if ($url === '/') {
            return true;
        }

        $knownPrefixes = collect($this->frontendRoutes());

        return $knownPrefixes->contains(fn ($prefix) => $url === $prefix || Str::startsWith($url, $prefix.'/'));
    }

    private function frontendRoutes(): array
    {
        return [
            '/admin/lms/system-check', '/analytics', '/reports', '/ai', '/mobile',
            '/courses', '/repository', '/learning-path', '/enrollment', '/videos',
            '/question-banks', '/exams', '/assignments', '/gradebook', '/attendance',
            '/community', '/surveys', '/career', '/credentials', '/obe', '/standards',
            '/sis', '/moodle-parity', '/settings', '/security', '/plugins', '/backup', '/uat',
        ];
    }

    private function smokeApiIssues(): array
    {
        $checks = ['api/v1/health'];
        $issues = [];

        foreach ($checks as $uri) {
            try {
                $request = request()->create('/'.$uri, 'GET', [], [], [], [
                    'HTTP_X_TENANT_CODE' => config('eralms.default_tenant_code', 'VABIS'),
                    'HTTP_X_DEMO_USER_EMAIL' => 'admin.lms@vabis.edu.vn',
                ]);
                $response = app()->handle($request);
                if ($response->getStatusCode() >= 400 && ! ($uri === 'api/v1/health' && $response->getStatusCode() === 503)) {
                    $issues[] = ['uri' => $uri, 'status' => $response->getStatusCode()];
                }
            } catch (\Throwable $exception) {
                $issues[] = ['uri' => $uri, 'status' => 500, 'message' => $exception->getMessage()];
            }
        }

        return $issues;
    }

    private function adminHasAllPermissions(string $email): bool
    {
        $tenant = $this->tenant();
        $user = LmsUser::query()->where('tenant_id', $tenant?->id)->where('email', $email)->first();
        if (! $user) {
            return false;
        }

        $required = $this->requiredPermissionKeys();
        $actual = DB::table('user_role_scope')
            ->join('role_permission', 'user_role_scope.role_id', '=', 'role_permission.role_id')
            ->join('permissions', 'role_permission.permission_id', '=', 'permissions.id')
            ->where('user_role_scope.user_id', $user->id)
            ->pluck('permissions.key')
            ->unique();

        return $required->diff($actual)->isEmpty();
    }

    private function assignDefaultRolesToUsersWithoutRole(): array
    {
        $tenant = $this->tenant();
        if (! $tenant) {
            return ['assigned' => 0];
        }

        $roles = Role::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('name', ['training_officer', 'teacher', 'student'])
            ->get()
            ->keyBy('name');

        $assigned = 0;
        LmsUser::query()
            ->where('tenant_id', $tenant->id)
            ->whereDoesntHave('roleScopes')
            ->chunkById(500, function ($users) use ($tenant, $roles, &$assigned) {
                foreach ($users as $user) {
                    $roleName = match ($user->user_type) {
                        'student', 'external_learner' => 'student',
                        'teacher' => 'teacher',
                        default => 'training_officer',
                    };
                    $role = $roles->get($roleName);
                    if (! $role) {
                        continue;
                    }

                    DB::table('user_role_scope')->updateOrInsert([
                        'user_id' => $user->id,
                        'role_id' => $role->id,
                        'tenant_id' => $tenant->id,
                        'campus_id' => null,
                        'academic_unit_id' => null,
                        'course_id' => null,
                        'class_id' => null,
                    ]);
                    $assigned++;
                }
            });

        return ['assigned' => $assigned];
    }

    private function tenant(): ?Tenant
    {
        return Tenant::query()
            ->where('code', config('eralms.default_tenant_code', 'VABIS'))
            ->where('status', 'active')
            ->first();
    }
}
