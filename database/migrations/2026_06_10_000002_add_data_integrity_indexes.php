<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->deduplicateCode('courses');
        $this->deduplicateCode('lms_users');

        Schema::table('courses', function (Blueprint $table) {
            $table->unique(['tenant_id', 'code'], 'courses_tenant_code_unique_integrity');
        });

        Schema::table('lms_users', function (Blueprint $table) {
            $table->unique(['tenant_id', 'code'], 'lms_users_tenant_code_unique_integrity');
            $table->index(['tenant_id', 'user_type', 'status'], 'lms_users_tenant_type_status_idx');
        });

        Schema::table('content_versions', function (Blueprint $table) {
            $table->index(['tenant_id', 'content_item_id', 'version'], 'content_versions_item_version_idx');
        });

        Schema::table('certificate_issues', function (Blueprint $table) {
            $table->index(['tenant_id', 'course_id', 'user_id', 'status'], 'cert_issues_course_user_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_issues', function (Blueprint $table) {
            $table->dropIndex('cert_issues_course_user_status_idx');
        });

        Schema::table('content_versions', function (Blueprint $table) {
            $table->dropIndex('content_versions_item_version_idx');
        });

        Schema::table('lms_users', function (Blueprint $table) {
            $table->dropIndex('lms_users_tenant_type_status_idx');
            $table->dropUnique('lms_users_tenant_code_unique_integrity');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropUnique('courses_tenant_code_unique_integrity');
        });
    }

    private function deduplicateCode(string $table): void
    {
        $groups = DB::table($table)
            ->select('tenant_id', 'code', DB::raw('count(*) as total'))
            ->whereNotNull('code')
            ->groupBy('tenant_id', 'code')
            ->having('total', '>', 1)
            ->get();

        foreach ($groups as $group) {
            $ids = DB::table($table)
                ->where('tenant_id', $group->tenant_id)
                ->where('code', $group->code)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            foreach (array_slice($ids, 1) as $id) {
                DB::table($table)
                    ->where('id', $id)
                    ->update(['code' => substr((string) $group->code.'-'.$id, 0, 255)]);
            }
        }
    }
};
