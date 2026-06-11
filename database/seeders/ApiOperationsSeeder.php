<?php

namespace Database\Seeders;

use App\Models\ApiDataContract;
use App\Models\ApiDataMapping;
use App\Models\ApiEndpoint;
use App\Models\ApiEntityMapping;
use App\Models\ApiErrorRule;
use App\Models\ApiEvent;
use App\Models\ApiHealthSnapshot;
use App\Models\ApiRequestLog;
use App\Models\ApiSyncJob;
use App\Models\ApiSyncJobItem;
use App\Models\ApiSystem;
use App\Models\ApiWebhookEndpoint;
use App\Models\LmsUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApiOperationsSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('code', 'VABIS')->first() ?? Tenant::query()->first();
        if (! $tenant) {
            return;
        }

        $this->ensurePermissionsAndRoles($tenant);

        $adminId = LmsUser::query()->where('tenant_id', $tenant->id)->where('email', 'admin.lms@vabis.edu.vn')->value('id');

        $systems = collect([
            ['SIS', 'SIS System', 'sis', 'https://sis.example.test', 'staging', 'hmac', 'active', 'Integration', 'Student information system'],
            ['LMS', 'LMS Internal System', 'lms', 'https://lms.internal.test', 'local', 'jwt', 'active', 'Platform', 'Internal LMS APIs'],
            ['EXAM', 'Exam Portal', 'exam', 'https://exam.example.test', 'staging', 'api_key', 'active', 'Assessment', 'Online exam portal'],
            ['MOBILE', 'Mobile App', 'mobile', 'https://mobile.example.test', 'production', 'jwt', 'active', 'Mobile', 'Learner mobile app backend'],
            ['AI', 'AI Service', 'ai', 'https://ai.example.test', 'staging', 'api_key', 'active', 'AI', 'AI tutor and content services'],
            ['FINANCE', 'Finance System', 'finance', 'https://finance.example.test', 'staging', 'oauth2', 'active', 'Finance', 'Payments and invoices'],
            ['CRM', 'CRM System', 'crm', 'https://crm.example.test', 'dev', 'oauth2', 'maintenance', 'Admissions', 'Lead and enrollment CRM'],
        ])->mapWithKeys(function (array $row) use ($tenant) {
            [$code, $name, $type, $baseUrl, $environment, $authType, $status, $team, $description] = $row;

            $system = ApiSystem::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'code' => $code,
            ], [
                'name' => $name,
                'type' => $type,
                'base_url' => $baseUrl,
                'environment' => $environment,
                'auth_type' => $authType,
                'status' => $status,
                'owner_team' => $team,
                'description' => $description,
                'settings' => ['mock' => true, 'webhook_secret' => 'secret-'.$code, 'sensitive_fields' => ['password', 'token', 'api_key']],
            ]);

            return [$code => $system];
        });

        $endpointRows = [
            ['SIS_STUDENTS', 'SIS students', 'SIS', 'GET', '/students', 'student', 'Sync student master data'],
            ['SIS_TEACHERS', 'SIS teachers', 'SIS', 'GET', '/teachers', 'teacher', 'Sync teacher master data'],
            ['SIS_CLASSES', 'SIS classes', 'SIS', 'GET', '/classes', 'class', 'Sync class sections'],
            ['SIS_ENROLLMENTS', 'SIS enrollments', 'SIS', 'GET', '/enrollments', 'enrollment', 'Sync enrollments'],
            ['SIS_GRADES', 'SIS grades', 'SIS', 'POST', '/grades', 'gradebook', 'Push approved grades'],
            ['SIS_ATTENDANCE', 'SIS attendance', 'SIS', 'POST', '/attendance', 'attendance', 'Push attendance'],
            ['LMS_PROGRESS', 'LMS progress', 'LMS', 'GET', '/progress', 'learning_path', 'Read learner progress'],
            ['LMS_CERTIFICATES', 'LMS certificates', 'LMS', 'POST', '/certificates', 'credentials', 'Issue certificates'],
            ['EXAM_ATTEMPTS', 'Exam attempts', 'EXAM', 'GET', '/attempts', 'exam', 'Fetch submitted attempts'],
            ['AI_TUTOR', 'AI tutor', 'AI', 'POST', '/tutor/chat', 'ai', 'AI tutoring request'],
        ];

        $endpoints = collect($endpointRows)->mapWithKeys(function (array $row) use ($tenant, $systems) {
            [$code, $name, $systemCode, $method, $path, $module, $purpose] = $row;
            $system = $systems[$systemCode];

            $endpoint = ApiEndpoint::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'system_id' => $system->id,
                'code' => $code,
            ], [
                'name' => $name,
                'method' => $method,
                'path' => $path,
                'full_url' => rtrim($system->base_url, '/').$path,
                'module' => $module,
                'purpose' => $purpose,
                'request_schema' => ['required' => $method === 'GET' ? [] : ['id'], 'sensitive_fields' => ['token', 'password', 'api_key']],
                'response_schema' => ['type' => 'object'],
                'timeout_ms' => 5000,
                'retry_policy' => ['max_attempts' => 3, 'backoff_seconds' => 60],
                'rate_limit' => ['per_minute' => 120],
                'permission_key' => 'api_ops.console.use',
                'status' => 'active',
            ]);

            return [$code => $endpoint];
        });

        foreach ($systems as $system) {
            ApiWebhookEndpoint::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'system_id' => $system->id,
                'name' => $system->code.' default webhook',
            ], [
                'url' => 'https://hooks.example.test/'.Str::lower($system->code),
                'secret' => Crypt::encryptString('whsec-'.$system->code),
                'subscribed_events' => ['sis.student.created', 'lms.grade.approved', 'certificate.issued'],
                'status' => 'active',
                'settings' => ['mock' => true],
            ]);
        }

        $eventKeys = ['sis.student.created', 'sis.enrollment.created', 'lms.grade.approved', 'lms.attendance.updated', 'exam.attempt.submitted', 'certificate.issued'];
        if (ApiEvent::query()->where('tenant_id', $tenant->id)->count() < 300) {
            foreach (range(1, 300) as $i) {
                ApiEvent::query()->updateOrCreate([
                    'tenant_id' => $tenant->id,
                    'event_key' => $eventKeys[$i % count($eventKeys)],
                    'idempotency_key' => 'seed-event-'.$i,
                ], [
                    'source_system_id' => $systems->values()[$i % $systems->count()]->id,
                    'target_system_id' => $systems->values()[($i + 1) % $systems->count()]->id,
                    'entity_type' => ['student', 'enrollment', 'grade', 'attendance', 'attempt', 'certificate'][$i % 6],
                    'entity_id' => (string) $i,
                    'payload' => ['id' => $i, 'code' => 'EVT-'.$i],
                    'status' => $i <= 50 ? 'failed' : (['pending', 'processing', 'success', 'retrying'][$i % 4]),
                    'attempts' => $i <= 50 ? 3 : ($i % 3),
                    'error_message' => $i <= 50 ? 'Seeded integration failure.' : null,
                    'next_retry_at' => $i <= 50 ? now()->subMinutes($i) : null,
                    'processed_at' => $i % 4 === 2 ? now()->subMinutes($i) : null,
                ]);
            }
        }

        if (ApiRequestLog::query()->where('tenant_id', $tenant->id)->count() < 1000) {
            $endpointList = $endpoints->values();
            $rows = [];
            foreach (range(1, 1000) as $i) {
                $endpoint = $endpointList[$i % $endpointList->count()];
                $rows[] = [
                    'tenant_id' => $tenant->id,
                    'system_id' => $endpoint->system_id,
                    'endpoint_id' => $endpoint->id,
                    'request_uuid' => (string) Str::uuid(),
                    'direction' => $i % 3 === 0 ? 'inbound' : 'outbound',
                    'method' => $endpoint->method,
                    'url' => $endpoint->full_url,
                    'status_code' => $i % 17 === 0 ? 500 : ($i % 11 === 0 ? 422 : 200),
                    'duration_ms' => 20 + ($i * 13) % 900,
                    'request_headers' => json_encode(['authorization' => '***MASKED***', 'x-request-id' => 'seed-'.$i]),
                    'request_body' => json_encode(['id' => $i, 'token' => '***MASKED***']),
                    'response_headers' => json_encode(['content-type' => 'application/json']),
                    'response_body' => json_encode(['ok' => $i % 17 !== 0]),
                    'error_message' => $i % 17 === 0 ? 'Seeded upstream error.' : null,
                    'actor_id' => $adminId,
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'ApiOperationsSeeder',
                    'created_at' => now()->subMinutes($i),
                ];

                if (count($rows) === 200) {
                    ApiRequestLog::query()->insert($rows);
                    $rows = [];
                }
            }
            if ($rows) {
                ApiRequestLog::query()->insert($rows);
            }
        }

        foreach (['student', 'teacher', 'class', 'course', 'enrollment', 'grade', 'attendance', 'payment', 'certificate'] as $entityType) {
            ApiDataContract::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'system_id' => $systems['SIS']->id,
                'entity_type' => $entityType,
                'version' => 'v1',
            ], [
                'schema' => ['required' => ['id', 'code'], 'properties' => ['id' => ['type' => 'string'], 'code' => ['type' => 'string']]],
                'status' => 'active',
                'effective_from' => now()->subDays(30),
                'created_by' => $adminId,
            ]);
        }

        foreach ([['student_code', 'code'], ['full_name', 'name'], ['email', 'email'], ['class_code', 'class.code'], ['grade_value', 'score']] as [$source, $target]) {
            ApiDataMapping::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'source_system_id' => $systems['SIS']->id,
                'target_system_id' => $systems['LMS']->id,
                'entity_type' => str_contains($source, 'grade') ? 'grade' : 'student',
                'source_field' => $source,
                'target_field' => $target,
            ], [
                'transform_rule' => ['type' => str_contains($source, 'email') ? 'lowercase' : 'copy'],
                'is_required' => in_array($source, ['student_code', 'full_name'], true),
                'default_value' => null,
                'status' => 'active',
            ]);
        }

        foreach (range(1, 10) as $i) {
            ApiEntityMapping::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'system_id' => $systems['SIS']->id,
                'entity_type' => 'student',
                'local_id' => 'LOCAL-CONFLICT-'.$i,
            ], [
                'external_id' => 'SIS-CONFLICT-'.ceil($i / 2),
                'external_code' => 'SC'.ceil($i / 2),
                'status' => 'conflict',
                'metadata' => ['seed_conflict' => true],
            ]);
        }

        if (ApiSyncJob::query()->where('tenant_id', $tenant->id)->count() < 20) {
            foreach (range(1, 20) as $i) {
                $job = ApiSyncJob::query()->create([
                    'tenant_id' => $tenant->id,
                    'system_id' => $systems->values()[$i % $systems->count()]->id,
                    'job_type' => ['full_sync', 'incremental_sync', 'manual_retry', 'push', 'pull'][$i % 5],
                    'entity_type' => ['student', 'teacher', 'enrollment', 'grade', 'attendance'][$i % 5],
                    'status' => $i % 6 === 0 ? 'failed' : 'success',
                    'total_count' => 25,
                    'success_count' => $i % 6 === 0 ? 20 : 25,
                    'failed_count' => $i % 6 === 0 ? 5 : 0,
                    'started_at' => now()->subHours($i),
                    'finished_at' => now()->subHours($i)->addMinutes(5),
                    'error_report' => $i % 6 === 0 ? [['error' => 'Seeded sync failure.']] : [],
                    'created_by' => $adminId,
                ]);
                foreach (range(1, 5) as $item) {
                    ApiSyncJobItem::query()->create([
                        'tenant_id' => $tenant->id,
                        'sync_job_id' => $job->id,
                        'entity_type' => $job->entity_type,
                        'entity_id' => (string) $item,
                        'external_id' => strtoupper($job->entity_type).'-'.$item,
                        'status' => $job->status === 'failed' && $item === 1 ? 'failed' : 'success',
                        'error_message' => $job->status === 'failed' && $item === 1 ? 'Seeded item failure.' : null,
                        'payload' => ['seed' => true, 'item' => $item],
                    ]);
                }
            }
        }

        foreach ($endpoints->values()->take(5) as $index => $endpoint) {
            ApiHealthSnapshot::query()->create([
                'tenant_id' => $tenant->id,
                'system_id' => $endpoint->system_id,
                'endpoint_id' => $endpoint->id,
                'status' => 'degraded',
                'latency_ms' => 650 + ($index * 50),
                'success_rate' => 86 - $index,
                'error_rate' => 14 + $index,
                'checked_at' => now()->subMinutes($index),
                'metadata' => ['seeded_degraded_endpoint' => true],
            ]);
        }

        foreach ([['TIMEOUT', 'timeout|timed out', 'high', true, 3, 'Check upstream latency and retry window.'], ['AUTH_FAILED', '401|403|signature', 'critical', false, 0, 'Rotate credentials and verify HMAC configuration.']] as [$code, $pattern, $severity, $retry, $maxRetry, $action]) {
            ApiErrorRule::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'error_code' => $code,
            ], [
                'pattern' => $pattern,
                'severity' => $severity,
                'auto_retry' => $retry,
                'max_retry' => $maxRetry,
                'notify_roles' => ['integration_admin', 'tenant_admin'],
                'recommended_action' => $action,
            ]);
        }
    }

    private function ensurePermissionsAndRoles(Tenant $tenant): void
    {
        $permissions = [
            'api_ops.view',
            'api_ops.system.manage',
            'api_ops.endpoint.manage',
            'api_ops.request.view',
            'api_ops.payload.view',
            'api_ops.secret.manage',
            'api_ops.webhook.manage',
            'api_ops.mapping.manage',
            'api_ops.sync.run',
            'api_ops.sync.retry',
            'api_ops.health.view',
            'api_ops.console.use',
            'api_ops.contract.manage',
        ];

        foreach ($permissions as $key) {
            Permission::query()->updateOrCreate(['key' => $key], [
                'module' => 'api_ops',
                'action' => Str::after($key, 'api_ops.'),
                'description' => 'Cho phép '.$key,
            ]);
        }

        $roleDefinitions = [
            'integration_admin' => ['display_name' => 'Quản trị tích hợp API', 'permissions' => $permissions],
            'api_ops_viewer' => ['display_name' => 'Người xem API Operations', 'permissions' => ['api_ops.view', 'api_ops.request.view', 'api_ops.health.view']],
        ];

        foreach ($roleDefinitions as $roleName => $definition) {
            $role = Role::query()->updateOrCreate([
                'tenant_id' => $tenant->id,
                'name' => $roleName,
            ], [
                'guard_name' => 'web',
                'display_name' => $definition['display_name'],
                'scope' => 'tenant',
                'description' => $definition['display_name'],
            ]);

            foreach (Permission::query()->whereIn('key', $definition['permissions'])->pluck('id') as $permissionId) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $role->id,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        Role::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('name', ['super_admin', 'tenant_admin'])
            ->get()
            ->each(function (Role $role) use ($permissions) {
                foreach (Permission::query()->whereIn('key', $permissions)->pluck('id') as $permissionId) {
                    DB::table('role_permission')->updateOrInsert([
                        'role_id' => $role->id,
                        'permission_id' => $permissionId,
                    ]);
                }
            });
    }
}
