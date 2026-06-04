<?php

namespace Database\Factories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'actor_id' => 1,
            'action' => 'update',
            'module' => 'core.users',
            'entity_type' => 'lms_user',
            'entity_id' => 1,
            'before' => [],
            'after' => [],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Factory',
        ];
    }
}
