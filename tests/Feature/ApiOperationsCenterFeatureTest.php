<?php

namespace Tests\Feature;

use App\Jobs\ApiOperations\RunApiSyncJob;
use App\Models\ApiDataMapping;
use App\Models\ApiEndpoint;
use App\Models\ApiEvent;
use App\Models\ApiRequestLog;
use App\Models\ApiSystem;
use App\Models\LmsUser;
use App\Models\Role;
use App\Models\Tenant;
use App\Services\ApiOperations\ApiGatewayService;
use App\Services\ApiOperations\ApiHealthService;
use App\Services\ApiOperations\DataMappingService;
use App\Services\ApiOperations\EventBusService;
use App\Services\ApiOperations\SyncJobService;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ApiOperationsCenterFeatureTest extends TestCase
{
    use RefreshDatabase;

    private array $headers = [
        'X-Tenant-Code' => 'VABIS',
        'X-Demo-User-Email' => 'admin.lms@vabis.edu.vn',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
    }

    public function test_create_system_and_endpoint(): void
    {
        $system = $this->withHeaders($this->headers)
            ->postJson('/api/v1/api-ops/systems', [
                'code' => 'CRM-TEST',
                'name' => 'CRM Test',
                'type' => 'crm',
                'base_url' => 'https://crm.example.test',
                'environment' => 'staging',
                'auth_type' => 'api_key',
                'status' => 'active',
            ])
            ->assertCreated()
            ->json('data');

        $this->withHeaders($this->headers)
            ->postJson('/api/v1/api-ops/endpoints', [
                'system_id' => $system['id'],
                'code' => 'CRM_LEADS',
                'name' => 'CRM leads',
                'method' => 'POST',
                'path' => '/leads',
                'request_schema' => ['required' => ['id']],
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'CRM_LEADS');
    }

    public function test_test_connection_writes_health_snapshot(): void
    {
        $system = $this->system();

        $this->withHeaders($this->headers)
            ->postJson("/api/v1/api-ops/systems/{$system->id}/test-connection")
            ->assertOk()
            ->assertJsonPath('data.ok', true);

        $this->assertDatabaseHas('api_health_snapshots', ['system_id' => $system->id]);
    }

    public function test_api_ops_actions_are_scoped_to_current_tenant(): void
    {
        $otherTenant = Tenant::query()->create([
            'code' => 'OTHER',
            'name' => 'Other tenant',
            'status' => 'active',
        ]);
        $foreignSystem = ApiSystem::query()->create([
            'tenant_id' => $otherTenant->id,
            'code' => 'FOREIGN-SIS',
            'name' => 'Foreign SIS',
            'type' => 'sis',
            'base_url' => 'https://foreign.example.test',
            'environment' => 'staging',
            'auth_type' => 'hmac',
            'status' => 'active',
            'settings' => [],
        ]);
        $foreignEndpoint = ApiEndpoint::query()->create([
            'tenant_id' => $otherTenant->id,
            'system_id' => $foreignSystem->id,
            'code' => 'FOREIGN_STUDENTS',
            'name' => 'Foreign students',
            'method' => 'POST',
            'path' => '/students',
            'full_url' => 'https://foreign.example.test/students',
            'request_schema' => ['required' => ['id']],
            'response_schema' => [],
            'timeout_ms' => 5000,
            'retry_policy' => ['max_attempts' => 3],
            'rate_limit' => ['per_minute' => 120],
            'status' => 'active',
        ]);

        $created = $this->withHeaders($this->headers)
            ->postJson('/api/v1/api-ops/systems', [
                'tenant_id' => $otherTenant->id,
                'code' => 'TENANT-INJECTION',
                'name' => 'Tenant injection',
                'type' => 'sis',
                'base_url' => 'https://safe.example.test',
                'environment' => 'staging',
                'auth_type' => 'hmac',
            ])
            ->assertCreated()
            ->json('data');

        $this->assertSame(1, $created['tenant_id']);

        $this->withHeaders($this->headers)
            ->postJson('/api/v1/api-ops/endpoints', [
                'system_id' => $foreignSystem->id,
                'code' => 'BAD_ENDPOINT',
                'name' => 'Bad endpoint',
                'method' => 'POST',
                'path' => '/bad',
            ])
            ->assertNotFound();

        $this->withHeaders($this->headers)
            ->postJson("/api/v1/api-ops/endpoints/{$foreignEndpoint->id}/test", ['body' => ['id' => 'S1']])
            ->assertNotFound();
    }

    public function test_gateway_logs_request_and_masks_sensitive_payload(): void
    {
        $endpoint = $this->endpoint();

        $log = app(ApiGatewayService::class)->sendRequest($endpoint, [
            'id' => 'S1',
            'password' => 'secret',
            'profile' => ['token' => 'abc'],
        ], ['Authorization' => 'Bearer secret']);

        $this->assertSame(200, $log->status_code);
        $this->assertSame('***MASKED***', $log->request_body['password']);
        $this->assertSame('***MASKED***', $log->request_body['profile']['token']);
        $this->assertSame('***MASKED***', $log->request_headers['Authorization']);
    }

    public function test_inbound_webhook_accepts_valid_hmac_and_rejects_invalid_signature(): void
    {
        $system = $this->system(['settings' => ['webhook_secret' => 'secret']]);
        $payload = ['event_key' => 'sis.student.updated', 'entity_type' => 'student', 'entity_id' => 'S1', 'idempotency_key' => 'idem-valid'];
        $signature = hash_hmac('sha256', json_encode($payload), 'secret');

        $this->withHeaders($this->headers + ['X-Api-Ops-Signature' => $signature])
            ->postJson("/api/v1/api-ops/webhooks/inbound/{$system->code}", $payload)
            ->assertAccepted()
            ->assertJsonPath('data.event_key', 'sis.student.updated');

        $this->withHeaders($this->headers + ['X-Api-Ops-Signature' => 'bad'])
            ->postJson("/api/v1/api-ops/webhooks/inbound/{$system->code}", $payload + ['idempotency_key' => 'idem-bad'])
            ->assertUnauthorized();
    }

    public function test_idempotency_prevents_duplicate_events(): void
    {
        $system = $this->system(['settings' => ['webhook_secret' => 'secret']]);
        $payload = ['event_key' => 'sis.student.updated', 'entity_type' => 'student', 'idempotency_key' => 'same-key'];
        $signature = hash_hmac('sha256', json_encode($payload), 'secret');

        $this->withHeaders($this->headers + ['X-Api-Ops-Signature' => $signature])->postJson("/api/v1/api-ops/webhooks/inbound/{$system->code}", $payload)->assertAccepted();
        $this->withHeaders($this->headers + ['X-Api-Ops-Signature' => $signature])->postJson("/api/v1/api-ops/webhooks/inbound/{$system->code}", $payload)->assertAccepted();

        $this->assertSame(1, ApiEvent::query()->where('idempotency_key', 'same-key')->count());
    }

    public function test_data_mapping_maps_payload_and_validates_required_fields(): void
    {
        $source = $this->system(['code' => 'SIS-MAP']);
        $target = $this->system(['code' => 'LMS-MAP', 'type' => 'lms']);
        ApiDataMapping::query()->create([
            'tenant_id' => 1,
            'source_system_id' => $source->id,
            'target_system_id' => $target->id,
            'entity_type' => 'student',
            'source_field' => 'student_code',
            'target_field' => 'code',
            'transform_rule' => ['type' => 'uppercase'],
            'is_required' => true,
            'status' => 'active',
        ]);

        $result = app(DataMappingService::class)->mapInboundPayload(1, $source->id, $target->id, 'student', ['student_code' => 'sv001']);

        $this->assertTrue($result['valid']);
        $this->assertSame('SV001', $result['mapped']['code']);
    }

    public function test_sync_job_can_be_dispatched_to_queue_and_run(): void
    {
        Queue::fake();
        $system = $this->system();
        $job = app(SyncJobService::class)->createFullSync(1, $system->id, 'student', 1, true);

        Queue::assertPushed(RunApiSyncJob::class);

        $finished = app(SyncJobService::class)->runSyncJob($job);
        $this->assertSame('success', $finished->status);
        $this->assertGreaterThan(0, $finished->success_count);
    }

    public function test_retry_failed_event_marks_success(): void
    {
        $event = ApiEvent::query()->create([
            'tenant_id' => 1,
            'event_key' => 'lms.grade.approved',
            'entity_type' => 'grade',
            'idempotency_key' => 'retry-event',
            'payload' => [],
            'status' => 'failed',
            'attempts' => 1,
        ]);

        app(EventBusService::class)->retryFailed(1);

        $this->assertSame('success', $event->fresh()->status);
    }

    public function test_viewer_can_view_logs_but_not_sensitive_payload(): void
    {
        $endpoint = $this->endpoint();
        ApiRequestLog::query()->create([
            'tenant_id' => 1,
            'system_id' => $endpoint->system_id,
            'endpoint_id' => $endpoint->id,
            'request_uuid' => '00000000-0000-0000-0000-000000000001',
            'direction' => 'outbound',
            'method' => 'POST',
            'url' => $endpoint->full_url,
            'status_code' => 200,
            'duration_ms' => 10,
            'request_body' => ['plain_secret' => 'visible-if-not-masked'],
            'response_body' => ['token' => 'visible-if-not-masked'],
            'created_at' => now(),
        ]);

        $viewer = LmsUser::query()->create([
            'tenant_id' => 1,
            'code' => 'API-VIEWER',
            'full_name' => 'API Viewer',
            'email' => 'api.viewer@example.test',
            'user_type' => 'staff',
            'status' => 'active',
            'metadata' => ['demo_password' => 'admin123456'],
        ]);
        $role = Role::query()->where('tenant_id', 1)->where('name', 'api_ops_viewer')->firstOrFail();
        DB::table('user_role_scope')->insert(['user_id' => $viewer->id, 'role_id' => $role->id, 'tenant_id' => 1]);

        $this->withHeaders(['X-Tenant-Code' => 'VABIS', 'X-Demo-User-Email' => $viewer->email])
            ->getJson('/api/v1/api-ops/requests')
            ->assertOk()
            ->assertJsonPath('data.data.0.request_body.masked', true)
            ->assertJsonPath('data.data.0.response_body.masked', true);
    }

    public function test_health_check_and_dashboard_load_summary_data(): void
    {
        $endpoint = $this->endpoint();
        app(ApiHealthService::class)->checkEndpoint($endpoint);

        $this->assertDatabaseHas('api_health_snapshots', ['endpoint_id' => $endpoint->id]);

        $this->withHeaders($this->headers)
            ->getJson('/api/v1/api-ops/dashboard')
            ->assertOk()
            ->assertJsonStructure(['data' => ['cards' => ['systems_total', 'healthy', 'requests_today'], 'charts']]);
    }

    private function system(array $overrides = []): ApiSystem
    {
        $code = $overrides['code'] ?? 'SIS-OPS';

        return ApiSystem::query()->updateOrCreate([
            'tenant_id' => 1,
            'code' => $code,
        ], array_merge([
            'name' => $code,
            'type' => 'sis',
            'base_url' => 'https://sis.example.test',
            'environment' => 'staging',
            'auth_type' => 'hmac',
            'status' => 'active',
            'settings' => ['webhook_secret' => 'secret'],
        ], $overrides));
    }

    private function endpoint(): ApiEndpoint
    {
        $system = $this->system();

        return ApiEndpoint::query()->updateOrCreate([
            'tenant_id' => 1,
            'system_id' => $system->id,
            'code' => 'SIS_STUDENTS',
        ], [
            'name' => 'SIS students',
            'method' => 'POST',
            'path' => '/students',
            'full_url' => 'https://sis.example.test/students',
            'module' => 'student',
            'request_schema' => ['required' => ['id'], 'sensitive_fields' => ['password', 'token']],
            'response_schema' => ['sensitive_fields' => ['token']],
            'timeout_ms' => 5000,
            'retry_policy' => ['max_attempts' => 3],
            'rate_limit' => ['per_minute' => 120],
            'status' => 'active',
        ]);
    }
}
