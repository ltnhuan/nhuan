<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_completions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('section_id')->nullable();
            $table->unsignedBigInteger('component_id')->nullable();
            $table->string('completion_type')->nullable();
            $table->string('status');
            $table->decimal('progress_percent', 8, 2)->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->string('source')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->index('tenant_id');
            $table->index('status');
            $table->index('course_id');
            $table->index('component_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_completions');
    }
};
