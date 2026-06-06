<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_enrollments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('exam_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->string('status')->default('assigned');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->unique(['tenant_id', 'exam_id', 'user_id']);
            $table->index(['tenant_id', 'user_id', 'status']);
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('exam_enrollments'); }
};
