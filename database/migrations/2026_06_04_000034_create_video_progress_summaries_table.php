<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_progress_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('component_id');
            $table->unsignedBigInteger('video_asset_id');
            $table->unsignedInteger('total_duration_seconds')->default(0);
            $table->decimal('watched_seconds', 10, 2)->default(0);
            $table->decimal('max_position_seconds', 10, 2)->default(0);
            $table->decimal('watch_percent', 8, 2)->default(0);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->decimal('suspicious_score', 8, 2)->default(0);
            $table->timestamp('last_watched_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->unique(['tenant_id', 'user_id', 'component_id', 'video_asset_id'], 'video_summary_unique_user_component');
            $table->index(['tenant_id', 'course_id', 'component_id']);
            $table->index(['is_completed', 'suspicious_score']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_progress_summaries');
    }
};
