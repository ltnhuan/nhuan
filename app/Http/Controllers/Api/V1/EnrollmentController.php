<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ClassSection;
use App\Models\Cohort;
use App\Models\CohortGroup;
use App\Models\CohortRule;
use App\Models\Enrollment;
use App\Models\EnrollmentImportJob;
use App\Models\LmsUser;
use App\Services\EnrollmentAnalyticsService;
use App\Services\EnrollmentImportService;
use App\Services\EnrollmentService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class EnrollmentController extends Controller
{
    public function cohorts(Request $request, TenantContext $tenant)
    {
        return Cohort::query()->where('tenant_id', $tenant->id())->withCount(['groups', 'enrollments'])->latest('updated_at')->paginate($request->integer('per_page', 25));
    }

    public function storeCohort(Request $request, TenantContext $tenant)
    {
        return response()->json(Cohort::query()->create(array_replace($request->all(), ['tenant_id' => $tenant->id(), 'created_by' => $this->userId($request, (int) $tenant->id())])), 201);
    }

    public function storeCohortGroup(Request $request, Cohort $cohort)
    {
        return response()->json(CohortGroup::query()->create(array_replace($request->all(), ['tenant_id' => $cohort->tenant_id, 'cohort_id' => $cohort->id])), 201);
    }

    public function storeCohortRule(Request $request, Cohort $cohort)
    {
        return response()->json(CohortRule::query()->create(array_replace($request->all(), ['tenant_id' => $cohort->tenant_id, 'cohort_id' => $cohort->id])), 201);
    }

    public function sections(Request $request, TenantContext $tenant)
    {
        return ClassSection::query()
            ->where('tenant_id', $tenant->id())
            ->withCount(['enrollments', 'teacherAssignments'])
            ->with(['course:id,code,title'])
            ->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->integer('course_id')))
            ->when($request->filled('section_type'), fn ($q) => $q->where('section_type', $request->input('section_type')))
            ->latest('updated_at')
            ->paginate($request->integer('per_page', 50));
    }

    public function storeSection(Request $request, TenantContext $tenant, EnrollmentService $service)
    {
        return response()->json($service->createSection(array_replace($request->all(), ['tenant_id' => $tenant->id()])), 201);
    }

    public function enrollments(Request $request, TenantContext $tenant)
    {
        $perPage = min($request->integer('per_page', 100), 500);
        $query = Enrollment::query()
            ->where('tenant_id', $tenant->id())
            ->with(['learner:id,code,full_name,email,user_type,status', 'classSection:id,code,name,section_type', 'course:id,code,title'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->input('source')))
            ->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->integer('course_id')))
            ->when($request->filled('class_section_id'), fn ($q) => $q->where('class_section_id', $request->integer('class_section_id')))
            ->when($request->filled('cohort_id'), fn ($q) => $q->where('cohort_id', $request->integer('cohort_id')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->input('q').'%';
                $q->whereHas('learner', fn ($user) => $user->where('code', 'like', $term)->orWhere('full_name', 'like', $term)->orWhere('email', 'like', $term));
            });

        return $request->boolean('virtual')
            ? $query->orderBy('id')->cursorPaginate($perPage)
            : $query->latest('updated_at')->paginate($perPage);
    }

    public function manualEnroll(Request $request, TenantContext $tenant, EnrollmentService $service)
    {
        return response()->json($service->enroll(array_replace($request->all(), ['tenant_id' => $tenant->id(), 'created_by' => $this->userId($request, (int) $tenant->id()), 'source' => $request->input('source', 'manual')])), 201);
    }

    public function selfEnroll(Request $request, ClassSection $section, EnrollmentService $service)
    {
        $userId = $this->userId($request, $section->tenant_id);
        return response()->json($service->enroll(['tenant_id' => $section->tenant_id, 'class_section_id' => $section->id, 'course_id' => $section->course_id, 'cohort_id' => $section->cohort_id, 'cohort_group_id' => $section->cohort_group_id, 'user_id' => $userId, 'source' => 'self', 'status' => 'active', 'created_by' => $userId]), 201);
    }

    public function invite(Request $request, ClassSection $section, EnrollmentService $service)
    {
        $rows = collect($request->input('user_ids', []))->map(fn ($userId) => ['class_section_id' => $section->id, 'course_id' => $section->course_id, 'cohort_id' => $section->cohort_id, 'cohort_group_id' => $section->cohort_group_id, 'user_id' => $userId, 'source' => 'invite', 'status' => 'pending'])->all();
        return response()->json($service->bulkEnroll($section->tenant_id, $rows, $this->userId($request, $section->tenant_id), 'invite'));
    }

    public function bulkEnroll(Request $request, TenantContext $tenant, EnrollmentService $service)
    {
        return $service->bulkEnroll((int) $tenant->id(), $request->input('rows', []), $this->userId($request, (int) $tenant->id()), $request->input('source', 'bulk'));
    }

    public function bulkAction(Request $request, TenantContext $tenant, EnrollmentService $service)
    {
        return $service->bulkAction((int) $tenant->id(), $request->input('ids', []), $request->input('action'), $this->userId($request, (int) $tenant->id()));
    }

    public function updateEnrollment(Request $request, TenantContext $tenant, Enrollment $enrollment, EnrollmentService $service)
    {
        abort_if((int) $enrollment->tenant_id !== (int) $tenant->id(), 404);

        $data = $request->validate([
            'source' => ['required', Rule::in(['manual', 'bulk', 'sis', 'self', 'invite', 'api'])],
            'status' => ['required', Rule::in(['pending', 'active', 'suspended', 'completed', 'withdrawn', 'expired'])],
            'sis_enrollment_id' => ['nullable', 'string', 'max:120'],
            'completion_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'risk_score' => ['required', 'numeric', 'min:0', 'max:100'],
            'expires_at' => ['nullable', 'date'],
            'metadata_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['status'] !== $enrollment->status) {
            $enrollment = $service->transition($enrollment, $data['status'], $this->userId($request, (int) $tenant->id()), ['source' => 'enrollment-edit']);
        }

        $metadata = $enrollment->metadata ?: [];
        if (array_key_exists('metadata_note', $data)) {
            $metadata['note'] = $data['metadata_note'];
        }

        $enrollment->forceFill([
            'source' => $data['source'],
            'sis_enrollment_id' => $data['sis_enrollment_id'] ?? null,
            'completion_percent' => $data['completion_percent'],
            'risk_score' => $data['risk_score'],
            'expires_at' => $data['expires_at'] ?? null,
            'metadata' => $metadata,
        ])->save();

        return $enrollment->fresh()->load(['learner:id,code,full_name,email,user_type,status', 'classSection:id,code,name,section_type', 'course:id,code,title']);
    }

    public function transition(Request $request, Enrollment $enrollment, EnrollmentService $service)
    {
        return $service->transition($enrollment, $request->input('status'), $this->userId($request, $enrollment->tenant_id), $request->input('metadata', []))
            ->load(['learner:id,code,full_name,email,user_type,status', 'classSection:id,code,name,section_type', 'course:id,code,title']);
    }

    public function assignTeacher(Request $request, ClassSection $section, EnrollmentService $service)
    {
        return response()->json($service->assignTeacher($section, $request->all()), 201);
    }

    public function import(Request $request, TenantContext $tenant, EnrollmentImportService $imports)
    {
        $format = $request->input('format', $request->hasFile('file') ? $request->file('file')->getClientOriginalExtension() : 'api');
        $actorId = $this->userId($request, (int) $tenant->id());
        if ($request->hasFile('file')) {
            return response()->json($imports->createFromUpload((int) $tenant->id(), $request->file('file'), $format, $actorId, $request->boolean('queue', true)), 202);
        }

        return response()->json($imports->createFromApiRows((int) $tenant->id(), $request->input('rows', []), $actorId, $request->boolean('queue', false)), 202);
    }

    public function importShow(EnrollmentImportJob $job)
    {
        return $job;
    }

    public function analytics(Request $request, TenantContext $tenant, EnrollmentAnalyticsService $analytics)
    {
        return $analytics->summary((int) $tenant->id(), $request->only(['course_id', 'class_section_id', 'cohort_id']));
    }

    private function userId(Request $request, int $tenantId): int
    {
        if ($request->user()) return (int) $request->user()->id;
        if ($request->header('X-Demo-User-Email')) return (int) (LmsUser::query()->where('tenant_id', $tenantId)->where('email', $request->header('X-Demo-User-Email'))->value('id') ?: 1);
        return 1;
    }
}
