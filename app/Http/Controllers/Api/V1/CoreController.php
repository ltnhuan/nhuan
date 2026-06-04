<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AcademicUnit;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Organization;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CoreController extends Controller
{
    public function me(Request $request)
    {
        return response()->json(['user' => $request->user(), 'tenant' => $request->attributes->get('tenant')]);
    }

    public function tenants()
    {
        return Tenant::query()->orderBy('name')->paginate(25);
    }

    public function organizations(Request $request)
    {
        return Organization::query()->where('tenant_id', $request->attributes->get('tenant')?->id)->orderBy('name')->paginate(50);
    }

    public function campuses(Request $request)
    {
        return Campus::query()->where('tenant_id', $request->attributes->get('tenant')?->id)->orderBy('name')->paginate(50);
    }

    public function academicUnits(Request $request)
    {
        return AcademicUnit::query()->where('tenant_id', $request->attributes->get('tenant')?->id)->orderBy('name')->paginate(50);
    }

    public function settings(Request $request)
    {
        return SystemSetting::query()->where('tenant_id', $request->attributes->get('tenant')?->id)->paginate(50);
    }

    public function updateSettings(Request $request, SettingService $settings)
    {
        $data = $request->validate(['group' => ['required', 'string'], 'key' => ['required', 'string'], 'value' => ['nullable']]);
        return $settings->set($request->attributes->get('tenant')->id, $data['group'], $data['key'], $data['value']);
    }

    public function auditLogs(Request $request)
    {
        return AuditLog::query()->where('tenant_id', $request->attributes->get('tenant')?->id)->latest()->paginate(50);
    }
}
