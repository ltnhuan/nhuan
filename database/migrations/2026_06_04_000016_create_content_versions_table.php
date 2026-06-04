<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('content_item_id')->nullable();
            $table->string('version')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('checksum')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('change_note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->index('tenant_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_versions');
    }
};
