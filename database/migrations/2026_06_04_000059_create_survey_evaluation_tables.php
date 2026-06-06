<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_forms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('code');
            $table->string('title');
            $table->string('survey_type')->default('general');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->jsonb('settings')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'survey_type', 'status']);
        });

        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('survey_form_id');
            $table->string('question_type');
            $table->string('code');
            $table->text('prompt');
            $table->text('help_text')->nullable();
            $table->boolean('required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('options')->nullable();
            $table->jsonb('matrix_rows')->nullable();
            $table->jsonb('matrix_columns')->nullable();
            $table->jsonb('scoring')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'survey_form_id', 'code']);
            $table->index(['tenant_id', 'survey_form_id', 'sort_order']);
            $table->index(['tenant_id', 'question_type']);
        });

        Schema::create('survey_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('survey_form_id');
            $table->string('code');
            $table->string('title');
            $table->string('target_scope');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('class_section_id')->nullable();
            $table->unsignedBigInteger('academic_unit_id')->nullable();
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->boolean('is_anonymous')->default(true);
            $table->boolean('allow_identified')->default(false);
            $table->string('status')->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->jsonb('channels')->nullable();
            $table->jsonb('settings')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'target_scope', 'status']);
            $table->index(['tenant_id', 'course_id', 'class_section_id']);
            $table->index(['tenant_id', 'academic_unit_id']);
            $table->index(['tenant_id', 'teacher_id']);
        });

        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('survey_campaign_id');
            $table->unsignedBigInteger('survey_form_id');
            $table->unsignedBigInteger('respondent_id')->nullable();
            $table->string('respondent_hash')->nullable();
            $table->boolean('is_anonymous')->default(true);
            $table->string('status')->default('submitted');
            $table->decimal('average_score', 8, 2)->nullable();
            $table->integer('nps_score')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'survey_campaign_id', 'submitted_at']);
            $table->index(['tenant_id', 'survey_form_id']);
            $table->index(['tenant_id', 'respondent_id']);
        });

        Schema::create('survey_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('survey_response_id');
            $table->unsignedBigInteger('survey_question_id');
            $table->string('question_type');
            $table->text('text_answer')->nullable();
            $table->decimal('numeric_answer', 8, 2)->nullable();
            $table->jsonb('json_answer')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'survey_response_id', 'survey_question_id']);
            $table->index(['tenant_id', 'survey_question_id', 'score']);
        });

        Schema::create('survey_improvements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('survey_campaign_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('class_section_id')->nullable();
            $table->unsignedBigInteger('academic_unit_id')->nullable();
            $table->unsignedBigInteger('teacher_id')->nullable();
            $table->string('issue_title');
            $table->text('issue_description')->nullable();
            $table->text('improvement_action');
            $table->text('result')->nullable();
            $table->string('status')->default('open');
            $table->string('priority')->default('medium');
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->jsonb('metrics')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'priority']);
            $table->index(['tenant_id', 'survey_campaign_id']);
            $table->index(['tenant_id', 'course_id', 'class_section_id']);
        });

        Schema::create('survey_evidence_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('survey_campaign_id')->nullable();
            $table->unsignedBigInteger('survey_improvement_id')->nullable();
            $table->string('evidence_type')->default('survey_report');
            $table->string('title');
            $table->string('file_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('checksum')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'evidence_type']);
            $table->index(['tenant_id', 'survey_campaign_id']);
            $table->index(['tenant_id', 'survey_improvement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_evidence_files');
        Schema::dropIfExists('survey_improvements');
        Schema::dropIfExists('survey_answers');
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('survey_campaigns');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('survey_forms');
    }
};
