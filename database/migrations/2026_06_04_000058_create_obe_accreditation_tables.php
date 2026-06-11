<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competency_frameworks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('code');
            $table->string('title');
            $table->string('framework_type')->default('OBE');
            $table->string('standard')->default('AUN-QA');
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->jsonb('settings')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'framework_type', 'status']);
            $table->timestamps();
        });

        Schema::create('competency_framework_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('framework_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code');
            $table->string('title');
            $table->string('item_type')->default('competency');
            $table->string('level')->nullable();
            $table->text('description')->nullable();
            $table->jsonb('rubric')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('active');
            $table->unique(['tenant_id', 'framework_id', 'code']);
            $table->index(['tenant_id', 'framework_id', 'item_type']);
            $table->timestamps();
        });

        Schema::create('outcome_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('source_outcome_id')->nullable();
            $table->unsignedBigInteger('target_outcome_id')->nullable();
            $table->unsignedBigInteger('competency_item_id')->nullable();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('target_type');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->decimal('weight', 8, 2)->default(1);
            $table->string('evidence_level')->default('introduced');
            $table->jsonb('metadata')->nullable();
            $table->unique(['tenant_id', 'source_type', 'source_id', 'target_type', 'target_id', 'source_outcome_id', 'target_outcome_id'], 'outcome_mappings_unique_path');
            $table->index(['tenant_id', 'source_type', 'target_type']);
            $table->index(['tenant_id', 'target_outcome_id']);
            $table->timestamps();
        });

        Schema::create('assessment_outcome_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('assessment_type');
            $table->unsignedBigInteger('assessment_id');
            $table->unsignedBigInteger('outcome_id');
            $table->string('method')->default('direct');
            $table->decimal('weight', 8, 2)->default(1);
            $table->decimal('max_score', 8, 2)->nullable();
            $table->jsonb('rubric_criteria')->nullable();
            $table->string('status')->default('active');
            $table->unique(['tenant_id', 'assessment_type', 'assessment_id', 'outcome_id']);
            $table->index(['tenant_id', 'outcome_id', 'assessment_type']);
            $table->timestamps();
        });

        Schema::create('outcome_achievement_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('outcome_id')->nullable();
            $table->unsignedBigInteger('competency_item_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->decimal('achievement_percent', 8, 2)->default(0);
            $table->unsignedInteger('assessed_count')->default(0);
            $table->unsignedInteger('evidence_count')->default(0);
            $table->string('attainment_status')->default('not_evaluated');
            $table->jsonb('metadata')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['tenant_id', 'course_id', 'class_id']);
            $table->index(['tenant_id', 'outcome_id', 'attainment_status']);
        });

        Schema::create('accreditation_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('title');
            $table->string('standard')->default('AUN-QA');
            $table->string('report_type')->default('outcome_coverage');
            $table->string('format')->default('pdf');
            $table->string('status')->default('draft');
            $table->jsonb('filters')->nullable();
            $table->jsonb('metrics')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->index(['tenant_id', 'standard', 'status']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accreditation_reports');
        Schema::dropIfExists('outcome_achievement_summaries');
        Schema::dropIfExists('assessment_outcome_mappings');
        Schema::dropIfExists('outcome_mappings');
        Schema::dropIfExists('competency_framework_items');
        Schema::dropIfExists('competency_frameworks');
    }
};
