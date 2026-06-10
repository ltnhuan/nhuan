<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AcademicUnit;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\LmsUser;
use App\Models\Organization;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Services\Core\CoreDashboardService;
use App\Services\Core\CoreMenuService;
use App\Services\SettingService;
use App\Services\TenantContext;
use App\Support\ApiPagination;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CoreController extends Controller
{
    public function me(Request $request, TenantContext $tenantContext, CoreMenuService $menuService)
    {
        $user = $request->user();

        if (! $user && $request->header('X-Demo-User-Email')) {
            $user = LmsUser::query()
                ->where('tenant_id', $tenantContext->id())
                ->where('email', $request->header('X-Demo-User-Email'))
                ->first();
        }

        if ($user && ! $user instanceof LmsUser) {
            $user = LmsUser::query()
                ->where('tenant_id', $tenantContext->id())
                ->where(function ($query) use ($user) {
                    $query->where('email', $user->email ?? null)
                        ->orWhere('id', method_exists($user, 'getAuthIdentifier') ? $user->getAuthIdentifier() : ($user->id ?? null));
                })
                ->first();
        }

        return response()->json([
            'user' => $user,
            'tenant' => $tenantContext->get(),
            'menu' => $user instanceof LmsUser ? $menuService->forUser($user, (int) $tenantContext->id()) : [],
        ]);
    }

    public function dashboard(TenantContext $tenantContext, CoreDashboardService $dashboardService)
    {
        return response()->json($dashboardService->summary((int) $tenantContext->id()));
    }

    public function tenants()
    {
        return Tenant::query()->orderBy('name')->paginate(25);
    }

    public function organizations(Request $request, TenantContext $tenantContext)
    {
        return Organization::query()
            ->where('tenant_id', $tenantContext->id())
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderBy('name')
            ->paginate(ApiPagination::perPage($request, 50));
    }

    public function campuses(Request $request, TenantContext $tenantContext)
    {
        return Campus::query()
            ->where('tenant_id', $tenantContext->id())
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderBy('name')
            ->paginate(ApiPagination::perPage($request, 50));
    }

    public function academicUnits(Request $request, TenantContext $tenantContext)
    {
        return AcademicUnit::query()
            ->where('tenant_id', $tenantContext->id())
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->orderBy('name')
            ->paginate(ApiPagination::perPage($request, 50));
    }

    public function settings(Request $request, TenantContext $tenantContext)
    {
        return SystemSetting::query()
            ->where('tenant_id', $tenantContext->id())
            ->when($request->filled('group'), fn ($query) => $query->where('group', $request->input('group')))
            ->orderBy('group')
            ->orderBy('key')
            ->paginate(ApiPagination::perPage($request, 50));
    }

    public function updateSettings(Request $request, SettingService $settings, TenantContext $tenantContext)
    {
        $data = $request->validate([
            'group' => ['required', 'string'],
            'key' => ['required', 'string'],
            'value' => ['nullable'],
        ]);

        return $settings->set((int) $tenantContext->id(), $data['group'], $data['key'], $data['value']);
    }

    public function auditLogs(Request $request, TenantContext $tenantContext)
    {
        return AuditLog::query()
            ->where('tenant_id', $tenantContext->id())
            ->when($request->filled('module'), fn ($query) => $query->where('module', $request->input('module')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->input('action')))
            ->latest('created_at')
            ->paginate(ApiPagination::perPage($request, 50));
    }
}
