<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_repository_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('academic_unit_id')->nullable();
            $table->string('item_type')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('checksum')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->string('visibility')->nullable();
            $table->string('status');
            $table->jsonb('metadata')->nullable();
            $table->index('tenant_id');
            $table->index('status');
            $table->index('academic_unit_id');
            $table->index('owner_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_repository_items');
    }
};
