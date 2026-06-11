<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gradebooks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('class_id')->nullable();
            $table->string('title');
            $table->string('grading_scheme')->default('weighted');
            $table->string('status')->default('draft');
            $table->jsonb('settings')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->index(['tenant_id', 'course_id', 'class_id']);
            $table->index(['tenant_id', 'status']);
            $table->timestamps();
        });

        Schema::create('grade_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('gradebook_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('title');
            $table->decimal('weight', 8, 2)->nullable();
            $table->decimal('max_score', 8, 2)->nullable();
            $table->string('aggregation_method')->default('weighted');
            $table->text('formula')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->index(['tenant_id', 'gradebook_id', 'sort_order']);
            $table->timestamps();
        });

        Schema::create('grade_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('gradebook_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('title');
            $table->decimal('max_score', 8, 2);
            $table->decimal('weight', 8, 2)->nullable();
            $table->boolean('required')->default(false);
            $table->text('formula')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('settings')->nullable();
            $table->index(['tenant_id', 'gradebook_id', 'sort_order']);
            $table->index(['source_type', 'source_id']);
            $table->timestamps();
        });

        Schema::create('learner_grades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('gradebook_id');
            $table->unsignedBigInteger('grade_item_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('raw_score', 8, 2)->nullable();
            $table->decimal('final_score', 8, 2)->nullable();
            $table->string('letter_grade')->nullable();
            $table->string('pass_status')->nullable();
            $table->string('source_status')->default('pending');
            $table->text('feedback')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unique(['tenant_id', 'grade_item_id', 'user_id']);
            $table->index(['tenant_id', 'gradebook_id', 'user_id']);
            $table->timestamps();
        });

        Schema::create('grade_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('gradebook_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('total_score', 8, 2)->default(0);
            $table->decimal('max_score', 8, 2)->default(0);
            $table->decimal('percent', 8, 2)->default(0);
            $table->string('letter_grade')->nullable();
            $table->string('pass_status')->default('not_evaluated');
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->unique(['tenant_id', 'gradebook_id', 'user_id']);
            $table->index(['tenant_id', 'gradebook_id', 'status']);
            $table->timestamps();
        });

        Schema::create('grade_change_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('gradebook_id');
            $table->unsignedBigInteger('grade_item_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('actor_id');
            $table->timestamp('created_at')->nullable();
            $table->index(['tenant_id', 'gradebook_id', 'user_id']);
            $table->index('created_at');
        });

        Schema::create('grade_approval_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('gradebook_id');
            $table->string('title');
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('sync_status')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->index(['tenant_id', 'gradebook_id', 'status']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_approval_batches');
        Schema::dropIfExists('grade_change_logs');
        Schema::dropIfExists('grade_summaries');
        Schema::dropIfExists('learner_grades');
        Schema::dropIfExists('grade_items');
        Schema::dropIfExists('grade_categories');
        Schema::dropIfExists('gradebooks');
    }
};
