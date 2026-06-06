<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('exam_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('attempt_no');
            $table->uuid('session_uuid')->unique();
            $table->string('status')->default('not_started');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('max_score', 8, 2)->default(0);
            $table->string('pass_status')->nullable();
            $table->decimal('suspicious_score', 8, 2)->default(0);
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->index(['tenant_id', 'exam_id', 'user_id', 'status']);
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('exam_attempts'); }
};
