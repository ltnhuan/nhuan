<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_watch_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('component_id');
            $table->unsignedBigInteger('video_asset_id');
            $table->unsignedBigInteger('session_id');
            $table->string('event_type');
            $table->decimal('position_seconds', 10, 2)->default(0);
            $table->decimal('watched_delta_seconds', 10, 2)->nullable();
            $table->decimal('playback_rate', 4, 2)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->index(['tenant_id', 'user_id', 'course_id', 'component_id'], 'video_events_scope_index');
            $table->index('video_asset_id');
            $table->index('session_id');
            $table->index('event_type');
            $table->index('created_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_watch_events');
    }
};
