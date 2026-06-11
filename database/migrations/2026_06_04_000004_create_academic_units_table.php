<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('code');
            $table->string('name');
            $table->string('type')->nullable();
            $table->string('status');
            $table->index('tenant_id');
            $table->index('status');
            $table->index('code');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_units');
    }
};
