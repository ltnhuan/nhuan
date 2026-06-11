<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('code');
            $table->string('name');
            $table->string('type')->default('certificate');
            $table->string('language')->default('vi');
            $table->string('status')->default('draft');
            $table->jsonb('canvas_schema')->nullable();
            $table->jsonb('dynamic_fields')->nullable();
            $table->string('signature_image_path')->nullable();
            $table->jsonb('translations')->nullable();
            $table->jsonb('settings')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status', 'type']);
        });

        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('certificate_template_id')->nullable();
            $table->string('code');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('credential_type')->default('course_certificate');
            $table->string('issuer_name')->default('EraLMS');
            $table->jsonb('rules')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'credential_type', 'status']);
        });

        Schema::create('certificate_issues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('certificate_id');
            $table->unsignedBigInteger('certificate_template_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('learning_path_id')->nullable();
            $table->string('issue_code');
            $table->string('learner_name');
            $table->string('certificate_title');
            $table->string('status')->default('issued');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('qr_payload');
            $table->string('verification_hash');
            $table->string('verification_url');
            $table->string('language')->default('vi');
            $table->jsonb('field_values')->nullable();
            $table->jsonb('sis_payload')->nullable();
            $table->unsignedBigInteger('portfolio_item_id')->nullable();
            $table->string('blockchain_status')->default('pending');
            $table->string('blockchain_network')->nullable();
            $table->string('blockchain_tx_hash')->nullable();
            $table->string('revocation_reason')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique('issue_code');
            $table->index(['tenant_id', 'user_id', 'status']);
            $table->index(['tenant_id', 'course_id']);
        });

        Schema::create('certificate_verifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('certificate_issue_id')->nullable();
            $table->string('issue_code');
            $table->string('verification_hash')->nullable();
            $table->boolean('valid')->default(false);
            $table->string('status')->default('unknown');
            $table->string('viewer_ip')->nullable();
            $table->string('viewer_user_agent')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['issue_code', 'valid']);
            $table->index('created_at');
        });

        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('code');
            $table->string('name');
            $table->string('badge_type')->default('completion');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->jsonb('criteria')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'badge_type', 'status']);
        });

        Schema::create('badge_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('badge_id');
            $table->string('rule_type');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->jsonb('conditions')->nullable();
            $table->boolean('auto_issue')->default(true);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['tenant_id', 'rule_type', 'status']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('badge_issues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('badge_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('issue_code');
            $table->string('status')->default('issued');
            $table->timestamp('issued_at')->nullable();
            $table->string('verification_hash');
            $table->string('verification_url');
            $table->jsonb('evidence')->nullable();
            $table->unsignedBigInteger('portfolio_item_id')->nullable();
            $table->timestamps();
            $table->unique('issue_code');
            $table->unique(['tenant_id', 'badge_id', 'user_id', 'course_id']);
            $table->index(['tenant_id', 'user_id', 'status']);
        });

        Schema::create('micro_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('code');
            $table->string('title');
            $table->string('credential_type')->default('skill_based');
            $table->string('industry')->nullable();
            $table->string('level')->nullable();
            $table->text('outcome_statement')->nullable();
            $table->jsonb('criteria')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'credential_type', 'industry', 'status']);
        });

        Schema::create('credential_skill_maps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('credential_type');
            $table->unsignedBigInteger('credential_id');
            $table->unsignedBigInteger('skill_definition_id')->nullable();
            $table->string('skill_code')->nullable();
            $table->string('skill_name');
            $table->decimal('required_score', 8, 2)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'credential_type', 'credential_id']);
        });

        Schema::create('credential_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('credential_type');
            $table->unsignedBigInteger('credential_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('event_type');
            $table->string('source')->default('eralms');
            $table->jsonb('payload')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['tenant_id', 'credential_type', 'event_type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_events');
        Schema::dropIfExists('credential_skill_maps');
        Schema::dropIfExists('micro_credentials');
        Schema::dropIfExists('badge_issues');
        Schema::dropIfExists('badge_rules');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('certificate_verifications');
        Schema::dropIfExists('certificate_issues');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('certificate_templates');
    }
};
