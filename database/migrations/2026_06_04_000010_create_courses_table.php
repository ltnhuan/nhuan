<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('code');
            $table->string('title');
            $table->string('slug')->nullable();
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('academic_unit_id')->nullable();
            $table->string('level')->nullable();
            $table->string('course_type')->nullable();
            $table->string('status');
            $table->string('visibility')->nullable();
            $table->string('language')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->decimal('estimated_hours', 8, 2)->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->jsonb('settings')->nullable();
            $table->index('tenant_id');
            $table->index('status');
            $table->index('code');
            $table->index('academic_unit_id');
            $table->index('owner_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
