<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('domain')->nullable();
            $table->string('status');
            $table->string('logo_url')->nullable();
            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();
            $table->string('locale')->nullable();
            $table->string('timezone')->nullable();
            $table->jsonb('settings')->nullable();
            $table->unique('code');
            $table->unique('domain');
            $table->index('status');
            $table->index('code');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
