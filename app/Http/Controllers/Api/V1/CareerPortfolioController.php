<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CareerProfile;
use App\Models\CareerTimelineEvent;
use App\Models\CompetencyRecord;
use App\Models\DigitalPortfolio;
use App\Models\LmsUser;
use App\Models\SkillDefinition;
use App\Services\CareerPortfolioService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CareerPortfolioController extends Controller
{
    public function dashboard(Request $request, TenantContext $tenant, CareerPortfolioService $service)
    {
        return $service->dashboard((int) $tenant->id(), $this->userId($request, (int) $tenant->id()));
    }

    public function digitalTwin(Request $request, TenantContext $tenant, CareerPortfolioService $service)
    {
        if ($request->query('scope') === 'overview') {
            return $service->digitalTwinOverview((int) $tenant->id(), $request->all());
        }

        return $service->digitalTwinIdp((int) $tenant->id(), $this->userId($request, (int) $tenant->id()));
    }

    public function profile(Request $request, TenantContext $tenant, CareerPortfolioService $service)
    {
        return $service->ensureProfile((int) $tenant->id(), $this->userId($request, (int) $tenant->id()), $request->all());
    }

    public function updateProfile(Request $request, CareerProfile $profile)
    {
        $this->assertOwnerOrStaff($request, $profile->tenant_id, $profile->user_id);
        $profile->fill($request->except(['tenant_id','user_id']))->save();
        return $profile->fresh('portfolio.items');
    }

    public function addItem(Request $request, DigitalPortfolio $portfolio, CareerPortfolioService $service)
    {
        $this->assertOwnerOrStaff($request, $portfolio->tenant_id, $portfolio->user_id);
        return response()->json($service->addPortfolioItem($portfolio, $request->all()), 201);
    }

    public function skills(TenantContext $tenant)
    {
        return SkillDefinition::query()->where('tenant_id', $tenant->id())->orderBy('category')->orderBy('name')->get();
    }

    public function upsertSkill(Request $request, TenantContext $tenant, CareerPortfolioService $service)
    {
        $userId = $request->integer('user_id') ?: $this->userId($request, (int) $tenant->id());
        $this->assertOwnerOrStaff($request, (int) $tenant->id(), $userId);
        return $service->upsertSkill((int) $tenant->id(), $userId, $request->integer('skill_definition_id'), (float) $request->input('score'), $request->only(['source','evidence','assessed_by']));
    }

    public function competency(Request $request, TenantContext $tenant)
    {
        $userId = $request->integer('user_id') ?: $this->userId($request, (int) $tenant->id());
        $this->assertOwnerOrStaff($request, (int) $tenant->id(), $userId);
        return response()->json(CompetencyRecord::query()->create(array_replace($request->all(), ['tenant_id'=>$tenant->id(), 'user_id'=>$userId])), 201);
    }

    public function timeline(Request $request, TenantContext $tenant)
    {
        $userId = $request->integer('user_id') ?: $this->userId($request, (int) $tenant->id());
        $this->assertOwnerOrStaff($request, (int) $tenant->id(), $userId);
        return response()->json(CareerTimelineEvent::query()->create(array_replace($request->all(), ['tenant_id'=>$tenant->id(), 'user_id'=>$userId])), 201);
    }

    public function publicProfile(Request $request, TenantContext $tenant, string $slug, CareerPortfolioService $service)
    {
        return $service->publicProfile((int) $tenant->id(), $slug, $request->only(['employer_name','viewer_email']));
    }

    public function employerSearch(Request $request, TenantContext $tenant, CareerPortfolioService $service)
    {
        return $service->employerSearch((int) $tenant->id(), $request->all());
    }

    public function verifyCertificate(TenantContext $tenant, string $code, CareerPortfolioService $service)
    {
        return $service->verifyCertificate((int) $tenant->id(), $code);
    }

    private function userId(Request $request, int $tenantId): int
    {
        if ($request->user()) return (int) $request->user()->id;
        if ($request->header('X-Demo-User-Email')) return (int) (LmsUser::query()->where('tenant_id', $tenantId)->where('email', $request->header('X-Demo-User-Email'))->value('id') ?: 1);
        return 1;
    }

    private function assertOwnerOrStaff(Request $request, int $tenantId, int $userId): void
    {
        $viewer = $request->header('X-Demo-User-Email')
            ? LmsUser::query()->where('tenant_id', $tenantId)->where('email', $request->header('X-Demo-User-Email'))->first()
            : null;
        if ($viewer?->user_type === 'student' && (int) $viewer->id !== $userId) {
            abort(403, 'Student chỉ được cập nhật hồ sơ của mình.');
        }
    }
}
