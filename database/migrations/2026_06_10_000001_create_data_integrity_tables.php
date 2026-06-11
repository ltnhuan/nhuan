<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_integrity_checks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('module');
            $table->string('check_key');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('severity')->default('warning');
            $table->string('status')->default('passed');
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'check_key']);
            $table->index(['tenant_id', 'module', 'status']);
            $table->index(['tenant_id', 'severity', 'status']);
        });

        Schema::create('data_integrity_issues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('check_id');
            $table->string('module');
            $table->string('entity_type');
            $table->string('entity_id')->nullable();
            $table->string('issue_key');
            $table->text('message');
            $table->string('severity')->default('warning');
            $table->boolean('auto_fix_available')->default(false);
            $table->string('status')->default('open');
            $table->unsignedBigInteger('fixed_by')->nullable();
            $table->timestamp('fixed_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'issue_key', 'entity_type', 'entity_id'], 'integrity_issue_unique_entity');
            $table->index(['tenant_id', 'module', 'status']);
            $table->index(['tenant_id', 'severity', 'status']);
            $table->index(['check_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_integrity_issues');
        Schema::dropIfExists('data_integrity_checks');
    }
};
