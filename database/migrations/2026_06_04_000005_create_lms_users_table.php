<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sis_user_id')->nullable();
            $table->string('code');
            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('user_type')->nullable();
            $table->string('status');
            $table->jsonb('metadata')->nullable();
            $table->index('tenant_id');
            $table->index('status');
            $table->index('code');
            $table->index('email');
            $table->index('user_type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_users');
    }
};
