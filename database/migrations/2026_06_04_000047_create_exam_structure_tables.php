<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('exam_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('question_count')->default(0);
            $table->decimal('score', 8, 2)->default(0);
            $table->jsonb('config')->nullable();
            $table->index(['tenant_id', 'exam_id']);
            $table->timestamps();
        });

        Schema::create('exam_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('exam_id');
            $table->unsignedBigInteger('section_id')->nullable();
            $table->unsignedBigInteger('question_id');
            $table->decimal('score', 8, 2)->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('required')->default(true);
            $table->jsonb('metadata')->nullable();
            $table->index(['tenant_id', 'exam_id', 'section_id']);
            $table->index('question_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_questions');
        Schema::dropIfExists('exam_sections');
    }
};
