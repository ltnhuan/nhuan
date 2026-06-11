<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Gradebook;
use App\Models\GradeChangeLog;
use App\Models\LearnerGrade;
use App\Models\LmsUser;
use App\Services\GradeApprovalService;
use App\Services\GradeFormulaService;
use App\Services\GradeImportService;
use App\Services\GradeItemService;
use App\Services\GradebookService;
use App\Services\LearnerGradeService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class GradebookController extends Controller
{
    public function index(Request $request, TenantContext $tenant) { return Gradebook::query()->where('tenant_id', $tenant->id())->withCount(['items','summaries'])->latest('updated_at')->paginate($request->integer('per_page', 25)); }
    public function store(Request $request, TenantContext $tenant, GradebookService $service) { return response()->json($service->create(array_replace($request->all(), ['tenant_id' => $tenant->id(), 'created_by' => $this->userId($request, (int) $tenant->id())])), 201); }
    public function show(Gradebook $gradebook) { return $gradebook->load(['categories.items','items','batches']); }
    public function update(Request $request, Gradebook $gradebook, GradebookService $service) { return $service->update($gradebook, $request->all()); }
    public function activate(Gradebook $gradebook, GradebookService $service) { return $service->activate($gradebook); }
    public function lock(Request $request, Gradebook $gradebook, GradeApprovalService $service) { return $service->lock($gradebook, $this->userId($request, $gradebook->tenant_id)); }
    public function category(Request $request, Gradebook $gradebook, GradeItemService $service) { return response()->json($service->createCategory($gradebook, $request->all()), 201); }
    public function item(Request $request, Gradebook $gradebook, GradeItemService $service) { return response()->json($service->createItem($gradebook, $request->all()), 201); }
    public function reorder(Request $request, Gradebook $gradebook, GradeItemService $service) { $service->reorder($gradebook, $request->input('category_ids', []), $request->input('item_ids', [])); return ['ok' => true]; }
    public function pullSources(Request $request, Gradebook $gradebook, GradeImportService $service) { return $service->pullSources($gradebook, $this->userId($request, $gradebook->tenant_id)); }
    public function recalculate(Gradebook $gradebook, GradeFormulaService $service) { return $service->recalculate($gradebook); }

    public function matrix(Request $request, Gradebook $gradebook)
    {
        $viewer = $this->demoUser($request, $gradebook->tenant_id);
        $studentUserId = $viewer?->user_type === 'student' ? $viewer->id : null;
        $summaries = $gradebook->summaries()->when($studentUserId, fn ($q) => $q->where('user_id', $studentUserId))->paginate($request->integer('per_page', 100));
        $grades = LearnerGrade::query()->where('gradebook_id', $gradebook->id)->when($studentUserId, fn ($q) => $q->where('user_id', $studentUserId))->get()->groupBy('user_id');
        return [
            'gradebook' => $gradebook,
            'items' => $gradebook->items()->get(),
            'rows' => $summaries->getCollection()->map(fn ($summary) => ['user_id' => $summary->user_id, 'summary' => $summary, 'grades' => ($grades[$summary->user_id] ?? collect())->values()]),
            'pagination' => ['total' => $summaries->total(), 'per_page' => $summaries->perPage(), 'current_page' => $summaries->currentPage()],
        ];
    }

    public function override(Request $request, LearnerGrade $grade, LearnerGradeService $service) { return $service->override($grade, (float) $request->input('final_score'), $this->userId($request, $grade->tenant_id), $request->input('reason'), $request->boolean('allow_locked_override')); }
    public function submit(Request $request, Gradebook $gradebook, GradeApprovalService $service) { return response()->json($service->submit($gradebook, $this->userId($request, $gradebook->tenant_id), $request->input('title')), 201); }
    public function approve(Request $request, Gradebook $gradebook, GradeApprovalService $service) { return $service->approve($gradebook, $this->userId($request, $gradebook->tenant_id)); }
    public function reject(Request $request, Gradebook $gradebook, GradeApprovalService $service) { return $service->reject($gradebook, $this->userId($request, $gradebook->tenant_id), $request->input('reason')); }
    public function sync(Gradebook $gradebook, GradeApprovalService $service) { return $service->syncToSis($gradebook); }
    public function logs(Gradebook $gradebook, Request $request)
    {
        $viewer = $this->demoUser($request, $gradebook->tenant_id);
        $studentUserId = $viewer?->user_type === 'student' ? $viewer->id : null;
        return GradeChangeLog::query()
            ->where('gradebook_id', $gradebook->id)
            ->when($studentUserId, fn ($q) => $q->where('user_id', $studentUserId))
            ->when(! $studentUserId && $request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->latest('created_at')
            ->paginate(50);
    }

    private function userId(Request $request, int $tenantId): int
    {
        if ($request->user()) return (int) $request->user()->id;
        if ($request->header('X-Demo-User-Email')) return (int) (LmsUser::query()->where('tenant_id', $tenantId)->where('email', $request->header('X-Demo-User-Email'))->value('id') ?: 1);
        return 1;
    }

    private function demoUser(Request $request, int $tenantId): ?LmsUser
    {
        if ($request->user() instanceof LmsUser) return $request->user();
        if ($request->header('X-Demo-User-Email')) return LmsUser::query()->where('tenant_id', $tenantId)->where('email', $request->header('X-Demo-User-Email'))->first();
        return null;
    }
}
