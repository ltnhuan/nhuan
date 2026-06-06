<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\LmsUser;
use App\Models\SurveyCampaign;
use App\Models\SurveyEvidenceFile;
use App\Models\SurveyForm;
use App\Models\SurveyImprovement;
use App\Services\SurveyService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SurveyController extends Controller
{
    public function forms(Request $request, TenantContext $tenant)
    {
        return SurveyForm::query()
            ->where('tenant_id', $tenant->id())
            ->withCount(['questions','campaigns'])
            ->when($request->query('survey_type'), fn ($q, $type) => $q->where('survey_type', $type))
            ->latest('updated_at')
            ->paginate($request->integer('per_page', 25));
    }

    public function storeForm(Request $request, TenantContext $tenant, SurveyService $service)
    {
        return response()->json($service->createForm(array_replace($request->all(), [
            'tenant_id' => $tenant->id(),
            'created_by' => $this->userId($request, (int) $tenant->id()),
        ])), 201);
    }

    public function showForm(SurveyForm $form)
    {
        return $form->load('questions');
    }

    public function updateForm(Request $request, SurveyForm $form, SurveyService $service)
    {
        return $service->updateForm($form, $request->all());
    }

    public function reorderQuestions(Request $request, SurveyForm $form)
    {
        foreach ($request->input('question_ids', []) as $index => $id) {
            $form->questions()->whereKey($id)->update(['sort_order' => $index + 1]);
        }
        return $form->fresh('questions');
    }

    public function campaigns(Request $request, TenantContext $tenant)
    {
        return SurveyCampaign::query()
            ->where('tenant_id', $tenant->id())
            ->with(['form:id,title,survey_type'])
            ->withCount('responses')
            ->when($request->query('target_scope'), fn ($q, $scope) => $q->where('target_scope', $scope))
            ->latest('updated_at')
            ->paginate($request->integer('per_page', 25));
    }

    public function storeCampaign(Request $request, TenantContext $tenant, SurveyService $service)
    {
        return response()->json($service->createCampaign(array_replace($request->all(), [
            'tenant_id' => $tenant->id(),
            'created_by' => $this->userId($request, (int) $tenant->id()),
        ])), 201);
    }

    public function showCampaign(SurveyCampaign $campaign)
    {
        return $campaign->load(['form.questions','responses.answers','improvements','evidence']);
    }

    public function launch(SurveyCampaign $campaign)
    {
        $campaign->forceFill(['status' => 'active'])->save();
        return $campaign->fresh();
    }

    public function close(SurveyCampaign $campaign)
    {
        $campaign->forceFill(['status' => 'closed'])->save();
        return $campaign->fresh();
    }

    public function submitResponse(Request $request, SurveyCampaign $campaign, SurveyService $service)
    {
        return response()->json($service->submitResponse($campaign, $request->all(), $this->nullableUserId($request, $campaign->tenant_id)), 201);
    }

    public function analytics(Request $request, TenantContext $tenant, SurveyService $service)
    {
        return $service->analytics((int) $tenant->id(), $request->only(['campaign_id','survey_type']));
    }

    public function improvements(Request $request, TenantContext $tenant)
    {
        return SurveyImprovement::query()
            ->where('tenant_id', $tenant->id())
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('updated_at')
            ->paginate($request->integer('per_page', 25));
    }

    public function storeImprovement(Request $request, TenantContext $tenant, SurveyService $service)
    {
        return response()->json($service->createImprovement(array_replace($request->all(), [
            'tenant_id' => $tenant->id(),
            'owner_id' => $request->input('owner_id', $this->nullableUserId($request, (int) $tenant->id())),
        ])), 201);
    }

    public function updateImprovement(Request $request, SurveyImprovement $improvement)
    {
        $improvement->fill($request->except(['id','tenant_id']))->save();
        return $improvement->fresh();
    }

    public function evidence(Request $request, TenantContext $tenant)
    {
        return SurveyEvidenceFile::query()
            ->where('tenant_id', $tenant->id())
            ->latest('created_at')
            ->paginate($request->integer('per_page', 25));
    }

    public function storeEvidence(Request $request, TenantContext $tenant, SurveyService $service)
    {
        return response()->json($service->createEvidence(array_replace($request->all(), [
            'tenant_id' => $tenant->id(),
            'created_by' => $this->userId($request, (int) $tenant->id()),
        ])), 201);
    }

    public function export(Request $request, SurveyCampaign $campaign, SurveyService $service)
    {
        $export = $service->export($campaign, $request->query('format', 'excel'));
        return response($export['content'], 200, [
            'Content-Type' => $export['mime'],
            'Content-Disposition' => 'attachment; filename="'.$export['filename'].'"',
        ]);
    }

    private function userId(Request $request, int $tenantId): int
    {
        return $this->nullableUserId($request, $tenantId) ?? 1;
    }

    private function nullableUserId(Request $request, int $tenantId): ?int
    {
        if ($request->user()) return (int) $request->user()->id;
        if ($request->header('X-Demo-User-Email')) {
            return LmsUser::query()->where('tenant_id', $tenantId)->where('email', $request->header('X-Demo-User-Email'))->value('id');
        }
        return null;
    }
}
