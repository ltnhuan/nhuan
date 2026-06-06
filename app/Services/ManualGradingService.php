<?php
namespace App\Services;
use App\Models\ExamAnswer;use App\Models\ExamGradingLog;use App\Models\ExamResult;
class ManualGradingService
{
    public function listPendingEssayAnswers(int $tenantId){return ExamAnswer::query()->where('tenant_id',$tenantId)->whereNull('score')->with('attemptQuestion')->paginate(50);}
    public function gradeEssayAnswer(ExamAnswer $answer,float $score,string $feedback,int $actorId): ExamAnswer { $before=$answer->toArray(); $answer->forceFill(['score'=>$score,'feedback'=>$feedback,'graded_by'=>$actorId,'graded_at'=>now()])->save(); $this->log($answer,'manual_grade',$before,$answer->fresh()->toArray(),$actorId); return $answer; }
    public function overrideScore(ExamAnswer $answer,float $score,string $note,int $actorId): ExamAnswer { $before=$answer->toArray(); $answer->forceFill(['score'=>$score,'feedback'=>$note,'graded_by'=>$actorId,'graded_at'=>now()])->save(); $this->log($answer,'override',$before,$answer->fresh()->toArray(),$actorId,$note); return $answer; }
    public function publishResult(ExamResult $result,int $actorId): ExamResult { $result->forceFill(['published'=>true,'published_at'=>now(),'approved_by'=>$actorId])->save(); return $result; }
    private function log(ExamAnswer $answer,string $action,array $before,array $after,int $actorId,?string $note=null): void { ExamGradingLog::query()->create(['tenant_id'=>$answer->tenant_id,'attempt_id'=>$answer->attempt_id,'answer_id'=>$answer->id,'action'=>$action,'before'=>$before,'after'=>$after,'actor_id'=>$actorId,'note'=>$note,'created_at'=>now()]); }
}
