<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('component_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('provider')->default('manual');
            $table->string('meeting_url')->nullable();
            $table->string('external_meeting_id')->nullable();
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->string('status')->default('scheduled');
            $table->boolean('attendance_required')->default(true);
            $table->unsignedInteger('min_attendance_minutes')->nullable();
            $table->jsonb('settings')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->index(['tenant_id', 'course_id', 'class_id']);
            $table->index(['tenant_id', 'status', 'start_at']);
            $table->timestamps();
        });

        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('live_session_id')->nullable();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('class_id')->nullable();
            $table->string('title');
            $table->string('attendance_type')->default('qr');
            $table->timestamp('open_at');
            $table->timestamp('close_at');
            $table->string('qr_token')->nullable();
            $table->string('otp_code')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('locked_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->jsonb('settings')->nullable();
            $table->index(['tenant_id', 'course_id', 'class_id']);
            $table->index(['tenant_id', 'status', 'open_at']);
            $table->timestamps();
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('attendance_session_id');
            $table->unsignedBigInteger('live_session_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('status')->default('pending');
            $table->timestamp('checkin_at')->nullable();
            $table->timestamp('checkout_at')->nullable();
            $table->unsignedInteger('attended_minutes')->default(0);
            $table->string('source')->default('manual');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->unique(['tenant_id', 'attendance_session_id', 'user_id']);
            $table->index(['tenant_id', 'live_session_id', 'user_id']);
            $table->timestamps();
        });

        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('attendance_session_id')->nullable();
            $table->unsignedBigInteger('live_session_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('event_type');
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['tenant_id', 'attendance_session_id', 'user_id']);
            $table->index(['tenant_id', 'live_session_id', 'user_id']);
        });

        Schema::create('eligibility_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->string('rule_type');
            $table->string('title');
            $table->jsonb('config')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('created_by');
            $table->index(['tenant_id', 'course_id', 'class_id', 'status']);
            $table->timestamps();
        });

        Schema::create('learner_eligibility_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('class_id')->nullable();
            $table->decimal('attendance_percent', 8, 2)->default(0);
            $table->unsignedInteger('absent_count')->default(0);
            $table->unsignedInteger('late_count')->default(0);
            $table->boolean('eligible_for_exam')->default(false);
            $table->text('reason')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['tenant_id', 'user_id', 'course_id', 'class_id']);
            $table->index(['tenant_id', 'course_id', 'eligible_for_exam']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_eligibility_summaries');
        Schema::dropIfExists('eligibility_rules');
        Schema::dropIfExists('attendance_events');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('attendance_sessions');
        Schema::dropIfExists('live_sessions');
    }
};
