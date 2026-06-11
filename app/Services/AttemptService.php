<?php
namespace App\Services;
use App\Models\Exam;use App\Models\ExamAnswer;use App\Models\ExamAttempt;use App\Models\ExamAttemptQuestion;use Illuminate\Http\Request;use Illuminate\Support\Facades\Cache;use Illuminate\Support\Str;
class AttemptService
{
    public function __construct(private readonly AutoGradingService $grading, private readonly LearningPathQuizIntegrationService $integration, private readonly ExamEnrollmentService $enrollments) {}
    public function startAttempt(Exam $exam,int $userId,?Request $request=null): ExamAttempt
    {
        if(! $this->enrollments->checkUserEligibility($exam,$userId)) throw new \RuntimeException('Người học chưa đủ điều kiện bắt đầu bài kiểm tra.');
        $count=ExamAttempt::query()->where('exam_id',$exam->id)->where('user_id',$userId)->count();
        if($count >= $exam->max_attempts) throw new \RuntimeException('Người học đã dùng hết số lần làm bài.');
        $attempt=ExamAttempt::query()->create(['tenant_id'=>$exam->tenant_id,'exam_id'=>$exam->id,'user_id'=>$userId,'attempt_no'=>$count+1,'session_uuid'=>(string)Str::uuid(),'status'=>'in_progress','started_at'=>now(),'max_score'=>$exam->total_score,'ip_address'=>$request?->ip(),'user_agent'=>$request?->userAgent(),'device_id'=>$request?->input('device_id')]);
        $this->generateAttemptQuestions($attempt);
        return $attempt->fresh('attemptQuestions');
    }
    public function generateAttemptQuestions(ExamAttempt $attempt): void
    {
        $exam=$attempt->exam; $questions=$exam->questions()->with('question.options','question.matchingPairs','question.fillBlankAnswers')->get(); if($exam->shuffle_questions) $questions=$questions->shuffle();
        foreach($questions->values() as $i=>$eq){$q=$eq->question; $options=$q->options->map(fn($o)=>$o->only(['option_key','content','media_url','sort_order']))->values()->all(); $secureOptions=$q->options->map(fn($o)=>$o->only(['option_key','content','media_url','sort_order','is_correct']))->values()->all(); if($exam->shuffle_options){shuffle($options); shuffle($secureOptions);} ExamAttemptQuestion::query()->create(['tenant_id'=>$attempt->tenant_id,'attempt_id'=>$attempt->id,'question_id'=>$q->id,'section_id'=>$eq->section_id,'display_order'=>$i+1,'score'=>$eq->score,'question_snapshot'=>$q->only(['id','code','question_type','title','stem','default_score','metadata']) + ['matching_pairs'=>$q->matchingPairs->toArray(),'fill_blank_answers'=>$q->fillBlankAnswers->toArray()],'options_snapshot'=>$secureOptions,'metadata'=>['client_options'=>$options]]);}
    }
    public function getAttemptState(ExamAttempt $attempt): array
    {
        // Không trả is_correct xuống client khi đang làm bài; đáp án đúng chỉ nằm trong snapshot server.
        return ['attempt'=>$attempt,'questions'=>$attempt->attemptQuestions->map(function($q){$question=$q->question_snapshot; unset($question['fill_blank_answers']); return ['id'=>$q->id,'display_order'=>$q->display_order,'score'=>$q->score,'question'=>$question,'options'=>$q->metadata['client_options']??[],'is_answered'=>$q->is_answered,'is_marked_review'=>$q->is_marked_review];})];
    }
    public function saveAnswer(ExamAttempt $attempt,int $attemptQuestionId,array $answerData,bool $autosave=false): ExamAnswer
    {
        $aq=ExamAttemptQuestion::query()->where('attempt_id',$attempt->id)->findOrFail($attemptQuestionId);
        $answer=ExamAnswer::query()->updateOrCreate(['attempt_id'=>$attempt->id,'attempt_question_id'=>$aq->id],['tenant_id'=>$attempt->tenant_id,'question_id'=>$aq->question_id,'answer_data'=>$answerData,'autosaved_at'=>$autosave?now():null]);
        $aq->forceFill(['is_answered'=>true])->save();
        return $answer;
    }
    public function autosaveAnswer(ExamAttempt $attempt,int $attemptQuestionId,array $answerData): ExamAnswer { return $this->saveAnswer($attempt,$attemptQuestionId,$answerData,true); }
    public function markReview(ExamAttempt $attempt,int $attemptQuestionId,bool $marked=true): ExamAttemptQuestion { $aq=ExamAttemptQuestion::query()->where('attempt_id',$attempt->id)->findOrFail($attemptQuestionId); $aq->forceFill(['is_marked_review'=>$marked])->save(); return $aq; }
    public function submitAttempt(ExamAttempt $attempt): ExamAttempt
    {
        return Cache::lock("exam-submit:{$attempt->id}",10)->block(3,function() use($attempt){ if($attempt->status==='graded') return $attempt; $submittedStatus=$attempt->status==='auto_submitted'?'auto_submitted':'submitted'; $attempt->forceFill(['status'=>$submittedStatus,'submitted_at'=>now(),'time_spent_seconds'=>now()->diffInSeconds($attempt->started_at)])->save(); $this->createMissingZeroAnswers($attempt->fresh('attemptQuestions')); foreach($attempt->answers()->with('attemptQuestion')->get() as $answer) $this->grading->gradeAnswer($answer); $graded=$this->grading->calculateAttemptScore($attempt->fresh()); $this->integration->syncQuizCompletion($graded); return $graded; });
    }
    public function autoSubmitExpiredAttempt(ExamAttempt $attempt): ExamAttempt { $attempt->forceFill(['status'=>'auto_submitted'])->save(); return $this->submitAttempt($attempt->fresh()); }
    public function preventDuplicateSubmit(ExamAttempt $attempt): bool { return in_array($attempt->status,['submitted','graded','auto_submitted'],true); }
    private function createMissingZeroAnswers(ExamAttempt $attempt): void
    {
        $answeredIds=$attempt->answers()->pluck('attempt_question_id')->all();
        foreach($attempt->attemptQuestions as $question){
            if(in_array($question->id,$answeredIds,true)) continue;
            ExamAnswer::query()->create(['tenant_id'=>$attempt->tenant_id,'attempt_id'=>$attempt->id,'attempt_question_id'=>$question->id,'question_id'=>$question->question_id,'answer_data'=>[],'is_correct'=>false,'score'=>0,'feedback'=>'Chưa trả lời','graded_at'=>now()]);
        }
    }
}
