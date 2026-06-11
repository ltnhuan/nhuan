<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\DataIntegrityIssue;
use App\Models\LmsUser;
use App\Services\DataIntegrityService;
use App\Services\TenantContext;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DataIntegrityController extends Controller
{
    public function dashboard(TenantContext $tenantContext, DataIntegrityService $service)
    {
        return ApiResponse::success($service->dashboard($tenantContext->id()), 'Data integrity dashboard loaded.');
    }

    public function runAll(TenantContext $tenantContext, DataIntegrityService $service)
    {
        return ApiResponse::success($service->runAllChecks($tenantContext->id()), 'Data integrity checks completed.');
    }

    public function runModule(string $module, TenantContext $tenantContext, DataIntegrityService $service)
    {
        return ApiResponse::success($service->runModuleChecks($module, $tenantContext->id()), 'Module integrity checks completed.');
    }

    public function autoFix(Request $request, TenantContext $tenantContext, DataIntegrityService $service)
    {
        $data = $request->validate([
            'issue_ids' => ['required', 'array', 'min:1'],
            'issue_ids.*' => ['integer'],
        ]);

        $actorId = $this->actorId($request, $tenantContext->id());
        $fixed = [];

        foreach (DataIntegrityIssue::query()->where('tenant_id', $tenantContext->id())->whereIn('id', $data['issue_ids'])->get() as $issue) {
            $fixed[] = $service->autoFixIssue($issue, $actorId);
        }

        return ApiResponse::success(['fixed' => $fixed], 'Auto-fix completed.');
    }

    public function ignore(Request $request, TenantContext $tenantContext, DataIntegrityService $service)
    {
        $data = $request->validate([
            'issue_ids' => ['required', 'array', 'min:1'],
            'issue_ids.*' => ['integer'],
        ]);

        $actorId = $this->actorId($request, $tenantContext->id());
        $ignored = [];

        foreach (DataIntegrityIssue::query()->where('tenant_id', $tenantContext->id())->whereIn('id', $data['issue_ids'])->get() as $issue) {
            $ignored[] = $service->ignoreIssue($issue, $actorId);
        }

        return ApiResponse::success(['ignored' => $ignored], 'Issues ignored.');
    }

    public function export(TenantContext $tenantContext, DataIntegrityService $service)
    {
        return response()->json($service->exportReport($tenantContext->id()))
            ->header('Content-Disposition', 'attachment; filename="data-integrity-report.json"');
    }

    private function actorId(Request $request, int $tenantId): ?int
    {
        if ($request->user()) {
            return (int) $request->user()->id;
        }

        if ($request->header('X-Demo-User-Email')) {
            return LmsUser::query()
                ->where('tenant_id', $tenantId)
                ->where('email', $request->header('X-Demo-User-Email'))
                ->value('id');
        }

        return null;
    }
}
