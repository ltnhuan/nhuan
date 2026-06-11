<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\IntegrationEvent;
use App\Models\IntegrationMapping;
use App\Models\IntegrationSystem;
use App\Models\SyncConflict;
use App\Models\SyncJob;
use App\Models\WebhookDelivery;
use App\Services\InboundWebhookService;
use App\Services\IntegrationConfigService;
use App\Services\MappingService;
use App\Services\OutboundWebhookService;
use App\Services\SyncJobService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IntegrationController extends Controller
{
    public function systems(Request $request, TenantContext $tenant) { return IntegrationSystem::query()->where('tenant_id',$tenant->id())->latest()->paginate($request->integer('per_page',25)); }
    public function storeSystem(Request $request, TenantContext $tenant, IntegrationConfigService $service) { return response()->json($service->createSystem($request->all() + ['tenant_id'=>$tenant->id()]), 201); }
    public function updateSystem(Request $request, IntegrationSystem $system, IntegrationConfigService $service) { return $service->updateSystem($system, $request->all()); }
    public function testConnection(IntegrationSystem $system, IntegrationConfigService $service) { return $service->validateConnection($system); }

    public function mappings(Request $request, TenantContext $tenant)
    {
        return IntegrationMapping::query()->where('tenant_id',$tenant->id())->when($request->filled('entity_type'),fn($q)=>$q->where('entity_type',$request->input('entity_type')))->when($request->filled('mapping_status'),fn($q)=>$q->where('mapping_status',$request->input('mapping_status')))->latest()->paginate($request->integer('per_page',50));
    }

    public function storeMapping(Request $request, MappingService $service)
    {
        $system = IntegrationSystem::query()->findOrFail($request->integer('system_id'));
        return response()->json($service->createMapping($system, $request->input('entity_type'), (string)$request->input('local_id'), (string)$request->input('external_id'), $request->input('external_code'), $request->input('metadata', [])), 201);
    }

    public function resolveConflict(Request $request, MappingService $service) { return $service->resolveConflict($request->integer('conflict_id'), $request->user()?->id ?? 1, $request->input('action','resolved')); }

    public function inbound(string $systemCode, Request $request, TenantContext $tenant, InboundWebhookService $service)
    {
        $system = IntegrationSystem::query()->where('tenant_id',$tenant->id())->where('code',$systemCode)->firstOrFail();
        return $service->receive($system, $request);
    }

    public function events(Request $request, TenantContext $tenant)
    {
        return IntegrationEvent::query()->where('tenant_id',$tenant->id())->when($request->filled('status'),fn($q)=>$q->where('status',$request->input('status')))->when($request->filled('direction'),fn($q)=>$q->where('direction',$request->input('direction')))->latest()->paginate($request->integer('per_page',50));
    }

    public function retryEvent(IntegrationEvent $event, InboundWebhookService $inbound, OutboundWebhookService $outbound)
    {
        if ($event->direction === 'inbound') return $inbound->process($event);
        $event->forceFill(['status'=>'pending','attempts'=>$event->attempts + 1])->save();
        $outbound->retryFailedDeliveries();
        return $event->fresh();
    }

    public function syncJobs(Request $request, TenantContext $tenant) { return SyncJob::query()->where('tenant_id',$tenant->id())->latest()->paginate($request->integer('per_page',50)); }
    public function syncUsers(Request $request, SyncJobService $service) { return $service->fullSyncUsers($this->system($request), $request->user()?->id ?? 1); }
    public function syncClasses(Request $request, SyncJobService $service) { return $service->fullSyncClasses($this->system($request), $request->user()?->id ?? 1); }
    public function syncEnrollments(Request $request, SyncJobService $service) { return $service->fullSyncEnrollments($this->system($request), $request->user()?->id ?? 1); }
    public function pushGrades(Request $request, SyncJobService $service) { return $service->pushGradesToSIS($this->system($request), $request->input('grades', []), $request->user()?->id ?? 1); }
    public function pushAttendance(Request $request, SyncJobService $service) { return $service->pushAttendanceToSIS($this->system($request), $request->input('attendance', []), $request->user()?->id ?? 1); }
    public function pushProgress(Request $request, SyncJobService $service) { return $service->pushProgressToSIS($this->system($request), $request->input('progress', []), $request->user()?->id ?? 1); }

    public function health(TenantContext $tenant)
    {
        return [
            'systems' => IntegrationSystem::query()->where('tenant_id',$tenant->id())->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total','status'),
            'events' => IntegrationEvent::query()->where('tenant_id',$tenant->id())->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total','status'),
            'deliveries' => WebhookDelivery::query()->where('tenant_id',$tenant->id())->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total','status'),
            'conflicts_open' => SyncConflict::query()->where('tenant_id',$tenant->id())->where('status','open')->count(),
            'queues' => ['sync-sis'=>'configured','webhook'=>'configured','integration-retry'=>'configured'],
        ];
    }

    private function system(Request $request): IntegrationSystem { return IntegrationSystem::query()->findOrFail($request->integer('system_id')); }
}
