<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\GradeSummary;
use App\Models\LmsUser;
use App\Models\Course;
use App\Services\StudentExperienceService;
use App\Services\StudentGradeDashboardService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class StudentController extends Controller
{
    public function home(Request $request, TenantContext $tenant, StudentExperienceService $service)
    {
        $tenantId = (int) $tenant->id();
        $student = $this->student($request, $tenantId, $this->viewer($request, $tenantId));

        abort_if(! $student, 404, 'Không tìm thấy sinh viên.');

        return response()->json($service->home($tenantId, $student));
    }

    public function grades(Request $request, TenantContext $tenant, StudentGradeDashboardService $service)
    {
        $tenantId = (int) $tenant->id();
        $viewer = $this->viewer($request, $tenantId);
        $student = $this->student($request, $tenantId, $viewer);

        abort_if(! $student, 404, 'Không tìm thấy sinh viên.');

        return response()->json($service->dashboard($tenantId, $student));
    }

    public function courses(Request $request, TenantContext $tenant, StudentExperienceService $service)
    {
        $tenantId = (int) $tenant->id();
        $student = $this->student($request, $tenantId, $this->viewer($request, $tenantId));

        abort_if(! $student, 404, 'Không tìm thấy sinh viên.');

        return response()->json($service->courses($tenantId, $student, $request->query()));
    }

    public function journey(Request $request, TenantContext $tenant, Course $course, StudentExperienceService $service)
    {
        $tenantId = (int) $tenant->id();
        $student = $this->student($request, $tenantId, $this->viewer($request, $tenantId));

        abort_if(! $student, 404, 'Không tìm thấy sinh viên.');
        abort_if((int) $course->tenant_id !== $tenantId, 404, 'Không tìm thấy khóa học.');

        return response()->json($service->journey($tenantId, $student, $course));
    }

    public function tasks(Request $request, TenantContext $tenant, StudentExperienceService $service)
    {
        $tenantId = (int) $tenant->id();
        $student = $this->student($request, $tenantId, $this->viewer($request, $tenantId));

        abort_if(! $student, 404, 'Không tìm thấy sinh viên.');

        return response()->json($service->tasks($tenantId, $student));
    }

    public function attendance(Request $request, TenantContext $tenant, StudentExperienceService $service)
    {
        $tenantId = (int) $tenant->id();
        $student = $this->student($request, $tenantId, $this->viewer($request, $tenantId));

        abort_if(! $student, 404, 'Không tìm thấy sinh viên.');

        return response()->json($service->attendance($tenantId, $student));
    }

    public function portfolio(Request $request, TenantContext $tenant, StudentExperienceService $service)
    {
        $tenantId = (int) $tenant->id();
        $student = $this->student($request, $tenantId, $this->viewer($request, $tenantId));

        abort_if(! $student, 404, 'Không tìm thấy sinh viên.');

        return response()->json($service->portfolio($tenantId, $student));
    }

    public function career(Request $request, TenantContext $tenant, StudentExperienceService $service)
    {
        $tenantId = (int) $tenant->id();
        $student = $this->student($request, $tenantId, $this->viewer($request, $tenantId));

        abort_if(! $student, 404, 'Không tìm thấy sinh viên.');

        return response()->json($service->career($tenantId, $student));
    }

    public function credentials(Request $request, TenantContext $tenant, StudentExperienceService $service)
    {
        $tenantId = (int) $tenant->id();
        $student = $this->student($request, $tenantId, $this->viewer($request, $tenantId));

        abort_if(! $student, 404, 'Không tìm thấy sinh viên.');

        return response()->json($service->credentials($tenantId, $student));
    }

    public function digitalTwin(Request $request, TenantContext $tenant, StudentExperienceService $service)
    {
        $tenantId = (int) $tenant->id();
        $student = $this->student($request, $tenantId, $this->viewer($request, $tenantId));

        abort_if(! $student, 404, 'Không tìm thấy sinh viên.');

        return response()->json($service->digitalTwin($tenantId, $student));
    }

    public function showDigitalTwin(Request $request, TenantContext $tenant, LmsUser $student, StudentExperienceService $service)
    {
        $tenantId = (int) $tenant->id();
        $viewer = $this->viewer($request, $tenantId);

        abort_if((int) $student->tenant_id !== $tenantId || $student->user_type !== 'student', 404, 'Không tìm thấy sinh viên.');
        abort_if($viewer?->user_type === 'student' && (int) $viewer->id !== (int) $student->id, 403, 'Sinh viên chỉ được xem Digital Twin của chính mình.');

        return response()->json($service->digitalTwin($tenantId, $student));
    }

    private function viewer(Request $request, int $tenantId): ?LmsUser
    {
        if ($request->user() instanceof LmsUser) {
            return $request->user();
        }

        if ($request->header('X-Demo-User-Email')) {
            return LmsUser::query()
                ->where('tenant_id', $tenantId)
                ->where('email', $request->header('X-Demo-User-Email'))
                ->first();
        }

        return null;
    }

    private function student(Request $request, int $tenantId, ?LmsUser $viewer): ?LmsUser
    {
        if ($viewer?->user_type === 'student') {
            return $viewer;
        }

        if ($request->filled('user_id')) {
            return LmsUser::query()
                ->where('tenant_id', $tenantId)
                ->where('user_type', 'student')
                ->whereKey($request->integer('user_id'))
                ->first();
        }

        $studentIdWithGrades = GradeSummary::query()
            ->where('tenant_id', $tenantId)
            ->latest('updated_at')
            ->value('user_id');

        return LmsUser::query()
            ->where('tenant_id', $tenantId)
            ->where('user_type', 'student')
            ->when($studentIdWithGrades, fn ($query) => $query->whereKey($studentIdWithGrades))
            ->first()
            ?: LmsUser::query()->where('tenant_id', $tenantId)->where('user_type', 'student')->first();
    }
}
