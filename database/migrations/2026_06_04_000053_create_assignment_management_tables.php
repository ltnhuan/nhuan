<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('max_score', 8, 2)->default(0);
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'course_id', 'status']);
        });

        Schema::create('rubric_criteria', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('rubric_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('max_score', 8, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(1);
            $table->jsonb('metadata')->nullable();
            $table->index(['tenant_id', 'rubric_id']);
        });

        Schema::create('rubric_levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('criterion_id');
            $table->string('level_name');
            $table->text('description')->nullable();
            $table->decimal('score', 8, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(1);
            $table->index(['tenant_id', 'criterion_id']);
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('component_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('assignment_type')->default('individual');
            $table->string('submission_type')->default('mixed');
            $table->string('status')->default('draft');
            $table->timestamp('open_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->boolean('allow_late')->default(false);
            $table->jsonb('late_penalty_config')->nullable();
            $table->decimal('max_score', 8, 2)->default(10);
            $table->decimal('pass_score', 8, 2)->nullable();
            $table->unsignedInteger('max_submissions')->nullable();
            $table->unsignedBigInteger('rubric_id')->nullable();
            $table->jsonb('settings')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'course_id', 'status']);
            $table->index(['component_id', 'due_at']);
        });

        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('assignment_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('group_id')->nullable();
            $table->unsignedInteger('submission_no');
            $table->string('status')->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->text('content_text')->nullable();
            $table->text('content_url')->nullable();
            $table->decimal('total_score', 8, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->unsignedBigInteger('graded_by')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'assignment_id', 'user_id', 'submission_no'], 'assignment_submission_version_unique');
            $table->index(['tenant_id', 'assignment_id', 'status']);
            $table->index(['user_id', 'group_id']);
        });

        Schema::create('assignment_submission_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('submission_id');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('checksum')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['tenant_id', 'submission_id']);
        });

        Schema::create('assignment_grades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('submission_id');
            $table->unsignedBigInteger('assignment_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('score', 8, 2);
            $table->decimal('max_score', 8, 2);
            $table->text('feedback')->nullable();
            $table->jsonb('rubric_breakdown')->nullable();
            $table->decimal('ai_suggested_score', 8, 2)->nullable();
            $table->text('ai_feedback')->nullable();
            $table->string('grading_status')->default('draft');
            $table->unsignedBigInteger('graded_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'assignment_id', 'grading_status']);
            $table->index(['submission_id', 'user_id']);
        });

        Schema::create('assignment_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('assignment_id');
            $table->unsignedBigInteger('submission_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('event_type');
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['tenant_id', 'assignment_id', 'event_type']);
            $table->index(['submission_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_events');
        Schema::dropIfExists('assignment_grades');
        Schema::dropIfExists('assignment_submission_files');
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('rubric_levels');
        Schema::dropIfExists('rubric_criteria');
        Schema::dropIfExists('rubrics');
    }
};
