<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Assignment;
use App\Models\AssignmentGrade;
use App\Models\AssignmentSubmission;
use App\Models\LmsUser;
use App\Models\Rubric;
use App\Services\AssignmentGradingService;
use App\Services\AssignmentService;
use App\Services\RubricService;
use App\Services\SubmissionService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AssignmentController extends Controller
{
    public function index(Request $request, TenantContext $tenant)
    {
        return Assignment::query()
            ->where('tenant_id', $tenant->id())
            ->when($request->filled('course_id'), fn ($q) => $q->where('course_id', $request->integer('course_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('due_from'), fn ($q) => $q->where('due_at', '>=', $request->input('due_from')))
            ->when($request->filled('due_to'), fn ($q) => $q->where('due_at', '<=', $request->input('due_to')))
            ->with(['rubric:id,title,max_score,status', 'course:id,title,code'])
            ->withCount([
                'submissions',
                'submissions as late_submissions_count' => fn ($query) => $query->where('status', 'late_submitted'),
            ])
            ->latest('updated_at')
            ->paginate($request->integer('per_page', 25));
    }

    public function store(Request $request, TenantContext $tenant, AssignmentService $service)
    {
        return response()->json($service->create(array_replace($request->all(), ['tenant_id'=>$tenant->id(), 'created_by'=>$this->userId($request, (int) $tenant->id())])), 201);
    }

    public function show(Request $request, Assignment $assignment)
    {
        $user = $this->demoUser($request, $assignment->tenant_id);
        return $assignment->load([
            'rubric.criteria.levels',
            'submissions' => fn ($query) => $user?->user_type === 'student' ? $query->where('user_id', $user->id) : $query,
            'submissions.files',
            'submissions.grade',
        ]);
    }

    public function update(Request $request, Assignment $assignment, AssignmentService $service)
    {
        return $service->update($assignment, $request->all());
    }

    public function schedule(Request $request, Assignment $assignment, AssignmentService $service)
    {
        return $service->schedule($assignment, $request->all());
    }

    public function deadline(Request $request, Assignment $assignment, AssignmentService $service)
    {
        return $service->deadlineWindow($assignment, $this->userId($request, $assignment->tenant_id));
    }

    public function publish(Assignment $assignment, AssignmentService $service)
    {
        return $service->publish($assignment);
    }

    public function close(Assignment $assignment, AssignmentService $service)
    {
        return $service->close($assignment);
    }

    public function submit(Request $request, Assignment $assignment, SubmissionService $service)
    {
        return response()->json($service->submit($assignment, $this->userId($request, $assignment->tenant_id), $request->all()), 201);
    }

    public function attachFiles(Request $request, AssignmentSubmission $submission, SubmissionService $service)
    {
        $this->assertOwnSubmissionForStudent($request, $submission);
        $files = $request->file('files', []);
        if (! is_array($files)) {
            $files = [$files];
        }
        return response()->json(['files' => $service->attachFiles($submission, $files, $this->userId($request, $submission->tenant_id))], 201);
    }

    public function resubmit(Request $request, AssignmentSubmission $submission, SubmissionService $service)
    {
        $this->assertOwnSubmissionForStudent($request, $submission);
        return response()->json($service->resubmit($submission, $request->all()), 201);
    }

    public function submissions(Request $request, Assignment $assignment)
    {
        $query = AssignmentSubmission::query()->where('assignment_id', $assignment->id)->with(['files','grade','user:id,code,full_name,email']);
        if ($request->boolean('mine')) {
            $query->where('user_id', $this->userId($request, $assignment->tenant_id));
        }
        return $query
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest('submitted_at')
            ->paginate($request->integer('per_page', 50));
    }

    public function grade(Request $request, AssignmentSubmission $submission, AssignmentGradingService $service)
    {
        return $service->gradeSubmission($submission, $request->all(), $this->userId($request, $submission->tenant_id));
    }

    public function approve(Request $request, AssignmentGrade $grade, AssignmentGradingService $service)
    {
        return $service->approveGrade($grade, $this->userId($request, $grade->tenant_id));
    }

    public function returnSubmission(Request $request, AssignmentSubmission $submission, AssignmentGradingService $service)
    {
        return $service->returnForRevision($submission, $request->input('feedback', ''), $this->userId($request, $submission->tenant_id));
    }

    public function rubrics(Request $request, TenantContext $tenant)
    {
        return Rubric::query()->where('tenant_id', $tenant->id())->with('criteria.levels')->latest('updated_at')->paginate($request->integer('per_page', 25));
    }

    public function storeRubric(Request $request, TenantContext $tenant, RubricService $service)
    {
        $rubric = $service->create(array_replace($request->all(), ['tenant_id'=>$tenant->id(), 'created_by'=>$this->userId($request, (int) $tenant->id())]));
        foreach ($request->input('criteria', []) as $criterionData) {
            $levels = $criterionData['levels'] ?? [];
            unset($criterionData['levels']);
            $criterion = $service->addCriterion($rubric, $criterionData);
            foreach ($levels as $levelData) {
                $service->addLevel($criterion, $levelData);
            }
        }
        return response()->json($rubric->fresh('criteria.levels'), 201);
    }

    public function updateRubric(Request $request, Rubric $rubric, RubricService $service)
    {
        return $service->update($rubric, $request->all())->fresh('criteria.levels');
    }

    private function userId(Request $request, int $tenantId): int
    {
        if ($request->user()) {
            return (int) $request->user()->id;
        }
        if ($request->header('X-Demo-User-Email')) {
            return (int) (LmsUser::query()->where('tenant_id', $tenantId)->where('email', $request->header('X-Demo-User-Email'))->value('id') ?: 1);
        }
        return 1;
    }

    private function demoUser(Request $request, int $tenantId): ?LmsUser
    {
        if ($request->user() instanceof LmsUser) {
            return $request->user();
        }
        if ($request->header('X-Demo-User-Email')) {
            return LmsUser::query()->where('tenant_id', $tenantId)->where('email', $request->header('X-Demo-User-Email'))->first();
        }
        return null;
    }

    private function assertOwnSubmissionForStudent(Request $request, AssignmentSubmission $submission): void
    {
        $user = $this->demoUser($request, $submission->tenant_id);
        if ($user?->user_type === 'student' && (int) $submission->user_id !== (int) $user->id) {
            abort(403, 'Student chỉ được thao tác trên bài nộp của mình.');
        }
    }
}
