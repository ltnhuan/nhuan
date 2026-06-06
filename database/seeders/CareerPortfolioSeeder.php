<?php

namespace Database\Seeders;

use App\Models\CareerTimelineEvent;
use App\Models\CompetencyRecord;
use App\Models\DigitalPortfolio;
use App\Models\LmsUser;
use App\Models\PortfolioItem;
use App\Models\SkillDefinition;
use App\Services\CareerPortfolioService;
use Illuminate\Database\Seeder;

class CareerPortfolioSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 1;
        $service = app(CareerPortfolioService::class);
        $service->defaultSkillDefinitions($tenantId);
        $skills = SkillDefinition::query()->where('tenant_id', $tenantId)->get();
        $students = LmsUser::query()->where('tenant_id', $tenantId)->where('user_type', 'student')->take(120)->get();
        foreach ($students as $index => $student) {
            $service->ensureProfile($tenantId, $student->id, [
                'headline' => ($index % 3 === 0 ? 'Ứng viên thực tập kỹ thuật' : 'Sinh viên sẵn sàng việc làm').' - '.$student->full_name,
                'visibility' => $index % 4 === 0 ? 'private' : 'public',
                'lifecycle_status' => match ($index % 4) { 0 => 'student', 1 => 'graduate', 2 => 'alumni', default => 'employed' },
                'resume_data' => ['objective' => 'Tìm cơ hội thực tập/việc làm đúng chuyên ngành.', 'template' => 'compact'],
            ]);
            $portfolio = DigitalPortfolio::query()->where('tenant_id', $tenantId)->where('user_id', $student->id)->firstOrFail();
            foreach (['assignment','project','certificate','competition','student_activity','internship'] as $itemIndex => $type) {
                PortfolioItem::query()->updateOrCreate(
                    ['tenant_id'=>$tenantId,'portfolio_id'=>$portfolio->id,'title'=>ucfirst($type).' mẫu '.$student->code],
                    ['user_id'=>$student->id,'item_type'=>$type,'description'=>'Minh chứng '.$type.' trong hành trình học tập.', 'issuer'=>$type === 'certificate' ? 'EraLMS College' : null, 'evidence_url'=>'https://example.edu/portfolio/'.$student->code.'/'.$type, 'issued_at'=>now()->subMonths($itemIndex + 1), 'verification_code'=>$type === 'certificate' ? 'CERT'.$student->id.$itemIndex : null, 'visibility'=>$index % 4 === 0 ? 'private' : 'public', 'status'=>'published', 'metadata'=>['demo'=>true]]
                );
            }
            $service->recalculatePortfolioScore($portfolio);
            foreach ($skills->take(10) as $skill) {
                $service->upsertSkill($tenantId, $student->id, $skill->id, 55 + (($student->id + $skill->id) % 41), ['source'=>'rubric_outcome','evidence'=>['portfolio'=>true]]);
            }
            foreach ([['admission','Tuyển sinh'], ['coursework','Hoàn thành học phần'], ['internship','Thực tập doanh nghiệp'], ['employment','Việc làm đầu tiên']] as $offset => [$type, $title]) {
                CareerTimelineEvent::query()->updateOrCreate(['tenant_id'=>$tenantId,'user_id'=>$student->id,'event_type'=>$type], ['title'=>$title, 'description'=>'Mốc '.$title.' của '.$student->full_name, 'event_date'=>now()->subMonths(18 - ($offset * 5)), 'metadata'=>['demo'=>true]]);
            }
            foreach ([['CLO','CLO1','Vận dụng kiến thức nền'], ['PLO','PLO2','Năng lực nghề nghiệp'], ['competency','COMP-AI','Ứng dụng AI trong học tập']] as [$type, $code, $title]) {
                CompetencyRecord::query()->updateOrCreate(['tenant_id'=>$tenantId,'user_id'=>$student->id,'code'=>$code], ['outcome_type'=>$type,'title'=>$title,'score'=>60 + ($student->id % 35),'attainment_status'=>$student->id % 5 === 0 ? 'in_progress' : 'achieved','evidence'=>['source'=>'demo']]);
            }
        }
    }
}
