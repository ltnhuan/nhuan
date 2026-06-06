<?php

namespace Database\Seeders;

use App\Models\IntegrationEvent;
use App\Models\IntegrationMapping;
use App\Models\IntegrationSystem;
use App\Models\LmsUser;
use App\Models\Course;
use App\Models\SyncConflict;
use App\Models\SyncJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class IntegrationSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 1;
        $system = IntegrationSystem::query()->updateOrCreate(['tenant_id'=>$tenantId,'code'=>'SIS-MOCK'], ['name'=>'Mock SIS Gateway','system_type'=>'sis','base_url'=>'https://sis-mock.example.test/api','auth_type'=>'api_key','credentials_encrypted'=>Crypt::encryptString(json_encode(['api_key'=>'seeded-secret'])),'status'=>'active','settings'=>['mock'=>true,'webhook_secret'=>'sis-webhook-secret','queues'=>['sync-sis','webhook','integration-retry']]]);
        $endpoint = WebhookEndpoint::query()->updateOrCreate(['tenant_id'=>$tenantId,'system_id'=>$system->id,'name'=>'SIS outbound webhook'], ['url'=>'https://sis-mock.example.test/webhooks/lms','secret'=>'outbound-secret','subscribed_events'=>['lms.grade.synced','lms.attendance.updated','lms.progress.updated'],'status'=>'active','settings'=>['mock_fail'=>true]]);

        foreach (LmsUser::query()->where('tenant_id',$tenantId)->limit(100)->get() as $user) {
            IntegrationMapping::query()->updateOrCreate(['tenant_id'=>$tenantId,'system_id'=>$system->id,'entity_type'=>$user->user_type === 'teacher' ? 'teacher' : 'student','local_id'=>(string)$user->id], ['external_id'=>'SIS-U-'.$user->id,'external_code'=>$user->code,'mapping_status'=>'active','metadata'=>['email'=>$user->email]]);
        }
        foreach (Course::query()->where('tenant_id',$tenantId)->limit(20)->get() as $course) {
            IntegrationMapping::query()->updateOrCreate(['tenant_id'=>$tenantId,'system_id'=>$system->id,'entity_type'=>'course','local_id'=>(string)$course->id], ['external_id'=>'SIS-C-'.$course->id,'external_code'=>$course->code,'mapping_status'=>'active','metadata'=>['title'=>$course->title]]);
        }
        foreach (range(1, 20) as $i) {
            IntegrationMapping::query()->updateOrCreate(['tenant_id'=>$tenantId,'system_id'=>$system->id,'entity_type'=>'class','local_id'=>(string)$i], ['external_id'=>'SIS-CLASS-'.$i,'external_code'=>'CLS'.str_pad((string)$i,3,'0',STR_PAD_LEFT),'mapping_status'=>'active','metadata'=>['name'=>'Lớp tích hợp '.$i]]);
        }
        foreach ([['sis.student.updated','inbound','success'],['sis.enrollment.created','inbound','failed'],['lms.grade.synced','outbound','retrying'],['lms.attendance.updated','outbound','success']] as $i => [$key,$direction,$status]) {
            $event = IntegrationEvent::query()->updateOrCreate(['system_id'=>$system->id,'event_key'=>$key,'idempotency_key'=>'seed-'.$i], ['tenant_id'=>$tenantId,'direction'=>$direction,'entity_type'=>explode('.',$key)[1],'entity_id'=>(string)$i,'payload'=>['demo'=>true,'event_key'=>$key],'status'=>$status,'attempts'=>$status === 'success' ? 1 : 2,'error_message'=>$status === 'failed' ? 'Demo failure' : null,'processed_at'=>$status === 'success' ? now() : null]);
            if ($direction === 'outbound') WebhookDelivery::query()->updateOrCreate(['tenant_id'=>$tenantId,'endpoint_id'=>$endpoint->id,'event_id'=>$event->id], ['payload'=>$event->payload,'status'=>$status === 'success' ? 'success' : 'retrying','attempts'=>2,'next_retry_at'=>now()->addMinutes(5)]);
        }

        $syncJobs = [
            ['full_sync','users','success',5185,5185,0,now()->subHours(3),now()->subHours(2)->subMinutes(42),null],
            ['full_sync','classes','success',100,100,0,now()->subHours(2)->subMinutes(30),now()->subHours(2)->subMinutes(24),null],
            ['pull','enrollments','success',50000,49920,80,now()->subHours(2),now()->subHour()->subMinutes(25),['summary'=>'80 bản ghi bị bỏ qua do mã lớp hoặc mã học viên không còn hiệu lực','samples'=>['SIS-ENR-1042','SIS-ENR-3188','SIS-ENR-4201']]],
            ['push','grades','running',1250,830,12,now()->subMinutes(24),null,['summary'=>'Đang đẩy điểm tổng kết sang SIS, 12 dòng đang chờ retry','retry_after_minutes'=>10]],
            ['push','attendance','failed',100,84,16,now()->subMinutes(58),now()->subMinutes(45),['summary'=>'SIS từ chối 16 phiên điểm danh do thiếu mã phòng học','next_action'=>'Bổ sung mapping phòng học rồi chạy manual retry']],
            ['manual_retry','progress','pending',300,0,0,null,null,['summary'=>'Hàng đợi retry cho tiến độ học tập video/xAPI']],
        ];

        foreach ($syncJobs as [$jobType,$entityType,$status,$total,$success,$failed,$startedAt,$finishedAt,$errorReport]) {
            SyncJob::query()->updateOrCreate(
                ['tenant_id'=>$tenantId,'system_id'=>$system->id,'job_type'=>$jobType,'entity_type'=>$entityType],
                ['status'=>$status,'total_count'=>$total,'success_count'=>$success,'failed_count'=>$failed,'started_at'=>$startedAt,'finished_at'=>$finishedAt,'error_report'=>$errorReport,'created_by'=>1]
            );
        }

        SyncConflict::query()->updateOrCreate(['tenant_id'=>$tenantId,'system_id'=>$system->id,'entity_type'=>'student','local_id'=>'1','external_id'=>'SIS-U-DUP'], ['conflict_type'=>'duplicate','local_snapshot'=>['id'=>1],'external_snapshot'=>['id'=>'SIS-U-DUP'],'status'=>'open']);
    }
}
