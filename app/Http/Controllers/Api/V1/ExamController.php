<?php
namespace App\Http\Controllers\Api\V1;
use App\Models\ClassSection;use App\Models\Exam;use App\Models\ExamAnswer;use App\Models\ExamAttempt;use App\Models\ExamEnrollment;use App\Models\ExamResult;use App\Models\LmsUser;use App\Services\AttemptService;use App\Services\ExamEnrollmentService;use App\Services\ExamService;use App\Services\ManualGradingService;use App\Services\ProctoringEventService;use App\Services\TenantContext;use App\Support\ApiPagination;use Illuminate\Http\Request;use Illuminate\Routing\Controller;
class ExamController extends Controller
{
    public function index(Request $r,TenantContext $t){return Exam::query()->where('tenant_id',$t->id())->with(['course:id,code,title','questionBank:id,code,name','blueprint:id,code,name','creator:id,full_name,email'])->withCount(['sections','questions','attempts'])->when($r->filled('status'),fn($q)=>$q->where('status',$r->input('status')))->when($r->filled('exam_type'),fn($q)=>$q->where('exam_type',$r->input('exam_type')))->latest('updated_at')->paginate(ApiPagination::perPage($r,25));}
    public function store(Request $r,TenantContext $t,ExamService $s){return response()->json($s->createExam($r->all()+['tenant_id'=>$t->id(),'created_by'=>$r->user()?->id??1]),201);}
    public function show(Exam $exam){return $exam->load(['course:id,code,title','questionBank:id,code,name','blueprint:id,code,name,total_questions,total_score,duration_minutes','creator:id,full_name,email','approver:id,full_name,email','sections','questions.question']);}
    public function update(Request $r,Exam $exam,ExamService $s){return $s->updateExam($exam,$r->all());}
    public function build(Exam $exam,ExamService $s){return $s->buildExamFromBlueprint($exam);}
    public function publish(Exam $exam,ExamService $s){return $s->publishExam($exam);}
    public function close(Exam $exam,ExamService $s){return $s->closeExam($exam);}
    public function assignUser(Request $r,Exam $exam,ExamEnrollmentService $s){return $s->assignExamToUser($exam,$r->integer('user_id'),$r->user()?->id??1,$r->only(['available_from','available_until']));}
    public function assignClass(Request $r,Exam $exam,ExamEnrollmentService $s){$ids=$r->input('user_ids', LmsUser::query()->where('tenant_id',$exam->tenant_id)->where('user_type','student')->limit(50)->pluck('id')->all()); return ['assigned'=>$s->assignExamToClass($exam,$r->integer('class_id',1),$ids,$r->user()?->id??1)];}
    public function myExams(Request $r,TenantContext $t,ExamEnrollmentService $s){return $s->getAvailableExamsForUser((int)$t->id(),$this->userId($r,(int)$t->id()));}
    public function start(Request $r,Exam $exam,AttemptService $s){return response()->json($s->startAttempt($exam,$this->userId($r,$exam->tenant_id),$r),201);}
    public function attempt(ExamAttempt $attempt,AttemptService $s){return $s->getAttemptState($attempt->load('attemptQuestions'));}
    public function answer(Request $r,ExamAttempt $attempt,AttemptService $s){return $s->saveAnswer($attempt,$r->integer('attempt_question_id'),$r->input('answer_data',[]));}
    public function autosave(Request $r,ExamAttempt $attempt,AttemptService $s){return $s->autosaveAnswer($attempt,$r->integer('attempt_question_id'),$r->input('answer_data',[]));}
    public function markReview(Request $r,ExamAttempt $attempt,AttemptService $s){return $s->markReview($attempt,$r->integer('attempt_question_id'),$r->boolean('marked',true));}
    public function event(Request $r,ExamAttempt $attempt,ProctoringEventService $s){return $s->recordEvent($attempt,$r->input('event_type'),$r->input('event_value'),$r->input('metadata',[]));}
    public function submit(ExamAttempt $attempt,AttemptService $s){return $s->submitAttempt($attempt);}
    public function attempts(Request $r, Exam $exam){return $exam->attempts()->with('user:id,code,full_name,email,user_type')->latest()->paginate(ApiPagination::perPage($r,50));}
    public function results(Request $r, Exam $exam){return ExamResult::query()->where('exam_id',$exam->id)->with(['user:id,code,full_name,email,user_type','attempt:id,attempt_no,status,submitted_at','approver:id,full_name,email'])->latest()->paginate(ApiPagination::perPage($r,50));}
    public function resultClasses(Request $r,TenantContext $t)
    {
        $assignments=ExamEnrollment::query()->where('tenant_id',$t->id())->when($r->filled('exam_id'),fn($q)=>$q->where('exam_id',$r->integer('exam_id')))->when($r->filled('class_id'),fn($q)=>$q->where('class_id',$r->integer('class_id')))->whereNotNull('class_id')->get();
        $classes=ClassSection::query()->whereIn('id',$assignments->pluck('class_id')->unique())->with('course:id,code,title')->get()->keyBy('id');
        $exams=Exam::query()->whereIn('id',$assignments->pluck('exam_id')->unique())->with('course:id,code,title')->get()->keyBy('id');
        $rows=[];
        foreach($assignments->groupBy(fn($item)=>$item->class_id.'-'.$item->exam_id) as $group){
            $first=$group->first(); $userIds=$group->pluck('user_id')->unique()->values();
            $results=ExamResult::query()->where('exam_id',$first->exam_id)->whereIn('user_id',$userIds)->get();
            $attempts=ExamAttempt::query()->where('exam_id',$first->exam_id)->whereIn('user_id',$userIds)->get();
            $submitted=$attempts->whereIn('status',['submitted','graded','auto_submitted'])->pluck('user_id')->unique()->count();
            $rows[]=['class'=>$classes->get($first->class_id),'exam'=>$exams->get($first->exam_id),'candidates_count'=>$userIds->count(),'submitted_count'=>$submitted,'missing_count'=>max($userIds->count()-$submitted,0),'average_score'=>round((float)$results->avg('score'),2),'pass_rate'=>$results->count()?round($results->where('pass_status','passed')->count()*100/$results->count(),1):0];
        }
        return array_values($rows);
    }
    public function resultCandidates(ClassSection $class,Exam $exam)
    {
        $userIds=ExamEnrollment::query()->where('class_id',$class->id)->where('exam_id',$exam->id)->pluck('user_id')->unique()->values();
        $users=LmsUser::query()->whereIn('id',$userIds)->get()->keyBy('id');
        $attempts=ExamAttempt::query()->where('exam_id',$exam->id)->whereIn('user_id',$userIds)->latest('submitted_at')->get()->groupBy('user_id');
        $results=ExamResult::query()->where('exam_id',$exam->id)->whereIn('user_id',$userIds)->latest('id')->get()->groupBy('user_id');
        return $userIds->map(function($userId) use($users,$attempts,$results){
            $userAttempts=$attempts->get($userId,collect()); $latestAttempt=$userAttempts->first(); $bestResult=$results->get($userId,collect())->sortByDesc('percent')->first();
            return ['user'=>$users->get($userId),'status'=>$latestAttempt?->status ?? 'not_started','score'=>$bestResult?->score ?? $latestAttempt?->score,'max_score'=>$bestResult?->max_score ?? $latestAttempt?->max_score,'percent'=>$bestResult?->percent,'pass_status'=>$bestResult?->pass_status ?? $latestAttempt?->pass_status,'attempts_count'=>$userAttempts->count(),'submitted_at'=>$latestAttempt?->submitted_at,'attempt_id'=>$latestAttempt?->id];
        })->values();
    }
    public function resultAttemptDetail(ExamAttempt $attempt)
    {
        $attempt->load(['exam:id,code,title,total_score,pass_score','user:id,code,full_name,email','attemptQuestions','answers']);
        $answers=$attempt->answers->keyBy('attempt_question_id');
        return ['attempt'=>$attempt,'questions'=>$attempt->attemptQuestions->map(function($question) use($answers){$answer=$answers->get($question->id); return ['id'=>$question->id,'display_order'=>$question->display_order,'score'=>$question->score,'question'=>$question->question_snapshot,'options'=>$question->options_snapshot,'answer'=>$answer?->answer_data,'is_correct'=>$answer?->is_correct,'earned_score'=>$answer?->score,'feedback'=>$answer?->feedback];})->values()];
    }
    public function pending(TenantContext $t,ManualGradingService $s){return $s->listPendingEssayAnswers((int)$t->id());}
    public function grade(Request $r,ExamAnswer $answer,ManualGradingService $s){return $s->gradeEssayAnswer($answer,(float)$r->input('score'),$r->input('feedback',''),$r->user()?->id??1);}
    public function publishResult(Request $r,ExamResult $result,ManualGradingService $s){return $s->publishResult($result,$r->user()?->id??1);}
    private function userId(Request $request,int $tenantId): int { if($request->user()) return (int)$request->user()->id; if($request->header('X-Demo-User-Email')) return (int)(LmsUser::query()->where('tenant_id',$tenantId)->where('email',$request->header('X-Demo-User-Email'))->value('id') ?: 1); return 1; }
}
