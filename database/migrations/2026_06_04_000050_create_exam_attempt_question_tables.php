<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempt_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('attempt_id');
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('section_id')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->decimal('score', 8, 2)->default(1);
            $table->jsonb('question_snapshot');
            $table->jsonb('options_snapshot')->nullable();
            $table->boolean('is_answered')->default(false);
            $table->boolean('is_marked_review')->default(false);
            $table->jsonb('metadata')->nullable();
            $table->index(['tenant_id', 'attempt_id']);
            $table->timestamps();
        });

        Schema::create('exam_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('attempt_id');
            $table->unsignedBigInteger('attempt_question_id');
            $table->unsignedBigInteger('question_id');
            $table->jsonb('answer_data');
            $table->boolean('is_correct')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->unsignedBigInteger('graded_by')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamp('autosaved_at')->nullable();
            $table->unique(['attempt_id', 'attempt_question_id']);
            $table->index(['tenant_id', 'attempt_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_answers');
        Schema::dropIfExists('exam_attempt_questions');
    }
};
