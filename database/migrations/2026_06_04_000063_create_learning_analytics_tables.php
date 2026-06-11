<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_metrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('class_section_id')->nullable();
            $table->date('metric_date');
            $table->unsignedInteger('login_frequency')->default(0);
            $table->unsignedInteger('study_time_minutes')->default(0);
            $table->decimal('video_completion', 5, 2)->default(0);
            $table->decimal('assignment_completion', 5, 2)->default(0);
            $table->decimal('quiz_score', 5, 2)->default(0);
            $table->decimal('attendance', 5, 2)->default(0);
            $table->unsignedInteger('forum_activity')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'course_id', 'metric_date']);
            $table->index(['tenant_id', 'metric_date']);
            $table->index(['tenant_id', 'course_id', 'metric_date']);
            $table->index(['tenant_id', 'user_id']);
        });

        Schema::create('learner_risk_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->decimal('risk_score', 5, 2)->default(0);
            $table->string('risk_level')->default('low');
            $table->jsonb('risk_factors')->nullable();
            $table->jsonb('recommendations')->nullable();
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'course_id']);
            $table->index(['tenant_id', 'risk_level']);
            $table->index(['tenant_id', 'risk_score']);
        });

        Schema::create('engagement_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->date('score_date');
            $table->decimal('engagement_score', 5, 2)->default(0);
            $table->decimal('login_score', 5, 2)->default(0);
            $table->decimal('study_score', 5, 2)->default(0);
            $table->decimal('content_score', 5, 2)->default(0);
            $table->decimal('social_score', 5, 2)->default(0);
            $table->jsonb('signals')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'course_id', 'score_date']);
            $table->index(['tenant_id', 'score_date']);
        });

        Schema::create('outcome_achievement', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('learning_outcome_id')->nullable();
            $table->date('achievement_date');
            $table->decimal('achievement_percent', 5, 2)->default(0);
            $table->string('achievement_level')->default('developing');
            $table->jsonb('evidence')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'course_id', 'achievement_date']);
            $table->index(['tenant_id', 'learning_outcome_id']);
        });

        Schema::create('analytics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('scope_type')->default('tenant');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('period_type');
            $table->date('period_start');
            $table->date('period_end');
            $table->jsonb('metrics');
            $table->timestamps();

            $table->unique(['tenant_id', 'scope_type', 'scope_id', 'period_type', 'period_start']);
            $table->index(['tenant_id', 'period_type', 'period_start']);
        });

        Schema::create('risk_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('risk_profile_id')->nullable();
            $table->string('alert_type');
            $table->string('severity');
            $table->string('status')->default('open');
            $table->text('message');
            $table->jsonb('recommended_actions')->nullable();
            $table->timestamp('triggered_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'severity']);
            $table->index(['tenant_id', 'alert_type']);
            $table->index(['tenant_id', 'user_id']);
        });

        foreach (['daily', 'weekly', 'monthly'] as $period) {
            Schema::create("analytics_{$period}_summaries", function (Blueprint $table) use ($period) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('scope_type')->default('tenant');
                $table->unsignedBigInteger('scope_id')->nullable();
                $table->date('period_start');
                $table->date('period_end');
                $table->unsignedInteger('learner_count')->default(0);
                $table->decimal('avg_progress', 5, 2)->default(0);
                $table->decimal('avg_grade', 5, 2)->default(0);
                $table->decimal('avg_engagement', 5, 2)->default(0);
                $table->decimal('avg_risk_score', 5, 2)->default(0);
                $table->unsignedInteger('high_risk_count')->default(0);
                $table->unsignedInteger('critical_risk_count')->default(0);
                $table->decimal('completion_rate', 5, 2)->default(0);
                $table->jsonb('chart_data')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'scope_type', 'scope_id', 'period_start'], "analytics_{$period}_scope_unique");
                $table->index(['tenant_id', 'period_start'], "analytics_{$period}_period_index");
            });
        }
    }

    public function down(): void
    {
        foreach (['monthly', 'weekly', 'daily'] as $period) {
            Schema::dropIfExists("analytics_{$period}_summaries");
        }
        Schema::dropIfExists('risk_alerts');
        Schema::dropIfExists('analytics_snapshots');
        Schema::dropIfExists('outcome_achievement');
        Schema::dropIfExists('engagement_scores');
        Schema::dropIfExists('learner_risk_profiles');
        Schema::dropIfExists('learning_metrics');
    }
};
