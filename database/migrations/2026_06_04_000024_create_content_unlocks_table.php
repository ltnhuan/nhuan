<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_unlocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->boolean('is_unlocked')->default(false);
            $table->string('reason')->nullable();
            $table->timestamp('unlocked_at')->nullable();
            $table->text('locked_message')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->index(['tenant_id', 'user_id', 'course_id']);
            $table->index('course_id');
            $table->unique(['tenant_id', 'user_id', 'target_type', 'target_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_unlocks');
    }
};
