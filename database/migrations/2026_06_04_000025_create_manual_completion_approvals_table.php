<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_completion_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('component_id')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->string('status');
            $table->text('note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->index('tenant_id');
            $table->index('status');
            $table->index('course_id');
            $table->index('component_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_completion_approvals');
    }
};
