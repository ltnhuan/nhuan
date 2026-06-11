<?php
namespace App\Services;
use App\Models\CourseComponent;use App\Models\ExamAttempt;
class LearningPathQuizIntegrationService
{
    public function __construct(private readonly CompletionEngineService $completion) {}
    public function syncQuizCompletion(ExamAttempt $attempt): void { $exam=$attempt->exam; if(!$exam->component_id) return; $component=CourseComponent::query()->find($exam->component_id); if(!$component) return; $this->completion->markComponentCompleted($attempt->tenant_id,$attempt->user_id,$component,['score'=>(float)$attempt->score,'progress_percent'=>$attempt->pass_status==='passed'?100:0,'allow_retry'=>true]); }
}
