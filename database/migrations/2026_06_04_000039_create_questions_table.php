<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('question_bank_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('code');
            $table->string('question_type');
            $table->string('title');
            $table->text('stem');
            $table->text('explanation')->nullable();
            $table->string('difficulty')->default('medium');
            $table->string('bloom_level')->default('understand');
            $table->decimal('default_score', 8, 2)->default(1);
            $table->decimal('penalty_score', 8, 2)->default(0);
            $table->unsignedInteger('time_limit_seconds')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'question_bank_id', 'category_id']);
            $table->index(['question_type', 'difficulty', 'bloom_level', 'status'], 'questions_filter_index');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
