<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\CompetencyFramework;
use App\Models\CompetencyRecord;
use App\Models\Course;
use App\Models\CourseComponent;
use App\Models\Exam;
use App\Models\LearningOutcome;
use App\Models\LmsUser;
use App\Services\AccreditationReportService;
use App\Services\AchievementAnalyticsService;
use App\Services\AssessmentMappingService;
use App\Services\OBEFrameworkService;
use Illuminate\Database\Seeder;

class OBEAccreditationSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 1;
        $courses = Course::query()->where('tenant_id', $tenantId)->limit(10)->get();
        $obe = app(OBEFrameworkService::class);
        $assessment = app(AssessmentMappingService::class);

        foreach (range(1, 6) as $i) {
            LearningOutcome::query()->updateOrCreate(['tenant_id'=>$tenantId,'code'=>'PLO'.$i], ['name'=>'Program Learning Outcome '.$i,'type'=>'PLO','description'=>'Chuẩn đầu ra chương trình '.$i,'status'=>'active']);
        }
        foreach ($courses as $course) {
            foreach (range(1, 4) as $i) {
                $clo = LearningOutcome::query()->updateOrCreate(['tenant_id'=>$tenantId,'code'=>'CLO-'.$course->id.'-'.$i], ['name'=>'CLO '.$i.' - '.$course->title,'type'=>'CLO','description'=>'Chuẩn đầu ra học phần '.$i,'course_id'=>$course->id,'status'=>'active']);
                $plo = LearningOutcome::query()->where('tenant_id',$tenantId)->where('code','PLO'.(($i % 6) + 1))->first();
                $obe->mapOutcomePath(['tenant_id'=>$tenantId,'source_outcome_id'=>$clo->id,'target_outcome_id'=>$plo->id,'source_type'=>'CLO','source_id'=>$clo->id,'target_type'=>'PLO','target_id'=>$plo->id,'weight'=>0.8,'evidence_level'=>'assessed']);
                $obe->mapOutcomePath(['tenant_id'=>$tenantId,'source_outcome_id'=>$clo->id,'target_outcome_id'=>$clo->id,'source_type'=>'course','source_id'=>$course->id,'target_type'=>'CLO','target_id'=>$clo->id,'weight'=>1,'evidence_level'=>'reinforced']);
                if ($component = CourseComponent::query()->where('course_id',$course->id)->skip($i - 1)->first()) $obe->mapOutcomePath(['tenant_id'=>$tenantId,'source_outcome_id'=>$clo->id,'target_outcome_id'=>$clo->id,'source_type'=>'lesson','source_id'=>$component->id,'target_type'=>'CLO','target_id'=>$clo->id,'weight'=>0.5,'evidence_level'=>'introduced']);
                if (class_exists(Exam::class) && ($exam = Exam::query()->where('tenant_id',$tenantId)->first())) $assessment->mapAssessment($tenantId, 'quiz', $exam->id, [['outcome_id'=>$clo->id,'weight'=>1,'max_score'=>10]]);
                if (class_exists(Assignment::class) && ($assignment = Assignment::query()->where('tenant_id',$tenantId)->first())) $assessment->mapAssessment($tenantId, 'assignment', $assignment->id, [['outcome_id'=>$clo->id,'weight'=>1,'max_score'=>10]]);
            }
        }

        $framework = CompetencyFramework::query()->updateOrCreate(['tenant_id'=>$tenantId,'code'=>'AUNQA-TVET-OBE'], ['title'=>'AUN-QA/TVET Competency Framework','framework_type'=>'OBE','standard'=>'AUN-QA','description'=>'Khung năng lực phục vụ AUN-QA, GDNN, TVET','status'=>'active','settings'=>['scale'=>'0_100'],'created_by'=>1]);
        foreach ([['COMP-TECH','Năng lực chuyên môn'],['COMP-DIGI','Năng lực số'],['COMP-EN','Ngoại ngữ nghề nghiệp'],['COMP-SAFE','An toàn lao động'],['COMP-WORK','Tác phong công nghiệp']] as $order => [$code,$title]) {
            $item = $obe->createFrameworkItem($framework, ['code'=>$code,'title'=>$title,'item_type'=>'competency','level'=>'program','description'=>$title,'sort_order'=>$order + 1,'rubric'=>['threshold'=>70]]);
            foreach (LearningOutcome::query()->where('tenant_id',$tenantId)->where('type','PLO')->limit(2)->get() as $plo) $obe->mapOutcomePath(['tenant_id'=>$tenantId,'source_outcome_id'=>$plo->id,'target_outcome_id'=>$plo->id,'competency_item_id'=>$item->id,'source_type'=>'PLO','source_id'=>$plo->id,'target_type'=>'competency','target_id'=>$item->id,'weight'=>0.5,'evidence_level'=>'assessed']);
        }

        foreach (LmsUser::query()->where('tenant_id',$tenantId)->where('user_type','student')->limit(80)->get() as $student) {
            foreach (LearningOutcome::query()->where('tenant_id',$tenantId)->limit(8)->get() as $outcome) {
                CompetencyRecord::query()->updateOrCreate(['tenant_id'=>$tenantId,'user_id'=>$student->id,'code'=>$outcome->code], ['learning_outcome_id'=>$outcome->id,'outcome_type'=>$outcome->type,'title'=>$outcome->name,'score'=>55 + (($student->id + $outcome->id) % 45),'attainment_status'=>'achieved','evidence'=>['source'=>'obe_seed']]);
            }
        }
        app(AchievementAnalyticsService::class)->recalculate($tenantId);
        foreach ([['AUN-QA','pdf'],['TVET','xlsx'],['GDNN','docx']] as [$standard,$format]) app(AccreditationReportService::class)->generate($tenantId, $standard, $format, [], 1);
    }
}
