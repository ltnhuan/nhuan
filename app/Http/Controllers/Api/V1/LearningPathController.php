<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\LearningCompletion;
use App\Models\LearningPathRule;
use App\Models\LmsUser;
use App\Models\UserCourseProgress;
use App\Services\CompletionEngineService;
use App\Services\LearningEventService;
use App\Services\LearningPathRuleService;
use App\Services\ManualApprovalService;
use App\Services\UnlockEngineService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LearningPathController extends Controller
{
    public function rules(Course $course, LearningPathRuleService $rules)
    {
        return $rules->getRulesForCourse($course->id);
    }

    public function storeRule(Request $request, Course $course, LearningPathRuleService $rules)
    {
        return $rules->createRule($request->all() + ['tenant_id' => $course->tenant_id, 'course_id' => $course->id, 'created_by' => $request->user()?->id ?? 1]);
    }

    public function updateRule(Request $request, LearningPathRule $rule, LearningPathRuleService $rules)
    {
        return $rules->updateRule($rule, $request->all());
    }

    public function deleteRule(LearningPathRule $rule, LearningPathRuleService $rules)
    {
        $rules->deleteRule($rule); return response()->noContent();
    }

    public function myProgress(Request $request, Course $course)
    {
        return UserCourseProgress::query()->where('course_id', $course->id)->where('user_id', $request->user()?->id ?? 1)->firstOrFail();
    }

    public function userProgress(Request $request, Course $course)
    {
        return UserCourseProgress::query()->where('course_id', $course->id)->paginate(50);
    }

    public function appendEvent(Request $request, LearningEventService $events)
    {
        return $events->appendEvent($request->all(), $request);
    }

    public function startComponent(Request $request, CourseComponent $component, CompletionEngineService $completion)
    {
        return $completion->markComponentStarted($component->tenant_id, $request->user()?->id ?? 1, $component);
    }

    public function componentProgress(Request $request, CourseComponent $component, CompletionEngineService $completion)
    {
        return $completion->markComponentProgress($component->tenant_id, $request->user()?->id ?? 1, $component, (float) $request->input('progress_percent'), $request->input('metadata', []));
    }

    public function completeComponent(Request $request, CourseComponent $component, CompletionEngineService $completion)
    {
        return $completion->markComponentCompleted($component->tenant_id, $request->user()?->id ?? 1, $component, $request->input('evidence', []));
    }

    public function accessCheck(Request $request, CourseComponent $component, UnlockEngineService $unlock)
    {
        $user = LmsUser::query()->findOrFail($request->user()?->id ?? 1);
        return $unlock->canAccessComponent($user, $component);
    }

    public function requestApproval(Request $request, LearningCompletion $completion, ManualApprovalService $manual)
    {
        return $manual->requestApproval($completion, $request->user()?->id ?? 1, $request->input('note'));
    }

    public function approveCompletion(Request $request, LearningCompletion $completion, ManualApprovalService $manual)
    {
        return $manual->approveCompletion($completion, $request->user()?->id ?? 1, $request->input('note'));
    }

    public function rejectCompletion(Request $request, LearningCompletion $completion, ManualApprovalService $manual)
    {
        return $manual->rejectCompletion($completion, $request->user()?->id ?? 1, $request->input('note'));
    }
}
