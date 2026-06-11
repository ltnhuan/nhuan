<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_assets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('repository_item_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('component_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('original_filename');
            $table->string('original_storage_path');
            $table->string('hls_master_path')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('mime_type')->nullable();
            $table->string('processing_status')->default('pending');
            $table->string('visibility')->default('private');
            $table->string('checksum')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('subtitle_path')->nullable();
            $table->string('transcript_path')->nullable();
            $table->jsonb('settings')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->index(['tenant_id', 'course_id', 'component_id']);
            $table->index(['processing_status', 'visibility']);
            $table->index('repository_item_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_assets');
    }
};
