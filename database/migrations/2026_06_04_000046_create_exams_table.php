<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('component_id')->nullable();
            $table->unsignedBigInteger('question_bank_id')->nullable();
            $table->unsignedBigInteger('blueprint_id')->nullable();
            $table->string('code');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('exam_type')->default('quiz');
            $table->string('delivery_mode')->default('self_paced');
            $table->string('status')->default('draft');
            $table->decimal('total_score', 8, 2)->default(0);
            $table->decimal('pass_score', 8, 2)->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->unsignedInteger('max_attempts')->default(1);
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('shuffle_options')->default(true);
            $table->string('show_result_mode')->default('after_close');
            $table->boolean('show_correct_answers')->default(false);
            $table->timestamp('open_at')->nullable();
            $table->timestamp('close_at')->nullable();
            $table->jsonb('settings')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'course_id', 'status']);
            $table->index(['exam_type', 'delivery_mode']);
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('exams'); }
};
