<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cohorts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('code');
            $table->string('name');
            $table->string('type')->default('academic');
            $table->string('status')->default('active');
            $table->unsignedBigInteger('academic_unit_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
            $table->index('academic_unit_id');
        });

        Schema::create('cohort_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('cohort_id');
            $table->string('code');
            $table->string('name');
            $table->string('group_type')->default('learning_group');
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status')->default('active');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'cohort_id', 'code']);
            $table->index(['tenant_id', 'group_type', 'status']);
            $table->index('cohort_id');
        });

        Schema::create('cohort_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('cohort_id');
            $table->string('name');
            $table->string('rule_type');
            $table->jsonb('conditions');
            $table->string('action')->default('include');
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'enabled', 'priority']);
            $table->index('cohort_id');
        });

        Schema::create('class_sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('cohort_id')->nullable();
            $table->unsignedBigInteger('cohort_group_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('sis_section_id')->nullable();
            $table->string('code');
            $table->string('name');
            $table->string('section_type')->default('class_section');
            $table->string('delivery_mode')->nullable();
            $table->string('status')->default('active');
            $table->unsignedInteger('capacity')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->jsonb('schedule')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'course_id', 'status']);
            $table->index(['tenant_id', 'sis_section_id']);
            $table->index(['tenant_id', 'section_type']);
            $table->index('parent_id');
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('class_section_id')->nullable();
            $table->unsignedBigInteger('cohort_id')->nullable();
            $table->unsignedBigInteger('cohort_group_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('source')->default('manual');
            $table->string('sis_enrollment_id')->nullable();
            $table->string('status')->default('pending');
            $table->decimal('completion_percent', 5, 2)->default(0);
            $table->decimal('risk_score', 5, 2)->default(0);
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'class_section_id', 'user_id']);
            $table->index(['tenant_id', 'course_id', 'status']);
            $table->index(['tenant_id', 'class_section_id', 'status']);
            $table->index(['tenant_id', 'cohort_id', 'status']);
            $table->index(['tenant_id', 'source']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'risk_score']);
            $table->index(['tenant_id', 'sis_enrollment_id']);
        });

        Schema::create('cohort_enrollments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('cohort_id');
            $table->unsignedBigInteger('cohort_group_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('source')->default('manual');
            $table->string('status')->default('active');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'cohort_id', 'user_id']);
            $table->index(['tenant_id', 'cohort_group_id', 'status']);
            $table->index(['tenant_id', 'user_id']);
        });

        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('class_section_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role');
            $table->string('status')->default('active');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'class_section_id', 'user_id', 'role']);
            $table->index(['tenant_id', 'role', 'status']);
            $table->index(['tenant_id', 'user_id']);
        });

        Schema::create('enrollment_import_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('source')->default('csv');
            $table->string('format')->default('csv');
            $table->string('status')->default('pending');
            $table->string('file_path')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->jsonb('payload')->nullable();
            $table->jsonb('errors')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'source']);
        });

        Schema::create('enrollment_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('enrollment_id');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('event_type');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'enrollment_id']);
            $table->index(['tenant_id', 'event_type']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_events');
        Schema::dropIfExists('enrollment_import_jobs');
        Schema::dropIfExists('teacher_assignments');
        Schema::dropIfExists('cohort_enrollments');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('class_sections');
        Schema::dropIfExists('cohort_rules');
        Schema::dropIfExists('cohort_groups');
        Schema::dropIfExists('cohorts');
    }
};
