<?php

namespace App\Services;

use App\Jobs\WriteAuditLog;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogService
{
    public function record(string $action, string $module, Model|string $entity, array $before = [], array $after = [], ?object $actor = null, ?Request $request = null): ?AuditLog
    {
        $payload = [
            'tenant_id' => app(TenantContext::class)->id(),
            'actor_id' => method_exists($actor, 'getAuthIdentifier') ? $actor->getAuthIdentifier() : ($actor->id ?? null),
            'action' => $action,
            'module' => $module,
            'entity_type' => is_string($entity) ? $entity : $entity::class,
            'entity_id' => is_string($entity) ? null : $entity->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ];

        if (config('eralms.audit.queue', false)) {
            WriteAuditLog::dispatch($payload)->afterResponse();
            return null;
        }

        return AuditLog::query()->create($payload);
    }
}
