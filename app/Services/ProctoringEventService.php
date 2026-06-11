<?php
namespace App\Services;
use App\Models\ExamAttempt;use App\Models\ExamAttemptEvent;
class ProctoringEventService
{
    public function recordEvent(ExamAttempt $attempt,string $type,?float $value=null,array $metadata=[]): ExamAttemptEvent { $event=ExamAttemptEvent::query()->create(['tenant_id'=>$attempt->tenant_id,'attempt_id'=>$attempt->id,'user_id'=>$attempt->user_id,'event_type'=>$type,'event_value'=>$value,'metadata'=>$metadata]); $score=$this->calculateSuspiciousScore($attempt); $attempt->forceFill(['suspicious_score'=>$score,'status'=>$score>=60?'flagged':$attempt->status])->save(); return $event; }
    public function calculateSuspiciousScore(ExamAttempt $attempt): float { $weights=['tab_hidden'=>10,'fullscreen_exit'=>15,'copy_attempt'=>20,'paste_attempt'=>20,'network_reconnect'=>8,'suspicious'=>25]; $score=0; foreach($weights as $event=>$weight){$score += $attempt->events()->where('event_type',$event)->count()*$weight;} return min(100,$score); }
    public function flagAttemptIfNeeded(ExamAttempt $attempt): bool { if($attempt->suspicious_score>=60){$attempt->forceFill(['status'=>'flagged'])->save(); return true;} return false; }
}
