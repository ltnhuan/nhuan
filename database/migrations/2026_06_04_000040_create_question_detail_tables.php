<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('question_id');
            $table->string('option_key');
            $table->text('content');
            $table->boolean('is_correct')->default(false);
            $table->decimal('score_weight', 8, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('media_url')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->index(['tenant_id', 'question_id']);
            $table->timestamps();
        });

        Schema::create('question_matching_pairs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('question_id');
            $table->text('left_content');
            $table->text('right_content');
            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->index(['tenant_id', 'question_id']);
            $table->timestamps();
        });

        Schema::create('question_fill_blank_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('question_id');
            $table->string('blank_key');
            $table->string('accepted_answer');
            $table->boolean('case_sensitive')->default(false);
            $table->decimal('score_weight', 8, 2)->default(1);
            $table->text('feedback')->nullable();
            $table->index(['tenant_id', 'question_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_fill_blank_answers');
        Schema::dropIfExists('question_matching_pairs');
        Schema::dropIfExists('question_options');
    }
};
