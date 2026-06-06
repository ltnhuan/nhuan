<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_progress_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('section_id')->nullable();
            $table->unsignedBigInteger('component_id')->nullable();
            $table->string('event_type');
            $table->decimal('event_value', 8, 2)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->unsignedBigInteger('device_id')->nullable();
            $table->index(['tenant_id', 'user_id', 'course_id']);
            $table->index('course_id');
            $table->index('component_id');
            $table->index('event_type');
            $table->index('created_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_progress_events');
    }
};
