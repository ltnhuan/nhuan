<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('section_id')->nullable();
            $table->string('component_type')->nullable();
            $table->string('title');
            $table->unsignedBigInteger('content_id')->nullable();
            $table->jsonb('config')->nullable();
            $table->string('sort_order')->nullable();
            $table->boolean('required')->default(true);
            $table->string('status');
            $table->index('tenant_id');
            $table->index('status');
            $table->index('course_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_components');
    }
};
