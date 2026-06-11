<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_banks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('academic_unit_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('visibility')->default('private');
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->jsonb('settings')->nullable();
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'academic_unit_id', 'course_id']);
            $table->index(['visibility', 'status']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_banks');
    }
};
