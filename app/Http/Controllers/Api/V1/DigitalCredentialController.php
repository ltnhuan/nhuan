<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Badge;
use App\Models\BadgeIssue;
use App\Models\BadgeRule;
use App\Models\Certificate;
use App\Models\CertificateIssue;
use App\Models\CertificateTemplate;
use App\Models\LearningCompletion;
use App\Models\LmsUser;
use App\Models\MicroCredential;
use App\Services\DigitalCredentialService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DigitalCredentialController extends Controller
{
    public function dashboard(TenantContext $tenant, DigitalCredentialService $service)
    {
        return $service->analytics((int) $tenant->id());
    }

    public function builder(TenantContext $tenant, DigitalCredentialService $service)
    {
        return [
            'templates' => CertificateTemplate::query()->where('tenant_id', $tenant->id())->latest()->paginate(20),
            'default_template' => $service->defaultCertificateBlueprint((int) $tenant->id()),
            'dynamic_fields' => ['learner_name','certificate_title','course_title','issued_at','expires_at','qr_payload','verification_hash','signature_image'],
        ];
    }

    public function storeTemplate(Request $request, TenantContext $tenant)
    {
        $data = $request->validate(['code'=>'required|string','name'=>'required|string','type'=>'nullable|string','language'=>'nullable|string','status'=>'nullable|string','canvas_schema'=>'nullable|array','dynamic_fields'=>'nullable|array','signature_image_path'=>'nullable|string','translations'=>'nullable|array','settings'=>'nullable|array']);
        return response()->json(CertificateTemplate::query()->updateOrCreate(['tenant_id' => $tenant->id(), 'code' => $data['code']], array_replace($data, ['tenant_id' => $tenant->id()])), 201);
    }

    public function certificates(TenantContext $tenant)
    {
        return Certificate::query()->where('tenant_id', $tenant->id())->with('template')->latest()->paginate(25);
    }

    public function storeCertificate(Request $request, TenantContext $tenant)
    {
        $data = $request->validate(['code'=>'required|string','title'=>'required|string','certificate_template_id'=>'nullable|integer','description'=>'nullable|string','credential_type'=>'nullable|string','issuer_name'=>'nullable|string','rules'=>'nullable|array','metadata'=>'nullable|array','status'=>'nullable|string']);
        return response()->json(Certificate::query()->updateOrCreate(['tenant_id' => $tenant->id(), 'code' => $data['code']], array_replace(['credential_type' => 'course_certificate', 'issuer_name' => 'EraLMS', 'status' => 'active'], $data, ['tenant_id' => $tenant->id()])), 201);
    }

    public function issueCertificate(Request $request, TenantContext $tenant, DigitalCredentialService $service)
    {
        $data = $request->validate(['certificate_id'=>'required|integer','user_id'=>'required|integer','course_id'=>'nullable|integer','learning_path_id'=>'nullable|integer','issue_code'=>'nullable|string','language'=>'nullable|string','field_values'=>'nullable|array','sis_payload'=>'nullable|array','expires_at'=>'nullable|date']);
        return response()->json($service->issueCertificate((int) $tenant->id(), $data), 201);
    }

    public function revokeCertificate(Request $request, CertificateIssue $issue, DigitalCredentialService $service)
    {
        return $service->revokeCertificate($issue, $request->string('reason', 'Revoked by issuer')->toString());
    }

    public function verify(Request $request, TenantContext $tenant, string $code, DigitalCredentialService $service)
    {
        return $service->verifyCertificate($tenant->id() ? (int) $tenant->id() : null, $code, ['ip' => $request->ip(), 'user_agent' => $request->userAgent(), 'source' => 'api']);
    }

    public function portal(Request $request, string $code, DigitalCredentialService $service)
    {
        return $service->verifyCertificate(null, $code, ['ip' => $request->ip(), 'user_agent' => $request->userAgent(), 'source' => 'verify_portal']);
    }

    public function badges(TenantContext $tenant)
    {
        return ['badges' => Badge::query()->where('tenant_id', $tenant->id())->with('rules')->latest()->paginate(25), 'rules' => BadgeRule::query()->where('tenant_id', $tenant->id())->latest()->take(50)->get()];
    }

    public function storeBadge(Request $request, TenantContext $tenant)
    {
        $data = $request->validate(['code'=>'required|string','name'=>'required|string','badge_type'=>'nullable|string','description'=>'nullable|string','image_url'=>'nullable|string','criteria'=>'nullable|array','metadata'=>'nullable|array','status'=>'nullable|string']);
        return response()->json(Badge::query()->updateOrCreate(['tenant_id' => $tenant->id(), 'code' => $data['code']], array_replace(['badge_type' => 'completion', 'status' => 'active'], $data, ['tenant_id' => $tenant->id()])), 201);
    }

    public function storeBadgeRule(Request $request, TenantContext $tenant)
    {
        $data = $request->validate(['badge_id'=>'required|integer','rule_type'=>'required|string','source_type'=>'nullable|string','source_id'=>'nullable|integer','conditions'=>'nullable|array','auto_issue'=>'nullable|boolean','status'=>'nullable|string']);
        return response()->json(BadgeRule::query()->create(array_replace(['auto_issue' => true, 'status' => 'active'], $data, ['tenant_id' => $tenant->id()])), 201);
    }

    public function issueBadge(Request $request, TenantContext $tenant, DigitalCredentialService $service)
    {
        $data = $request->validate(['badge_id'=>'required|integer','user_id'=>'required|integer','course_id'=>'nullable|integer','evidence'=>'nullable|array']);
        return response()->json($service->issueBadge((int) $tenant->id(), $data), 201);
    }

    public function autoIssueBadges(Request $request, DigitalCredentialService $service)
    {
        $completion = LearningCompletion::query()->findOrFail($request->integer('completion_id'));
        return ['issued' => $service->autoIssueBadgesForCompletion($completion)];
    }

    public function microCredentials(TenantContext $tenant)
    {
        return MicroCredential::query()->where('tenant_id', $tenant->id())->latest()->paginate(25);
    }

    public function issueCredential(Request $request, TenantContext $tenant, DigitalCredentialService $service)
    {
        $data = $request->validate(['code'=>'required|string','title'=>'required|string','credential_type'=>'nullable|string','industry'=>'nullable|string','level'=>'nullable|string','outcome_statement'=>'nullable|string','criteria'=>'nullable|array','metadata'=>'nullable|array','skills'=>'nullable|array']);
        return response()->json($service->issueMicroCredential((int) $tenant->id(), $data), 201);
    }

    public function wallet(Request $request, TenantContext $tenant, DigitalCredentialService $service)
    {
        $userId = $request->integer('user_id') ?: $this->userId($request, (int) $tenant->id());
        return $service->wallet((int) $tenant->id(), $userId);
    }

    private function userId(Request $request, int $tenantId): int
    {
        if ($request->user()) return (int) $request->user()->id;
        if ($request->header('X-Demo-User-Email')) return (int) (LmsUser::query()->where('tenant_id', $tenantId)->where('email', $request->header('X-Demo-User-Email'))->value('id') ?: 1);
        return 1;
    }
}
