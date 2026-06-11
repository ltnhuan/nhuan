<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_systems', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('code');
            $table->string('name');
            $table->string('system_type')->default('sis');
            $table->string('base_url');
            $table->string('auth_type')->default('api_key');
            $table->text('credentials_encrypted')->nullable();
            $table->string('status')->default('active');
            $table->jsonb('settings')->nullable();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'system_type', 'status']);
            $table->timestamps();
        });

        Schema::create('integration_api_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('system_id');
            $table->string('key_name');
            $table->string('api_key_hash');
            $table->jsonb('scopes')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->index(['tenant_id', 'system_id', 'status']);
            $table->timestamps();
        });

        Schema::create('integration_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('system_id');
            $table->string('entity_type');
            $table->string('local_id');
            $table->string('external_id');
            $table->string('external_code')->nullable();
            $table->string('mapping_status')->default('active');
            $table->jsonb('metadata')->nullable();
            $table->unique(['tenant_id', 'system_id', 'entity_type', 'local_id']);
            $table->unique(['tenant_id', 'system_id', 'entity_type', 'external_id']);
            $table->index(['tenant_id', 'entity_type', 'mapping_status']);
            $table->timestamps();
        });

        Schema::create('integration_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('system_id')->nullable();
            $table->string('event_key');
            $table->string('direction');
            $table->string('entity_type');
            $table->string('entity_id')->nullable();
            $table->string('idempotency_key');
            $table->jsonb('payload')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unique(['system_id', 'event_key', 'idempotency_key']);
            $table->index(['tenant_id', 'direction', 'status']);
            $table->index(['tenant_id', 'event_key']);
            $table->timestamps();
        });

        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('system_id');
            $table->string('name');
            $table->string('url');
            $table->string('secret');
            $table->jsonb('subscribed_events')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->jsonb('settings')->nullable();
            $table->index(['tenant_id', 'system_id', 'status']);
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('endpoint_id');
            $table->unsignedBigInteger('event_id');
            $table->jsonb('payload')->nullable();
            $table->unsignedInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->index(['tenant_id', 'status', 'next_retry_at']);
            $table->timestamps();
        });

        Schema::create('sync_jobs', function (Blueprint $table) {
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
            $table->index(['tenant_id', 'system_id', 'status']);
            $table->timestamps();
        });

        Schema::create('sync_conflicts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('system_id');
            $table->string('entity_type');
            $table->string('local_id')->nullable();
            $table->string('external_id')->nullable();
            $table->string('conflict_type');
            $table->jsonb('local_snapshot')->nullable();
            $table->jsonb('external_snapshot')->nullable();
            $table->string('status')->default('open');
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->index(['tenant_id', 'system_id', 'status']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_conflicts');
        Schema::dropIfExists('sync_jobs');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('integration_events');
        Schema::dropIfExists('integration_mappings');
        Schema::dropIfExists('integration_api_keys');
        Schema::dropIfExists('integration_systems');
    }
};
