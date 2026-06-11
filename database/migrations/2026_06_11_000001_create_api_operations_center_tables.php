<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_systems', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('code');
            $table->string('name');
            $table->string('type')->default('third_party');
            $table->string('base_url');
            $table->string('environment')->default('staging');
            $table->string('auth_type')->default('none');
            $table->string('status')->default('active');
            $table->string('owner_team')->nullable();
            $table->text('description')->nullable();
            $table->jsonb('settings')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'type', 'status']);
            $table->index(['tenant_id', 'environment']);
        });

        Schema::create('api_endpoints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('system_id');
            $table->string('code');
            $table->string('name');
            $table->string('method');
            $table->string('path');
            $table->string('full_url');
            $table->string('module')->nullable();
            $table->text('purpose')->nullable();
            $table->jsonb('request_schema')->nullable();
            $table->jsonb('response_schema')->nullable();
            $table->unsignedInteger('timeout_ms')->default(5000);
            $table->jsonb('retry_policy')->nullable();
            $table->jsonb('rate_limit')->nullable();
            $table->string('permission_key')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['tenant_id', 'system_id', 'code']);
            $table->index(['tenant_id', 'system_id', 'status']);
            $table->index(['tenant_id', 'method']);
        });

        Schema::create('api_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('system_id');
            $table->string('name');
            $table->string('credential_type');
            $table->text('encrypted_value');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('rotated_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['tenant_id', 'system_id', 'status']);
        });

        Schema::create('api_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('system_id');
            $table->unsignedBigInteger('endpoint_id')->nullable();
            $table->uuid('request_uuid');
            $table->string('direction');
            $table->string('method');
            $table->text('url');
            $table->unsignedInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->jsonb('request_headers')->nullable();
            $table->jsonb('request_body')->nullable();
            $table->jsonb('response_headers')->nullable();
            $table->jsonb('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique('request_uuid');
            $table->index(['tenant_id', 'system_id', 'created_at']);
            $table->index(['tenant_id', 'endpoint_id', 'created_at']);
            $table->index(['tenant_id', 'status_code']);
            $table->index(['tenant_id', 'direction']);
        });

        Schema::create('api_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('event_key');
            $table->unsignedBigInteger('source_system_id')->nullable();
            $table->unsignedBigInteger('target_system_id')->nullable();
            $table->string('entity_type');
            $table->string('entity_id')->nullable();
            $table->string('idempotency_key');
            $table->jsonb('payload')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'event_key', 'idempotency_key']);
            $table->index(['tenant_id', 'status', 'next_retry_at']);
            $table->index(['tenant_id', 'event_key']);
            $table->index(['tenant_id', 'source_system_id']);
            $table->index(['tenant_id', 'target_system_id']);
        });

        Schema::create('api_webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('system_id');
            $table->string('name');
            $table->string('url');
            $table->text('secret');
            $table->jsonb('subscribed_events')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->jsonb('settings')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'system_id', 'status']);
        });

        Schema::create('api_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('webhook_endpoint_id');
            $table->unsignedBigInteger('api_event_id');
            $table->jsonb('payload')->nullable();
            $table->unsignedInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'next_retry_at']);
            $table->index(['tenant_id', 'webhook_endpoint_id']);
        });

        Schema::create('api_data_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('source_system_id');
            $table->unsignedBigInteger('target_system_id');
            $table->string('entity_type');
            $table->string('source_field');
            $table->string('target_field');
            $table->jsonb('transform_rule')->nullable();
            $table->boolean('is_required')->default(false);
            $table->string('default_value')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['tenant_id', 'entity_type', 'status']);
            $table->index(['tenant_id', 'source_system_id', 'target_system_id']);
        });

        Schema::create('api_entity_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('system_id');
            $table->string('entity_type');
            $table->string('local_id');
            $table->string('external_id');
            $table->string('external_code')->nullable();
            $table->string('status')->default('active');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'system_id', 'entity_type', 'local_id']);
            $table->index(['tenant_id', 'system_id', 'entity_type', 'external_id']);
            $table->index(['tenant_id', 'entity_type', 'status']);
        });

        Schema::create('api_sync_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('system_id');
            $table->string('job_type');
            $table->string('entity_type');
            $table->string('status')->default('pending');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->jsonb('error_report')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'system_id', 'status']);
            $table->index(['tenant_id', 'job_type', 'entity_type']);
        });

        Schema::create('api_sync_job_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sync_job_id');
            $table->string('entity_type');
            $table->string('entity_id')->nullable();
            $table->string('external_id')->nullable();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'sync_job_id', 'status']);
            $table->index(['tenant_id', 'entity_type', 'entity_id']);
        });

        Schema::create('api_data_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('system_id');
            $table->string('entity_type');
            $table->string('version');
            $table->jsonb('schema')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('effective_from')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'system_id', 'entity_type', 'version']);
            $table->index(['tenant_id', 'entity_type', 'status']);
        });

        Schema::create('api_health_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('system_id');
            $table->unsignedBigInteger('endpoint_id')->nullable();
            $table->string('status')->default('healthy');
            $table->unsignedInteger('latency_ms')->nullable();
            $table->decimal('success_rate', 5, 2)->nullable();
            $table->decimal('error_rate', 5, 2)->nullable();
            $table->timestamp('checked_at');
            $table->jsonb('metadata')->nullable();

            $table->index(['tenant_id', 'system_id', 'checked_at']);
            $table->index(['tenant_id', 'endpoint_id', 'checked_at']);
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('api_error_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('error_code');
            $table->text('pattern');
            $table->string('severity')->default('medium');
            $table->boolean('auto_retry')->default(true);
            $table->unsignedInteger('max_retry')->default(3);
            $table->jsonb('notify_roles')->nullable();
            $table->text('recommended_action')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'severity']);
            $table->index(['tenant_id', 'error_code']);
        });

        Schema::create('api_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action');
            $table->string('module');
            $table->string('entity_type');
            $table->string('entity_id')->nullable();
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'module', 'created_at']);
            $table->index(['tenant_id', 'entity_type', 'entity_id']);
            $table->index(['tenant_id', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_audit_logs');
        Schema::dropIfExists('api_error_rules');
        Schema::dropIfExists('api_health_snapshots');
        Schema::dropIfExists('api_data_contracts');
        Schema::dropIfExists('api_sync_job_items');
        Schema::dropIfExists('api_sync_jobs');
        Schema::dropIfExists('api_entity_mappings');
        Schema::dropIfExists('api_data_mappings');
        Schema::dropIfExists('api_webhook_deliveries');
        Schema::dropIfExists('api_webhook_endpoints');
        Schema::dropIfExists('api_events');
        Schema::dropIfExists('api_requests');
        Schema::dropIfExists('api_credentials');
        Schema::dropIfExists('api_endpoints');
        Schema::dropIfExists('api_systems');
    }
};
