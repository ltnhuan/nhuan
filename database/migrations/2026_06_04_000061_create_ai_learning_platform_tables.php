<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('content_repository_item_id')->nullable();
            $table->string('source_type');
            $table->string('title');
            $table->string('mime_type')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('status')->default('pending');
            $table->jsonb('metadata')->nullable();
            $table->timestamp('ingested_at')->nullable();
            $table->index('tenant_id');
            $table->index('course_id');
            $table->index('source_type');
            $table->index('status');
            $table->timestamps();
        });

        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedInteger('chunk_index');
            $table->text('content');
            $table->unsignedInteger('token_count')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->index('tenant_id');
            $table->index('document_id');
            $table->index('course_id');
            $table->timestamps();
        });

        Schema::create('embeddings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('document_chunk_id');
            $table->string('provider')->default('local');
            $table->string('model')->default('hash-embedding-v1');
            $table->unsignedInteger('dimensions')->default(32);
            $table->jsonb('vector');
            $table->index('tenant_id');
            $table->index('document_chunk_id');
            $table->timestamps();
        });

        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('assistant_type')->default('tutor');
            $table->text('question');
            $table->longText('answer');
            $table->jsonb('citations')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->index('tenant_id');
            $table->index('course_id');
            $table->index('assistant_type');
            $table->timestamps();
        });

        Schema::create('ai_flashcards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->string('front');
            $table->text('back');
            $table->string('difficulty')->default('medium');
            $table->jsonb('metadata')->nullable();
            $table->index('tenant_id');
            $table->index('course_id');
            $table->index('document_id');
            $table->timestamps();
        });

        Schema::create('ai_generated_quizzes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->string('quiz_type')->default('mixed');
            $table->string('title');
            $table->jsonb('questions');
            $table->jsonb('metadata')->nullable();
            $table->index('tenant_id');
            $table->index('course_id');
            $table->index('document_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generated_quizzes');
        Schema::dropIfExists('ai_flashcards');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('embeddings');
        Schema::dropIfExists('document_chunks');
        Schema::dropIfExists('documents');
    }
};
