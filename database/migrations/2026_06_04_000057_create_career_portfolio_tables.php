<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('career_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('headline')->nullable();
            $table->text('summary')->nullable();
            $table->string('public_slug')->nullable();
            $table->string('public_url')->nullable();
            $table->string('qr_payload')->nullable();
            $table->string('visibility')->default('private');
            $table->string('lifecycle_status')->default('student');
            $table->jsonb('resume_data')->nullable();
            $table->jsonb('settings')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
            $table->unique(['tenant_id', 'public_slug']);
            $table->index(['tenant_id', 'visibility', 'lifecycle_status']);
        });

        Schema::create('digital_portfolios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('career_profile_id')->nullable();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('status')->default('draft');
            $table->decimal('portfolio_score', 8, 2)->default(0);
            $table->jsonb('settings')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'status']);
        });

        Schema::create('portfolio_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('portfolio_id');
            $table->unsignedBigInteger('user_id');
            $table->string('item_type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('issuer')->nullable();
            $table->string('evidence_url')->nullable();
            $table->string('file_path')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expired_at')->nullable();
            $table->string('verification_code')->nullable();
            $table->string('visibility')->default('private');
            $table->string('status')->default('draft');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'item_type']);
            $table->index(['verification_code', 'status']);
        });

        Schema::create('skill_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('category');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('level_scale')->default('0_100');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'category', 'name']);
        });

        Schema::create('learner_skills', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('skill_definition_id');
            $table->decimal('score', 8, 2)->default(0);
            $table->string('source')->default('manual');
            $table->jsonb('evidence')->nullable();
            $table->timestamp('assessed_at')->nullable();
            $table->unsignedBigInteger('assessed_by')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id', 'skill_definition_id']);
            $table->index(['tenant_id', 'user_id']);
        });

        Schema::create('competency_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('learning_outcome_id')->nullable();
            $table->string('outcome_type')->default('competency');
            $table->string('code');
            $table->string('title');
            $table->decimal('score', 8, 2)->nullable();
            $table->string('attainment_status')->default('in_progress');
            $table->jsonb('evidence')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'outcome_type']);
        });

        Schema::create('career_timeline_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('event_type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('event_date')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'event_date']);
        });

        Schema::create('employer_profile_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('employer_name')->nullable();
            $table->string('viewer_email')->nullable();
            $table->string('action')->default('viewed');
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['tenant_id', 'user_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employer_profile_views');
        Schema::dropIfExists('career_timeline_events');
        Schema::dropIfExists('competency_records');
        Schema::dropIfExists('learner_skills');
        Schema::dropIfExists('skill_definitions');
        Schema::dropIfExists('portfolio_items');
        Schema::dropIfExists('digital_portfolios');
        Schema::dropIfExists('career_profiles');
    }
};
