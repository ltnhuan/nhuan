<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('question_bank_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->index(['tenant_id', 'question_bank_id', 'parent_id']);
            $table->unique(['question_bank_id', 'code']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_categories');
    }
};
