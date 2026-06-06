<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AccreditationReport;
use App\Models\CompetencyFramework;
use App\Models\LearningOutcome;
use App\Services\AccreditationReportService;
use App\Services\AchievementAnalyticsService;
use App\Services\AssessmentMappingService;
use App\Services\OBEFrameworkService;
use App\Services\OutcomeMatrixService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OBEController extends Controller
{
    public function outcomes(Request $request, TenantContext $tenant) { return LearningOutcome::query()->where('tenant_id', $tenant->id())->when($request->filled('type'), fn($q)=>$q->where('type',$request->input('type')))->orderBy('code')->paginate($request->integer('per_page',50)); }
    public function storeOutcome(Request $request, TenantContext $tenant, OBEFrameworkService $service) { return response()->json($service->createOutcome($request->all() + ['tenant_id'=>$tenant->id()]), 201); }
    public function updateOutcome(Request $request, LearningOutcome $outcome, OBEFrameworkService $service) { return $service->updateOutcome($outcome, $request->all()); }
    public function frameworks(Request $request, TenantContext $tenant) { return CompetencyFramework::query()->where('tenant_id',$tenant->id())->with('items')->latest()->paginate($request->integer('per_page',25)); }
    public function storeFramework(Request $request, TenantContext $tenant, OBEFrameworkService $service) { return response()->json($service->createFramework($request->all() + ['tenant_id'=>$tenant->id(),'created_by'=>$request->user()?->id ?? 1]), 201); }
    public function storeFrameworkItem(Request $request, CompetencyFramework $framework, OBEFrameworkService $service) { return response()->json($service->createFrameworkItem($framework, $request->all()), 201); }
    public function mapOutcomePath(Request $request, TenantContext $tenant, OBEFrameworkService $service) { return response()->json($service->mapOutcomePath($request->all() + ['tenant_id'=>$tenant->id()]), 201); }
    public function mapAssessment(Request $request, TenantContext $tenant, AssessmentMappingService $service) { return $service->mapAssessment((int)$tenant->id(), $request->input('assessment_type'), $request->integer('assessment_id'), $request->input('outcomes', [])); }
    public function matrix(Request $request, TenantContext $tenant, OutcomeMatrixService $service) { return $service->matrix((int)$tenant->id(), $request->filled('course_id') ? $request->integer('course_id') : null); }
    public function coverage(Request $request, TenantContext $tenant, OutcomeMatrixService $service) { return $service->coverage((int)$tenant->id(), $request->filled('course_id') ? $request->integer('course_id') : null); }
    public function recalculate(Request $request, TenantContext $tenant, AchievementAnalyticsService $service) { return $service->recalculate((int)$tenant->id(), $request->filled('course_id') ? $request->integer('course_id') : null); }
    public function dashboard(TenantContext $tenant, AchievementAnalyticsService $service) { return $service->dashboard((int)$tenant->id()); }
    public function reports(Request $request, TenantContext $tenant) { return AccreditationReport::query()->where('tenant_id',$tenant->id())->latest()->paginate($request->integer('per_page',25)); }
    public function generateReport(Request $request, TenantContext $tenant, AccreditationReportService $service) { return response()->json($service->generate((int)$tenant->id(), $request->input('standard','AUN-QA'), $request->input('format','pdf'), $request->input('filters',[]), $request->user()?->id ?? 1), 201); }
}
