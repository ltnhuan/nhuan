<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogService
{
    public function record(string $action, string $module, Model|string $entity, array $before = [], array $after = [], ?Authenticatable $actor = null, ?Request $request = null): AuditLog
    {
        return AuditLog::query()->create([
            'tenant_id' => app(TenantContext::class)->id(),
            'actor_id' => $actor?->getAuthIdentifier(),
            'action' => $action,
            'module' => $module,
            'entity_type' => is_string($entity) ? $entity : $entity::class,
            'entity_id' => is_string($entity) ? null : $entity->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
