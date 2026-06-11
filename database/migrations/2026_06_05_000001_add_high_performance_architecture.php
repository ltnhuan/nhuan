<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addPerformanceIndexes();
        $this->createMonitoringTables();
        $this->createPartitionRegistry();
        $this->enablePostgresAppendOnlyGuards();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach ($this->appendOnlyTables() as $table) {
                DB::statement("DROP TRIGGER IF EXISTS {$table}_append_only_guard ON {$table}");
            }
            DB::statement('DROP FUNCTION IF EXISTS eralms_prevent_event_mutation()');
        }

        Schema::dropIfExists('storage_usage_snapshots');
        Schema::dropIfExists('memory_warnings');
        Schema::dropIfExists('queue_latency_snapshots');
        Schema::dropIfExists('api_latency_logs');
        Schema::dropIfExists('event_partition_registry');
    }

    private function addPerformanceIndexes(): void
    {
        $indexes = [
            'learning_progress_events' => [
                ['columns' => ['tenant_id', 'course_id', 'status'], 'name' => 'lpe_tenant_course_status_idx'],
                ['columns' => ['tenant_id', 'user_id', 'status'], 'name' => 'lpe_tenant_user_status_idx'],
                ['columns' => ['tenant_id', 'course_id', 'created_at'], 'name' => 'lpe_tenant_course_created_idx'],
                ['columns' => ['tenant_id', 'user_id', 'course_id', 'created_at'], 'name' => 'lpe_user_course_created_idx'],
            ],
            'video_watch_events' => [
                ['columns' => ['tenant_id', 'course_id', 'created_at'], 'name' => 'vwe_tenant_course_created_idx'],
                ['columns' => ['tenant_id', 'user_id', 'video_asset_id', 'created_at'], 'name' => 'vwe_user_video_created_idx'],
                ['columns' => ['tenant_id', 'event_type', 'created_at'], 'name' => 'vwe_event_type_created_idx'],
            ],
            'exam_attempt_events' => [
                ['columns' => ['tenant_id', 'user_id', 'created_at'], 'name' => 'eae_tenant_user_created_idx'],
                ['columns' => ['tenant_id', 'event_type', 'created_at'], 'name' => 'eae_event_type_created_idx'],
            ],
            'audit_logs' => [
                ['columns' => ['tenant_id', 'module', 'created_at'], 'name' => 'audit_module_created_idx'],
                ['columns' => ['tenant_id', 'actor_id', 'created_at'], 'name' => 'audit_actor_created_idx'],
                ['columns' => ['tenant_id', 'entity_type', 'entity_id'], 'name' => 'audit_entity_idx'],
            ],
            'integration_events' => [
                ['columns' => ['tenant_id', 'status', 'created_at'], 'name' => 'integration_status_created_idx'],
                ['columns' => ['tenant_id', 'entity_type', 'entity_id'], 'name' => 'integration_entity_idx'],
            ],
            'user_course_progress' => [
                ['columns' => ['tenant_id', 'course_id', 'status', 'updated_at'], 'name' => 'ucp_course_status_updated_idx'],
                ['columns' => ['tenant_id', 'user_id', 'status'], 'name' => 'ucp_user_status_idx'],
            ],
            'video_progress_summaries' => [
                ['columns' => ['tenant_id', 'course_id', 'is_completed'], 'name' => 'vps_course_completed_idx'],
                ['columns' => ['tenant_id', 'user_id', 'last_watched_at'], 'name' => 'vps_user_last_watch_idx'],
            ],
            'grade_summaries' => [
                ['columns' => ['tenant_id', 'user_id', 'status'], 'name' => 'grade_summary_user_status_idx'],
                ['columns' => ['tenant_id', 'gradebook_id', 'pass_status'], 'name' => 'grade_summary_pass_idx'],
            ],
            'learner_eligibility_summaries' => [
                ['columns' => ['tenant_id', 'user_id', 'course_id'], 'name' => 'eligibility_user_course_idx'],
                ['columns' => ['tenant_id', 'course_id', 'class_id', 'eligible_for_exam'], 'name' => 'eligibility_class_exam_idx'],
            ],
            'analytics_snapshots' => [
                ['columns' => ['tenant_id', 'scope_type', 'scope_id', 'period_end'], 'name' => 'analytics_scope_period_end_idx'],
            ],
        ];

        foreach ($indexes as $table => $tableIndexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $tableIndexes) {
                foreach ($tableIndexes as $index) {
                    $columns = array_filter($index['columns'], fn ($column) => Schema::hasColumn($table, $column));

                    if (count($columns) > 1) {
                        $blueprint->index(array_values($columns), $index['name']);
                    }
                }
            });
        }
    }

    private function createMonitoringTables(): void
    {
        Schema::create('api_latency_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('method', 12);
            $table->string('path');
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('duration_ms');
            $table->decimal('memory_mb', 10, 2)->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['tenant_id', 'path', 'created_at']);
            $table->index(['duration_ms', 'created_at']);
        });

        Schema::create('queue_latency_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('connection')->default('redis');
            $table->string('queue');
            $table->unsignedInteger('waiting_jobs')->default(0);
            $table->unsignedInteger('oldest_wait_seconds')->default(0);
            $table->timestamp('captured_at')->nullable();
            $table->index(['connection', 'queue', 'captured_at']);
        });

        Schema::create('memory_warnings', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('api');
            $table->decimal('memory_mb', 10, 2);
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['source', 'created_at']);
        });

        Schema::create('storage_usage_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('disk');
            $table->unsignedBigInteger('used_bytes')->default(0);
            $table->unsignedBigInteger('file_count')->default(0);
            $table->timestamp('captured_at')->nullable();
            $table->index(['disk', 'captured_at']);
        });
    }

    private function createPartitionRegistry(): void
    {
        Schema::create('event_partition_registry', function (Blueprint $table) {
            $table->id();
            $table->string('table_name');
            $table->string('strategy')->default('monthly_range_created_at');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('partition_name');
            $table->string('status')->default('planned');
            $table->timestamp('created_at')->nullable();
            $table->unique(['table_name', 'partition_name']);
        });

        $start = now()->startOfMonth();
        $rows = [];

        foreach ($this->appendOnlyTables() as $table) {
            for ($i = 0; $i < 6; $i++) {
                $periodStart = $start->copy()->addMonths($i);
                $periodEnd = $periodStart->copy()->addMonth();
                $rows[] = [
                    'table_name' => $table,
                    'strategy' => 'monthly_range_created_at',
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'partition_name' => $table.'_'.$periodStart->format('Ym'),
                    'status' => DB::getDriverName() === 'pgsql' ? 'ready_for_online_conversion' : 'planned',
                    'created_at' => now(),
                ];
            }
        }

        DB::table('event_partition_registry')->insert($rows);
    }

    private function enablePostgresAppendOnlyGuards(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION eralms_prevent_event_mutation()
RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'EraLMS event logs are append-only';
END;
$$ LANGUAGE plpgsql;
SQL);

        foreach ($this->appendOnlyTables() as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::statement("DROP TRIGGER IF EXISTS {$table}_append_only_guard ON {$table}");
            DB::statement("CREATE TRIGGER {$table}_append_only_guard BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION eralms_prevent_event_mutation()");
        }
    }

    private function appendOnlyTables(): array
    {
        return [
            'learning_progress_events',
            'video_watch_events',
            'exam_attempt_events',
            'audit_logs',
            'integration_events',
        ];
    }
};
