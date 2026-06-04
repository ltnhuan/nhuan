<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_course_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->string('status');
            $table->decimal('progress_percent', 8, 2)->nullable();
            $table->string('completed_components_count')->nullable();
            $table->string('total_components_count')->nullable();
            $table->string('completed_required_count')->nullable();
            $table->string('total_required_count')->nullable();
            $table->unsignedBigInteger('last_component_id')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('risk_level')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->index('tenant_id');
            $table->index('status');
            $table->index('course_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_course_progress');
    }
};
