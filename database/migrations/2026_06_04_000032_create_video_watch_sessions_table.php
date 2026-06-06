<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_watch_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('component_id');
            $table->unsignedBigInteger('video_asset_id');
            $table->uuid('session_uuid')->unique();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->decimal('last_position_seconds', 10, 2)->default(0);
            $table->decimal('max_position_seconds', 10, 2)->default(0);
            $table->decimal('watched_seconds', 10, 2)->default(0);
            $table->decimal('watch_percent', 8, 2)->default(0);
            $table->decimal('playback_rate', 4, 2)->default(1);
            $table->string('status')->default('active');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->index(['tenant_id', 'user_id', 'course_id', 'component_id'], 'video_sessions_scope_index');
            $table->index('video_asset_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_watch_sessions');
    }
};
