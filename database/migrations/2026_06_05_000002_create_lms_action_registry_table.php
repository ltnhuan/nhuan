<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_action_registry', function (Blueprint $table) {
            $table->id();
            $table->string('module');
            $table->string('action_key');
            $table->string('label');
            $table->string('route_name');
            $table->string('http_method', 12)->default('POST');
            $table->string('permission_key')->nullable();
            $table->boolean('confirm_required')->default(false);
            $table->text('confirm_message')->nullable();
            $table->string('success_message')->default('Thao tác thành công');
            $table->string('failure_message')->default('Không thể thực hiện thao tác');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['module', 'action_key']);
            $table->index(['module', 'is_active', 'sort_order']);
            $table->index('permission_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_action_registry');
    }
};
