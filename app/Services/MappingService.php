<?php

namespace App\Services;

use App\Models\IntegrationMapping;
use App\Models\IntegrationSystem;
use App\Models\SyncConflict;

class MappingService
{
    public function findLocalByExternal(IntegrationSystem $system, string $entityType, string $externalId): ?string
    {
        return IntegrationMapping::query()->where(['tenant_id'=>$system->tenant_id,'system_id'=>$system->id,'entity_type'=>$entityType,'external_id'=>$externalId,'mapping_status'=>'active'])->value('local_id');
    }

    public function findExternalByLocal(IntegrationSystem $system, string $entityType, string $localId): ?string
    {
        return IntegrationMapping::query()->where(['tenant_id'=>$system->tenant_id,'system_id'=>$system->id,'entity_type'=>$entityType,'local_id'=>$localId,'mapping_status'=>'active'])->value('external_id');
    }

    public function createMapping(IntegrationSystem $system, string $entityType, string $localId, string $externalId, ?string $externalCode = null, array $metadata = []): IntegrationMapping
    {
        $conflict = $this->detectConflict($system, $entityType, $localId, $externalId);
        if ($conflict) return IntegrationMapping::query()->where('tenant_id',$system->tenant_id)->where('system_id',$system->id)->where('entity_type',$entityType)->where(function($q) use($localId,$externalId){$q->where('local_id',$localId)->orWhere('external_id',$externalId);})->first();
        return IntegrationMapping::query()->updateOrCreate(['tenant_id'=>$system->tenant_id,'system_id'=>$system->id,'entity_type'=>$entityType,'local_id'=>$localId], ['external_id'=>$externalId,'external_code'=>$externalCode,'mapping_status'=>'active','metadata'=>$metadata]);
    }

    public function detectConflict(IntegrationSystem $system, string $entityType, string $localId, string $externalId): ?SyncConflict
    {
        $duplicate = IntegrationMapping::query()->where('tenant_id',$system->tenant_id)->where('system_id',$system->id)->where('entity_type',$entityType)->where(function($q) use($localId,$externalId){$q->where('local_id',$localId)->orWhere('external_id',$externalId);})->where(function($q) use($localId,$externalId){$q->where('local_id','!=',$localId)->orWhere('external_id','!=',$externalId);})->first();
        if (! $duplicate) return null;
        return SyncConflict::query()->create(['tenant_id'=>$system->tenant_id,'system_id'=>$system->id,'entity_type'=>$entityType,'local_id'=>$localId,'external_id'=>$externalId,'conflict_type'=>'duplicate','local_snapshot'=>['local_id'=>$localId],'external_snapshot'=>['external_id'=>$externalId],'status'=>'open']);
    }

    public function resolveConflict(int $conflictId, int $actorId, string $action = 'resolved'): SyncConflict
    {
        $conflict = SyncConflict::query()->findOrFail($conflictId);
        $conflict->forceFill(['status' => $action, 'resolved_by' => $actorId, 'resolved_at' => now()])->save();
        IntegrationMapping::query()->where('system_id',$conflict->system_id)->where('entity_type',$conflict->entity_type)->where('local_id',$conflict->local_id)->where('external_id',$conflict->external_id)->update(['mapping_status' => $action === 'resolved' ? 'active' : 'inactive']);
        return $conflict->fresh();
    }
}
