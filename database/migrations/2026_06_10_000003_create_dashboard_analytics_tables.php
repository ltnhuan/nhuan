<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->date('snapshot_date');
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->unsignedBigInteger('semester_id')->nullable();
            $table->unsignedBigInteger('campus_id')->nullable();
            $table->unsignedBigInteger('faculty_id')->nullable();
            $table->unsignedBigInteger('program_id')->nullable();
            $table->unsignedBigInteger('class_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('metric_key', 120);
            $table->decimal('metric_value', 16, 4);
            $table->string('metric_unit', 32)->nullable();
            $table->jsonb('dimension')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'snapshot_date', 'metric_key'], 'dashboard_metric_lookup_index');
            $table->index(['tenant_id', 'faculty_id', 'snapshot_date'], 'dashboard_metric_faculty_index');
            $table->index(['tenant_id', 'class_id', 'snapshot_date'], 'dashboard_metric_class_index');
            $table->index(['tenant_id', 'course_id', 'snapshot_date'], 'dashboard_metric_course_index');
            $table->index(['tenant_id', 'user_id', 'snapshot_date'], 'dashboard_metric_user_index');
        });

        Schema::create('dashboard_widget_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('dashboard_key', 80);
            $table->string('widget_key', 120);
            $table->string('title');
            $table->string('widget_type', 32);
            $table->string('data_source');
            $table->jsonb('config')->nullable();
            $table->string('permission_key')->nullable();
            $table->unsignedInteger('sort_order')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'dashboard_key', 'widget_key'], 'dashboard_widget_unique');
            $table->index(['tenant_id', 'dashboard_key', 'is_active'], 'dashboard_widget_active_index');
        });

        Schema::create('dashboard_user_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('dashboard_key', 80);
            $table->jsonb('layout')->nullable();
            $table->jsonb('filters')->nullable();
            $table->jsonb('pinned_widgets')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'dashboard_key'], 'dashboard_user_pref_unique');
        });

        Schema::create('analytics_forecasts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('forecast_key', 120);
            $table->string('scope_type', 32);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->date('forecast_date');
            $table->string('horizon', 24);
            $table->decimal('predicted_value', 16, 4);
            $table->decimal('confidence', 6, 4);
            $table->string('model_name', 120);
            $table->jsonb('features')->nullable();
            $table->text('explanation')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'forecast_key', 'scope_type', 'scope_id'], 'analytics_forecast_lookup_index');
            $table->index(['tenant_id', 'forecast_date'], 'analytics_forecast_date_index');
        });

        Schema::create('analytics_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('alert_type', 120);
            $table->string('severity', 24);
            $table->string('scope_type', 32);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('title');
            $table->text('message');
            $table->text('recommended_action')->nullable();
            $table->string('status', 32)->default('open');
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'severity'], 'analytics_alert_status_index');
            $table->index(['tenant_id', 'scope_type', 'scope_id'], 'analytics_alert_scope_index');
            $table->index(['tenant_id', 'alert_type'], 'analytics_alert_type_index');
        });

        Schema::create('analytics_benchmarks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('benchmark_key', 120);
            $table->string('scope_type', 32);
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->decimal('industry_value', 16, 4)->nullable();
            $table->decimal('internal_target', 16, 4)->nullable();
            $table->decimal('current_value', 16, 4);
            $table->string('status', 32);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'benchmark_key', 'scope_type', 'scope_id'], 'analytics_benchmark_unique');
            $table->index(['tenant_id', 'status'], 'analytics_benchmark_status_index');
        });

        foreach ($this->summaryTables() as $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($tableName) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->date('snapshot_date');
                $table->unsignedBigInteger('academic_year_id')->nullable();
                $table->unsignedBigInteger('semester_id')->nullable();
                $table->unsignedBigInteger('campus_id')->nullable();
                $table->unsignedBigInteger('faculty_id')->nullable();
                $table->unsignedBigInteger('program_id')->nullable();
                $table->unsignedBigInteger('class_id')->nullable();
                $table->unsignedBigInteger('course_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->jsonb('metrics');
                $table->jsonb('dimensions')->nullable();
                $table->string('data_quality', 24)->default('good');
                $table->timestamps();

                $prefix = substr($tableName, 0, 24);
                $table->index(['tenant_id', 'snapshot_date'], "{$prefix}_tenant_date_index");
                $table->index(['tenant_id', 'faculty_id', 'snapshot_date'], "{$prefix}_faculty_index");
                $table->index(['tenant_id', 'class_id', 'snapshot_date'], "{$prefix}_class_index");
                $table->index(['tenant_id', 'course_id', 'snapshot_date'], "{$prefix}_course_index");
                $table->index(['tenant_id', 'user_id', 'snapshot_date'], "{$prefix}_user_index");
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->summaryTables()) as $tableName) {
            Schema::dropIfExists($tableName);
        }

        Schema::dropIfExists('analytics_benchmarks');
        Schema::dropIfExists('analytics_alerts');
        Schema::dropIfExists('analytics_forecasts');
        Schema::dropIfExists('dashboard_user_preferences');
        Schema::dropIfExists('dashboard_widget_configs');
        Schema::dropIfExists('dashboard_metric_snapshots');
    }

    private function summaryTables(): array
    {
        return [
            'course_operation_snapshots',
            'learner_analytics_snapshots',
            'class_analytics_snapshots',
            'faculty_analytics_snapshots',
            'exam_analytics_snapshots',
            'grade_analytics_snapshots',
            'attendance_analytics_snapshots',
            'risk_analytics_snapshots',
            'sis_sync_analytics_snapshots',
            'ai_usage_snapshots',
        ];
    }
};
