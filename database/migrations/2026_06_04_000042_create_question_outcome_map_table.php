<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_outcome_map', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('outcome_id');
            $table->decimal('weight', 8, 2)->default(1);
            $table->unique(['question_id', 'outcome_id']);
            $table->index(['tenant_id', 'outcome_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_outcome_map');
    }
};
