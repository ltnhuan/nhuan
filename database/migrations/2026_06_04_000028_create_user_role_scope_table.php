<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_role_scope', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('campus_id')->nullable();
            $table->unsignedBigInteger('academic_unit_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'campus_id', 'academic_unit_id']);
            $table->unique(['user_id', 'role_id', 'tenant_id', 'campus_id', 'academic_unit_id', 'course_id', 'class_id'], 'user_role_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_scope');
    }
};
