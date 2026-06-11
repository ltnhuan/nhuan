<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ExternalTool;
use App\Models\LtiLaunch;
use App\Models\LtiRegistration;
use App\Models\ScormAttempt;
use App\Models\ScormEvent;
use App\Models\ScormPackage;
use App\Models\XapiStatement;
use App\Services\LearningStandardsService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LearningStandardsController extends Controller
{
    public function packages(Request $request, TenantContext $tenant)
    {
        return ScormPackage::query()->where('tenant_id', $tenant->id())->latest()->paginate($request->integer('per_page', 25));
    }

    public function uploadScorm(Request $request, TenantContext $tenant, LearningStandardsService $service)
    {
        $data = $request->validate([
            'package' => ['required', 'file', 'mimes:zip'],
            'title' => ['nullable', 'string'],
            'standard' => ['nullable', 'in:scorm_1_2,scorm_2004'],
            'course_id' => ['nullable', 'integer'],
            'launch_path' => ['nullable', 'string'],
        ]);

        $package = $service->uploadScormPackage((int) $tenant->id(), $request->file('package'), $data, $service->resolveUserId($request->header('X-Demo-User-Email')));

        return response()->json($package, 201);
    }

    public function launchScorm(ScormPackage $package, Request $request, LearningStandardsService $service)
    {
        return response()->json($service->launchScorm($package, $request->integer('user_id') ?: $service->resolveUserId($request->header('X-Demo-User-Email')), $request->input('runtime_data', [])), 201);
    }

    public function trackScorm(ScormAttempt $attempt, Request $request, LearningStandardsService $service)
    {
        return $service->trackScorm($attempt, $request->validate([
            'event_type' => ['nullable', 'string'],
            'progress' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'score' => ['nullable', 'numeric', 'min:0'],
            'completion_status' => ['nullable', 'string'],
            'success_status' => ['nullable', 'string'],
            'session_time_seconds' => ['nullable', 'integer', 'min:0'],
            'runtime_data' => ['nullable', 'array'],
        ]));
    }

    public function scormEvents(Request $request, TenantContext $tenant)
    {
        return ScormEvent::query()->where('tenant_id', $tenant->id())->latest()->paginate($request->integer('per_page', 50));
    }

    public function xapiStatements(Request $request, TenantContext $tenant)
    {
        return XapiStatement::query()->where('tenant_id', $tenant->id())->latest('stored_at')->paginate($request->integer('per_page', 50));
    }

    public function storeXapi(Request $request, TenantContext $tenant, LearningStandardsService $service)
    {
        $statement = $request->validate([
            'id' => ['nullable', 'uuid'],
            'actor' => ['required', 'array'],
            'verb' => ['required', 'array'],
            'object' => ['required', 'array'],
            'result' => ['nullable', 'array'],
            'context' => ['nullable', 'array'],
            'timestamp' => ['nullable', 'date'],
        ]);

        return response()->json($service->storeXapiStatement((int) $tenant->id(), $statement), 201);
    }

    public function ltiRegistrations(Request $request, TenantContext $tenant)
    {
        return LtiRegistration::query()->where('tenant_id', $tenant->id())->latest()->paginate($request->integer('per_page', 25));
    }

    public function storeLtiRegistration(Request $request, TenantContext $tenant, LearningStandardsService $service)
    {
        return response()->json($service->registerLtiTool((int) $tenant->id(), $request->validate([
            'name' => ['required', 'string'],
            'issuer' => ['required', 'string'],
            'client_id' => ['required', 'string'],
            'deployment_id' => ['required', 'string'],
            'login_url' => ['required', 'url'],
            'launch_url' => ['required', 'url'],
            'jwks_url' => ['nullable', 'url'],
            'scopes' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ])), 201);
    }

    public function launchLti(LtiRegistration $registration, Request $request, LearningStandardsService $service)
    {
        return response()->json($service->launchLti($registration, $request->all()), 201);
    }

    public function ltiLaunches(Request $request, TenantContext $tenant)
    {
        return LtiLaunch::query()->where('tenant_id', $tenant->id())->latest('launched_at')->paginate($request->integer('per_page', 50));
    }

    public function externalTools(Request $request, TenantContext $tenant)
    {
        return ExternalTool::query()->where('tenant_id', $tenant->id())->when($request->filled('category'), fn ($query) => $query->where('category', $request->input('category')))->latest()->paginate($request->integer('per_page', 50));
    }

    public function storeExternalTool(Request $request, TenantContext $tenant, LearningStandardsService $service)
    {
        return response()->json($service->registerExternalTool((int) $tenant->id(), $request->validate([
            'name' => ['required', 'string'],
            'category' => ['required', 'in:zoom,teams,google_meet,lab,ai_tool,simulation,exam,publisher_content'],
            'provider' => ['required', 'string'],
            'launch_type' => ['nullable', 'string'],
            'launch_url' => ['nullable', 'url'],
            'lti_registration_id' => ['nullable', 'integer'],
            'capabilities' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ])), 201);
    }

    public function analytics(TenantContext $tenant, LearningStandardsService $service)
    {
        return $service->analytics((int) $tenant->id());
    }
}
