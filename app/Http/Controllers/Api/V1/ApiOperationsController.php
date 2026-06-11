<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ApiAuditLog;
use App\Models\ApiDataContract;
use App\Models\ApiDataMapping;
use App\Models\ApiEndpoint;
use App\Models\ApiEntityMapping;
use App\Models\ApiEvent;
use App\Models\ApiRequestLog;
use App\Models\ApiSyncJob;
use App\Models\ApiSystem;
use App\Models\ApiWebhookDelivery;
use App\Models\ApiWebhookEndpoint;
use App\Models\LmsUser;
use App\Services\ApiOperations\ApiConsoleService;
use App\Services\ApiOperations\ApiGatewayService;
use App\Services\ApiOperations\ApiHealthService;
use App\Services\ApiOperations\ApiRegistryService;
use App\Services\ApiOperations\DataMappingService;
use App\Services\ApiOperations\EntityMappingService;
use App\Services\ApiOperations\EventBusService;
use App\Services\ApiOperations\SyncJobService as ApiSyncJobService;
use App\Services\ApiOperations\WebhookService;
use App\Services\Core\CorePermissionService;
use App\Services\TenantContext;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;

class ApiOperationsController extends Controller
{
    public function dashboard(TenantContext $tenant, ApiHealthService $health)
    {
        return ApiResponse::success($health->dashboard((int) $tenant->id()), 'API Operations dashboard loaded.');
    }

    public function options(Request $request, TenantContext $tenant)
    {
        $include = collect(explode(',', (string) $request->input('include', 'systems,endpoints')))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values();
        $data = [];

        if ($include->contains('systems')) {
            $data['systems'] = ApiSystem::query()
                ->where('tenant_id', $tenant->id())
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type', 'environment', 'status']);
        }

        if ($include->contains('endpoints')) {
            $data['endpoints'] = ApiEndpoint::query()
                ->where('tenant_id', $tenant->id())
                ->when($request->filled('system_id'), fn ($query) => $query->where('system_id', $request->integer('system_id')))
                ->orderBy('code')
                ->get(['id', 'system_id', 'code', 'name', 'method', 'path', 'status']);
        }

        return ApiResponse::success($data, 'API options loaded.');
    }

    public function systems(Request $request, TenantContext $tenant)
    {
        $systems = ApiSystem::query()
            ->where('tenant_id', $tenant->id())
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return ApiResponse::success($systems);
    }

    public function storeSystem(Request $request, TenantContext $tenant, ApiRegistryService $registry)
    {
        $system = $registry->registerSystem($this->tenantPayload($request, $tenant), $this->currentUser($request)?->id);

        return ApiResponse::success($system, 'API system registered.', [], 201);
    }

    public function updateSystem(Request $request, TenantContext $tenant, ApiSystem $system)
    {
        $this->assertTenantModel($system, $tenant);
        $before = $system->toArray();
        $data = Arr::except($request->all(), ['tenant_id', 'credentials', 'encrypted_value']);
        $system->fill($data)->save();
        $this->audit($system->tenant_id, $this->currentUser($request)?->id, 'api_system.updated', 'registry', 'api_system', (string) $system->id, $before, $system->fresh()->toArray(), $request->ip());

        return ApiResponse::success($system->fresh(), 'API system updated.');
    }

    public function testConnection(TenantContext $tenant, ApiSystem $system, ApiRegistryService $registry)
    {
        $this->assertTenantModel($system, $tenant);

        return ApiResponse::success($registry->testConnection($system), 'Connection checked.');
    }

    public function endpoints(Request $request, TenantContext $tenant)
    {
        $endpoints = ApiEndpoint::query()
            ->with('system:id,code,name,type,environment,status')
            ->where('tenant_id', $tenant->id())
            ->when($request->filled('system_id'), fn ($query) => $query->where('system_id', $request->integer('system_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('method'), fn ($query) => $query->where('method', strtoupper((string) $request->input('method'))))
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return ApiResponse::success($endpoints);
    }

    public function storeEndpoint(Request $request, TenantContext $tenant, ApiRegistryService $registry)
    {
        $this->assertTenantSystem($request->integer('system_id'), $tenant);
        $endpoint = $registry->registerEndpoint($this->tenantPayload($request, $tenant), $this->currentUser($request)?->id);

        return ApiResponse::success($endpoint, 'API endpoint registered.', [], 201);
    }

    public function updateEndpoint(Request $request, TenantContext $tenant, ApiEndpoint $endpoint)
    {
        $this->assertTenantModel($endpoint, $tenant);
        if ($request->filled('system_id')) {
            $this->assertTenantSystem($request->integer('system_id'), $tenant);
        }

        $before = $endpoint->toArray();
        $endpoint->fill(Arr::except($request->all(), ['tenant_id']))->save();
        $this->audit($endpoint->tenant_id, $this->currentUser($request)?->id, 'api_endpoint.updated', 'registry', 'api_endpoint', (string) $endpoint->id, $before, $endpoint->fresh()->toArray(), $request->ip());

        return ApiResponse::success($endpoint->fresh(), 'API endpoint updated.');
    }

    public function testEndpoint(Request $request, TenantContext $tenant, ApiEndpoint $endpoint, ApiConsoleService $console)
    {
        $this->assertTenantModel($endpoint, $tenant);

        $result = $console->testEndpoint(
            $endpoint,
            $request->input('body', []),
            $request->input('headers', []),
            $this->currentUser($request)?->id,
            $this->can($request, 'api_ops.secret.manage')
        );

        return ApiResponse::success($result, 'Endpoint tested.');
    }

    public function requests(Request $request, TenantContext $tenant)
    {
        $canViewPayload = $this->can($request, 'api_ops.payload.view');
        $logs = ApiRequestLog::query()
            ->with('endpoint:id,code,name')
            ->where('tenant_id', $tenant->id())
            ->when($request->filled('system_id'), fn ($query) => $query->where('system_id', $request->integer('system_id')))
            ->when($request->filled('endpoint_id'), fn ($query) => $query->where('endpoint_id', $request->integer('endpoint_id')))
            ->when($request->filled('status_code'), fn ($query) => $query->where('status_code', $request->integer('status_code')))
            ->when($request->filled('error'), fn ($query) => $query->whereNotNull('error_message'))
            ->when($request->filled('duration_min'), fn ($query) => $query->where('duration_ms', '>=', $request->integer('duration_min')))
            ->latest('created_at')
            ->paginate($request->integer('per_page', 50))
            ->through(fn (ApiRequestLog $log) => $this->serializeRequestLog($log, $canViewPayload));

        return ApiResponse::success($logs);
    }

    public function events(Request $request, TenantContext $tenant)
    {
        $events = ApiEvent::query()
            ->where('tenant_id', $tenant->id())
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('event_key'), fn ($query) => $query->where('event_key', $request->input('event_key')))
            ->latest()
            ->paginate($request->integer('per_page', 50));

        return ApiResponse::success($events);
    }

    public function retryEvent(TenantContext $tenant, ApiEvent $event, EventBusService $events)
    {
        $this->assertTenantModel($event, $tenant);

        return ApiResponse::success($events->consumeEvent($event), 'Event retried.');
    }

    public function ignoreEvent(Request $request, TenantContext $tenant, ApiEvent $event)
    {
        $this->assertTenantModel($event, $tenant);
        $event->forceFill(['status' => 'ignored', 'processed_at' => now()])->save();
        $this->audit($event->tenant_id, $this->currentUser($request)?->id, 'api_event.ignored', 'events', 'api_event', (string) $event->id, null, $event->fresh()->toArray(), $request->ip());

        return ApiResponse::success($event->fresh(), 'Event ignored.');
    }

    public function inboundWebhook(string $systemCode, Request $request, TenantContext $tenant, ApiGatewayService $gateway, EventBusService $events)
    {
        $system = ApiSystem::query()->where('tenant_id', $tenant->id())->where('code', $systemCode)->firstOrFail();
        $secret = (string) ($system->settings['webhook_secret'] ?? '');
        $body = $request->getContent();

        if ($secret !== '' && ! $gateway->verifySignature($body, $request->header('X-Api-Ops-Signature'), $secret)) {
            return ApiResponse::error('Invalid webhook signature.', [], 'INVALID_SIGNATURE', 401);
        }

        $payload = $request->json()->all() ?: $request->all();
        $gateway->receiveRequest($system, $request);
        $event = $events->publishEvent($system->tenant_id, (string) ($payload['event_key'] ?? 'webhook.received'), (string) ($payload['entity_type'] ?? 'unknown'), $payload, [
            'source_system_id' => $system->id,
            'entity_id' => $payload['entity_id'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? $events->nextIdempotencyKey($system->code),
        ]);

        return ApiResponse::success($event, 'Webhook accepted.', [], 202);
    }

    public function webhooks(Request $request, TenantContext $tenant)
    {
        $webhooks = ApiWebhookEndpoint::query()
            ->where('tenant_id', $tenant->id())
            ->when($request->filled('system_id'), fn ($query) => $query->where('system_id', $request->integer('system_id')))
            ->latest()
            ->paginate($request->integer('per_page', 25))
            ->through(fn (ApiWebhookEndpoint $endpoint) => array_merge($endpoint->toArray(), ['secret' => '***MASKED***']));

        return ApiResponse::success($webhooks);
    }

    public function storeWebhook(Request $request, TenantContext $tenant, WebhookService $webhooks)
    {
        $this->assertTenantSystem($request->integer('system_id'), $tenant);
        $endpoint = $webhooks->createWebhookEndpoint($this->tenantPayload($request, $tenant));

        return ApiResponse::success(array_merge($endpoint->toArray(), ['secret' => '***MASKED***']), 'Webhook endpoint created.', [], 201);
    }

    public function updateWebhook(Request $request, TenantContext $tenant, ApiWebhookEndpoint $webhook)
    {
        $this->assertTenantModel($webhook, $tenant);
        if ($request->filled('system_id')) {
            $this->assertTenantSystem($request->integer('system_id'), $tenant);
        }

        $data = Arr::except($request->all(), ['tenant_id']);
        if (($data['secret'] ?? null) === '***MASKED***') {
            unset($data['secret']);
        } elseif (array_key_exists('secret', $data)) {
            if (blank($data['secret'])) {
                unset($data['secret']);
            } else {
                $data['secret'] = Crypt::encryptString((string) $data['secret']);
            }
        }

        $webhook->fill($data)->save();

        return ApiResponse::success(array_merge($webhook->fresh()->toArray(), ['secret' => '***MASKED***']), 'Webhook endpoint updated.');
    }

    public function testWebhook(TenantContext $tenant, ApiWebhookEndpoint $webhook, WebhookService $webhooks, EventBusService $events)
    {
        $this->assertTenantModel($webhook, $tenant);
        $event = $events->publishEvent($webhook->tenant_id, 'api_ops.webhook.test', 'webhook', ['test' => true], [
            'target_system_id' => $webhook->system_id,
            'idempotency_key' => 'webhook-test-'.$webhook->id.'-'.now()->timestamp,
        ]);

        return ApiResponse::success($webhooks->deliverWebhook($webhook, $event), 'Webhook tested.');
    }

    public function retryFailedWebhooks(TenantContext $tenant, ApiWebhookEndpoint $webhook, WebhookService $webhooks)
    {
        $this->assertTenantModel($webhook, $tenant);

        return ApiResponse::success(['retried' => $webhooks->retryFailedWebhook($webhook->id)], 'Failed webhook deliveries retried.');
    }

    public function webhookDeliveries(Request $request, TenantContext $tenant)
    {
        $deliveries = ApiWebhookDelivery::query()
            ->where('tenant_id', $tenant->id())
            ->when($request->filled('webhook_endpoint_id'), fn ($query) => $query->where('webhook_endpoint_id', $request->integer('webhook_endpoint_id')))
            ->latest()
            ->paginate($request->integer('per_page', 50));

        return ApiResponse::success($deliveries);
    }

    public function mappings(Request $request, TenantContext $tenant)
    {
        $mappings = ApiDataMapping::query()
            ->where('tenant_id', $tenant->id())
            ->when($request->filled('entity_type'), fn ($query) => $query->where('entity_type', $request->input('entity_type')))
            ->latest()
            ->paginate($request->integer('per_page', 50));

        return ApiResponse::success($mappings);
    }

    public function storeMapping(Request $request, TenantContext $tenant)
    {
        $this->assertTenantSystems([$request->integer('source_system_id'), $request->integer('target_system_id')], $tenant);
        $mapping = ApiDataMapping::query()->create($this->tenantPayload($request, $tenant) + ['status' => 'active']);

        return ApiResponse::success($mapping, 'Data mapping created.', [], 201);
    }

    public function updateMapping(Request $request, TenantContext $tenant, ApiDataMapping $mapping)
    {
        $this->assertTenantModel($mapping, $tenant);
        $systemIds = [];
        if ($request->has('source_system_id')) {
            $systemIds[] = $request->integer('source_system_id');
        }
        if ($request->has('target_system_id')) {
            $systemIds[] = $request->integer('target_system_id');
        }
        $this->assertTenantSystems($systemIds, $tenant);
        $mapping->fill(Arr::except($request->all(), ['tenant_id']))->save();

        return ApiResponse::success($mapping->fresh(), 'Data mapping updated.');
    }

    public function validateMapping(Request $request, TenantContext $tenant, DataMappingService $mappings)
    {
        $this->assertTenantSystems([$request->integer('source_system_id'), $request->integer('target_system_id')], $tenant);

        $result = $mappings->mapPayload(
            (int) $tenant->id(),
            $request->integer('source_system_id'),
            $request->integer('target_system_id'),
            (string) $request->input('entity_type'),
            $request->input('payload', [])
        );

        return ApiResponse::success($result, 'Mapping validated.');
    }

    public function entityMappings(Request $request, TenantContext $tenant)
    {
        $mappings = ApiEntityMapping::query()
            ->where('tenant_id', $tenant->id())
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('entity_type'), fn ($query) => $query->where('entity_type', $request->input('entity_type')))
            ->latest()
            ->paginate($request->integer('per_page', 50));

        return ApiResponse::success($mappings);
    }

    public function resolveEntityMappingConflict(Request $request, TenantContext $tenant, EntityMappingService $mappings)
    {
        return ApiResponse::success($mappings->resolveConflict($request->integer('mapping_id'), $request->input('action', 'activate'), (int) $tenant->id()), 'Entity mapping conflict resolved.');
    }

    public function syncJobs(Request $request, TenantContext $tenant)
    {
        $jobs = ApiSyncJob::query()
            ->withCount('items')
            ->where('tenant_id', $tenant->id())
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest()
            ->paginate($request->integer('per_page', 50));

        return ApiResponse::success($jobs);
    }

    public function storeSyncJob(Request $request, TenantContext $tenant, ApiSyncJobService $syncJobs)
    {
        $this->assertTenantSystem($request->integer('system_id'), $tenant);
        $job = $syncJobs->createJob($tenant->id(), $request->integer('system_id'), $request->input('job_type', 'full_sync'), $request->input('entity_type', 'student'), $this->currentUser($request)?->id);

        return ApiResponse::success($job, 'Sync job created.', [], 201);
    }

    public function runSyncJob(TenantContext $tenant, ApiSyncJob $job, ApiSyncJobService $syncJobs)
    {
        $this->assertTenantModel($job, $tenant);

        return ApiResponse::success($syncJobs->runSyncJob($job), 'Sync job completed.');
    }

    public function retrySyncJob(TenantContext $tenant, ApiSyncJob $job, ApiSyncJobService $syncJobs)
    {
        $this->assertTenantModel($job, $tenant);

        return ApiResponse::success($syncJobs->retryFailedItems($job), 'Failed sync items retried.');
    }

    public function cancelSyncJob(TenantContext $tenant, ApiSyncJob $job, ApiSyncJobService $syncJobs)
    {
        $this->assertTenantModel($job, $tenant);

        return ApiResponse::success($syncJobs->cancelJob($job), 'Sync job cancelled.');
    }

    public function health(TenantContext $tenant, ApiHealthService $health)
    {
        return ApiResponse::success($health->dashboard((int) $tenant->id()), 'API health loaded.');
    }

    public function checkHealthNow(TenantContext $tenant, ApiHealthService $health)
    {
        return ApiResponse::success($health->buildSnapshots((int) $tenant->id()), 'API health snapshots created.');
    }

    public function contracts(Request $request, TenantContext $tenant)
    {
        $contracts = ApiDataContract::query()
            ->where('tenant_id', $tenant->id())
            ->when($request->filled('entity_type'), fn ($query) => $query->where('entity_type', $request->input('entity_type')))
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return ApiResponse::success($contracts);
    }

    public function storeContract(Request $request, TenantContext $tenant)
    {
        $this->assertTenantSystem($request->integer('system_id'), $tenant);
        $contract = ApiDataContract::query()->create(array_merge($this->tenantPayload($request, $tenant, ['created_by', 'status', 'effective_from']), [
            'status' => 'draft',
            'created_by' => $this->currentUser($request)?->id,
        ]));

        return ApiResponse::success($contract, 'Data contract created.', [], 201);
    }

    public function updateContract(Request $request, TenantContext $tenant, ApiDataContract $contract)
    {
        $this->assertTenantModel($contract, $tenant);
        if ($request->filled('system_id')) {
            $this->assertTenantSystem($request->integer('system_id'), $tenant);
        }

        $contract->fill(Arr::except($request->all(), ['tenant_id', 'created_by']))->save();

        return ApiResponse::success($contract->fresh(), 'Data contract updated.');
    }

    public function activateContract(TenantContext $tenant, ApiDataContract $contract)
    {
        $this->assertTenantModel($contract, $tenant);
        ApiDataContract::query()
            ->where('tenant_id', $contract->tenant_id)
            ->where('system_id', $contract->system_id)
            ->where('entity_type', $contract->entity_type)
            ->where('id', '!=', $contract->id)
            ->where('status', 'active')
            ->update(['status' => 'deprecated']);

        $contract->forceFill(['status' => 'active', 'effective_from' => now()])->save();

        return ApiResponse::success($contract->fresh(), 'Data contract activated.');
    }

    public function consoleTestRequest(Request $request, TenantContext $tenant, ApiConsoleService $console)
    {
        $endpoint = ApiEndpoint::query()
            ->where('tenant_id', $tenant->id())
            ->findOrFail($request->integer('endpoint_id'));

        return ApiResponse::success($console->testEndpoint(
            $endpoint,
            $request->input('body', []),
            $request->input('headers', []),
            $this->currentUser($request)?->id,
            $this->can($request, 'api_ops.secret.manage')
        ), 'Console request completed.');
    }

    public function auditLogs(Request $request, TenantContext $tenant)
    {
        return ApiResponse::success(ApiAuditLog::query()->where('tenant_id', $tenant->id())->latest('created_at')->paginate($request->integer('per_page', 50)));
    }

    private function tenantPayload(Request $request, TenantContext $tenant, array $except = []): array
    {
        return array_merge(
            Arr::except($request->all(), array_merge(['tenant_id'], $except)),
            ['tenant_id' => (int) $tenant->id()]
        );
    }

    private function assertTenantModel(Model $model, TenantContext $tenant): void
    {
        abort_unless((int) $model->getAttribute('tenant_id') === (int) $tenant->id(), 404);
    }

    private function assertTenantSystem(?int $systemId, TenantContext $tenant): void
    {
        $this->assertTenantSystems([$systemId], $tenant);
    }

    private function assertTenantSystems(array $systemIds, TenantContext $tenant): void
    {
        if ($systemIds === []) {
            return;
        }

        $ids = collect($systemIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        abort_unless($ids->count() === count($systemIds), 404);

        $matched = ApiSystem::query()
            ->where('tenant_id', $tenant->id())
            ->whereIn('id', $ids)
            ->count();

        abort_unless($matched === $ids->count(), 404);
    }

    private function serializeRequestLog(ApiRequestLog $log, bool $canViewPayload): array
    {
        $payload = $log->toArray();
        if (! $canViewPayload) {
            $payload['request_body'] = ['masked' => true];
            $payload['response_body'] = ['masked' => true];
            $payload['request_headers'] = ['masked' => true];
            $payload['response_headers'] = ['masked' => true];
        }

        return $payload;
    }

    private function currentUser(Request $request): ?LmsUser
    {
        $tenant = $request->attributes->get('tenant');
        $email = $request->header('X-Demo-User-Email');
        if (! $tenant || ! $email) {
            return null;
        }

        return LmsUser::query()->where('tenant_id', $tenant->id)->where('email', $email)->first();
    }

    private function can(Request $request, string $permission): bool
    {
        $tenant = $request->attributes->get('tenant');
        $user = $this->currentUser($request);
        if (! $tenant || ! $user) {
            return false;
        }

        return app(CorePermissionService::class)->can($user, $permission, ['tenant_id' => $tenant->id]);
    }

    private function audit(int $tenantId, ?int $actorId, string $action, string $module, string $entityType, string $entityId, ?array $before, ?array $after, ?string $ip): void
    {
        ApiAuditLog::query()->create([
            'tenant_id' => $tenantId,
            'actor_id' => $actorId,
            'action' => $action,
            'module' => $module,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before' => $before,
            'after' => $after,
            'ip_address' => $ip,
            'created_at' => now(),
        ]);
    }
}
