<?php

namespace Tests\Feature;

use App\Models\IntegrationEvent;
use App\Models\IntegrationMapping;
use App\Models\IntegrationSystem;
use App\Models\SyncConflict;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\InboundWebhookService;
use App\Services\IntegrationConfigService;
use App\Services\MappingService;
use App\Services\OutboundWebhookService;
use App\Services\SyncJobService;
use Database\Seeders\CoreSeeder;
use Database\Seeders\CourseStudioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class IntegrationHubFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->seed(CourseStudioSeeder::class);
    }

    public function test_connection_mock_sis(): void
    {
        $system = $this->system();
        $result = app(IntegrationConfigService::class)->validateConnection($system);
        $this->assertTrue($result['ok']);
        $this->assertSame('active', $system->fresh()->status);
    }

    public function test_receive_webhook_with_valid_signature_and_reject_invalid_signature(): void
    {
        $system = $this->system();
        $payload = ['event_key'=>'sis.student.updated','entity_type'=>'student','entity_id'=>'S1','idempotency_key'=>'idem-1'];
        $body = json_encode($payload);
        $request = Request::create('/webhook', 'POST', [], [], [], ['HTTP_X_ERALMS_SIGNATURE' => hash_hmac('sha256', $body, 'secret')], $body);

        $event = app(InboundWebhookService::class)->receive($system, $request);
        $this->assertSame('success', $event->status);

        $bad = Request::create('/webhook', 'POST', [], [], [], ['HTTP_X_ERALMS_SIGNATURE' => 'bad'], $body);
        $this->expectException(\RuntimeException::class);
        app(InboundWebhookService::class)->receive($system, $bad);
    }

    public function test_idempotency_prevents_duplicate_events(): void
    {
        $system = $this->system();
        $payload = ['event_key'=>'sis.student.updated','entity_type'=>'student','idempotency_key'=>'same-key'];
        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, 'secret');
        $request = fn () => Request::create('/webhook', 'POST', [], [], [], ['HTTP_X_ERALMS_SIGNATURE' => $signature], $body);

        app(InboundWebhookService::class)->receive($system, $request());
        app(InboundWebhookService::class)->receive($system, $request());

        $this->assertSame(1, IntegrationEvent::query()->where('idempotency_key','same-key')->count());
    }

    public function test_mapping_local_external_and_conflict(): void
    {
        $system = $this->system();
        $mapping = app(MappingService::class)->createMapping($system, 'student', '1', 'EXT-1', 'S001');

        $this->assertSame('1', app(MappingService::class)->findLocalByExternal($system, 'student', 'EXT-1'));
        $this->assertSame('EXT-1', app(MappingService::class)->findExternalByLocal($system, 'student', '1'));

        app(MappingService::class)->createMapping($system, 'student', '2', 'EXT-1');
        $this->assertSame(1, SyncConflict::query()->where('system_id', $system->id)->count());
        $this->assertSame('active', $mapping->mapping_status);
    }

    public function test_full_sync_users_creates_mapping(): void
    {
        $system = $this->system();
        $job = app(SyncJobService::class)->fullSyncUsers($system, 1);

        $this->assertSame('success', $job->status);
        $this->assertGreaterThan(0, IntegrationMapping::query()->where('system_id', $system->id)->count());
    }

    public function test_push_grade_creates_outbound_event(): void
    {
        $system = $this->system();
        $job = app(SyncJobService::class)->pushGradesToSIS($system, [['user_id'=>1,'score'=>8.5]], 1);

        $this->assertSame('success', $job->status);
        $this->assertDatabaseHas('integration_events', ['system_id'=>$system->id, 'event_key'=>'lms.grade.synced', 'direction'=>'outbound']);
    }

    public function test_retry_webhook_failed_delivery(): void
    {
        $system = $this->system();
        $endpoint = WebhookEndpoint::query()->create(['tenant_id'=>1,'system_id'=>$system->id,'name'=>'Failing endpoint','url'=>'https://example.invalid/hook','secret'=>'secret','subscribed_events'=>['lms.grade.synced'],'status'=>'active','settings'=>['mock_fail'=>true]]);
        $event = IntegrationEvent::query()->create(['tenant_id'=>1,'system_id'=>$system->id,'event_key'=>'lms.grade.synced','direction'=>'outbound','entity_type'=>'grade','idempotency_key'=>'retry-1','payload'=>['ok'=>true],'status'=>'pending']);
        $delivery = WebhookDelivery::query()->create(['tenant_id'=>1,'endpoint_id'=>$endpoint->id,'event_id'=>$event->id,'payload'=>['ok'=>true],'status'=>'failed','attempts'=>1,'next_retry_at'=>now()->subMinute()]);

        $count = app(OutboundWebhookService::class)->retryFailedDeliveries();

        $this->assertSame(1, $count);
        $this->assertGreaterThan(1, $delivery->fresh()->attempts);
    }

    public function test_dashboard_load_event_logs(): void
    {
        $system = $this->system();
        IntegrationEvent::query()->create(['tenant_id'=>1,'system_id'=>$system->id,'event_key'=>'sis.student.updated','direction'=>'inbound','entity_type'=>'student','idempotency_key'=>'dash-1','payload'=>[],'status'=>'success']);

        $headers = ['X-Demo-User-Email' => 'admin.lms@vabis.edu.vn'];
        $events = $this->withHeaders($headers)->getJson('/api/v1/integrations/events')->assertOk()->json('data');
        $health = $this->withHeaders($headers)->getJson('/api/v1/integrations/health')->assertOk()->json();

        $this->assertNotEmpty($events);
        $this->assertArrayHasKey('events', $health);
    }

    private function system(): IntegrationSystem
    {
        return IntegrationSystem::query()->first() ?: IntegrationSystem::query()->create(['tenant_id'=>1,'code'=>'SIS-MOCK','name'=>'Mock SIS','system_type'=>'sis','base_url'=>'https://sis.example.test','auth_type'=>'api_key','status'=>'active','settings'=>['mock'=>true,'webhook_secret'=>'secret']]);
    }
}
