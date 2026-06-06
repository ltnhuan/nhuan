<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempt_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('attempt_id');
            $table->unsignedBigInteger('user_id');
            $table->string('event_type');
            $table->decimal('event_value', 8, 2)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->index(['tenant_id', 'attempt_id']);
            $table->index('event_type');
            $table->index('created_at');
            $table->timestamps();
        });

        Schema::create('exam_grading_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('attempt_id');
            $table->unsignedBigInteger('answer_id')->nullable();
            $table->string('action');
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('exam_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('attempt_id');
            $table->decimal('score', 8, 2);
            $table->decimal('max_score', 8, 2);
            $table->decimal('percent', 8, 2);
            $table->string('pass_status');
            $table->boolean('published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->unique(['tenant_id', 'attempt_id']);
            $table->index(['tenant_id', 'exam_id', 'user_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_results');
        Schema::dropIfExists('exam_grading_logs');
        Schema::dropIfExists('exam_attempt_events');
    }
};
