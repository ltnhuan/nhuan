<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('type')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('sort_order')->nullable();
            $table->string('status');
            $table->timestamp('release_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->jsonb('settings')->nullable();
            $table->index('tenant_id');
            $table->index('status');
            $table->index('course_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_sections');
    }
};
