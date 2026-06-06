<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scorm_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('title');
            $table->string('standard')->default('scorm_1_2');
            $table->string('version')->nullable();
            $table->string('launch_path')->nullable();
            $table->string('zip_path')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('checksum')->nullable();
            $table->string('status')->default('ready');
            $table->jsonb('manifest')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->index(['tenant_id', 'standard', 'status']);
            $table->timestamps();
        });

        Schema::create('scorm_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('package_id');
            $table->unsignedBigInteger('user_id');
            $table->string('standard')->default('scorm_1_2');
            $table->string('status')->default('launched');
            $table->decimal('progress', 5, 2)->default(0);
            $table->decimal('score', 6, 2)->nullable();
            $table->string('completion_status')->default('not_attempted');
            $table->string('success_status')->nullable();
            $table->unsignedInteger('session_time_seconds')->default(0);
            $table->jsonb('runtime_data')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->index(['tenant_id', 'package_id', 'user_id']);
            $table->index(['tenant_id', 'completion_status']);
            $table->timestamps();
        });

        Schema::create('scorm_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('attempt_id');
            $table->unsignedBigInteger('package_id');
            $table->string('event_type');
            $table->decimal('progress', 5, 2)->nullable();
            $table->decimal('score', 6, 2)->nullable();
            $table->string('completion_status')->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->index(['tenant_id', 'package_id', 'event_type']);
            $table->timestamps();
        });

        Schema::create('xapi_statements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('statement_uuid')->unique();
            $table->jsonb('actor');
            $table->jsonb('verb');
            $table->jsonb('object');
            $table->jsonb('result')->nullable();
            $table->jsonb('context')->nullable();
            $table->jsonb('raw_statement')->nullable();
            $table->timestamp('stored_at')->nullable();
            $table->timestamp('timestamp')->nullable();
            $table->index(['tenant_id', 'stored_at']);
            $table->timestamps();
        });

        Schema::create('lti_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('issuer');
            $table->string('client_id');
            $table->string('deployment_id');
            $table->string('login_url');
            $table->string('launch_url');
            $table->string('jwks_url')->nullable();
            $table->string('tool_type')->default('tool_provider');
            $table->string('status')->default('active');
            $table->jsonb('scopes')->nullable();
            $table->jsonb('settings')->nullable();
            $table->unique(['tenant_id', 'client_id', 'deployment_id']);
            $table->index(['tenant_id', 'status']);
            $table->timestamps();
        });

        Schema::create('lti_launches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('registration_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('resource_link_id')->nullable();
            $table->string('target_link_uri');
            $table->jsonb('roles')->nullable();
            $table->jsonb('claims')->nullable();
            $table->string('status')->default('launched');
            $table->timestamp('launched_at')->nullable();
            $table->index(['tenant_id', 'registration_id', 'status']);
            $table->timestamps();
        });

        Schema::create('external_tools', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('category');
            $table->string('provider');
            $table->string('launch_type')->default('external_url');
            $table->string('launch_url')->nullable();
            $table->unsignedBigInteger('lti_registration_id')->nullable();
            $table->string('status')->default('active');
            $table->jsonb('capabilities')->nullable();
            $table->jsonb('settings')->nullable();
            $table->index(['tenant_id', 'category', 'status']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_tools');
        Schema::dropIfExists('lti_launches');
        Schema::dropIfExists('lti_registrations');
        Schema::dropIfExists('xapi_statements');
        Schema::dropIfExists('scorm_events');
        Schema::dropIfExists('scorm_attempts');
        Schema::dropIfExists('scorm_packages');
    }
};
