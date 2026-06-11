<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_queue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('client_uuid');
            $table->string('device_id')->nullable();
            $table->string('operation');
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->jsonb('payload');
            $table->jsonb('result')->nullable();
            $table->jsonb('conflict')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'client_uuid']);
            $table->index(['tenant_id', 'user_id', 'status']);
            $table->index('operation');
        });

        Schema::create('offline_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('component_id');
            $table->string('device_id')->nullable();
            $table->decimal('progress_percent', 8, 2)->default(0);
            $table->string('status')->default('in_progress');
            $table->jsonb('metadata')->nullable();
            $table->timestamp('client_updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'course_id', 'component_id']);
            $table->index(['tenant_id', 'user_id', 'status']);
        });

        Schema::create('offline_quiz_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('exam_id')->nullable();
            $table->unsignedBigInteger('attempt_id')->nullable();
            $table->unsignedBigInteger('attempt_question_id')->nullable();
            $table->unsignedBigInteger('question_id')->nullable();
            $table->string('device_id')->nullable();
            $table->jsonb('answer_data');
            $table->string('status')->default('pending');
            $table->jsonb('sync_result')->nullable();
            $table->timestamp('client_updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'status']);
            $table->index(['tenant_id', 'attempt_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_quiz_answers');
        Schema::dropIfExists('offline_progress');
        Schema::dropIfExists('offline_queue');
    }
};
