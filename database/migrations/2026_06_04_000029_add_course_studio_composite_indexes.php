<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->index(['tenant_id', 'status', 'course_type'], 'courses_tenant_status_type_idx');
            $table->index(['tenant_id', 'academic_unit_id', 'status'], 'courses_tenant_unit_status_idx');
        });

        Schema::table('course_sections', function (Blueprint $table) {
            $table->index(['tenant_id', 'course_id', 'parent_id', 'type'], 'sections_outline_idx');
            $table->index(['course_id', 'sort_order'], 'sections_course_sort_idx');
        });

        Schema::table('course_components', function (Blueprint $table) {
            $table->index(['tenant_id', 'course_id', 'section_id', 'sort_order'], 'components_outline_idx');
            $table->index(['component_type', 'status'], 'components_type_status_idx');
        });

        Schema::table('content_repository_items', function (Blueprint $table) {
            $table->index(['tenant_id', 'parent_id', 'status'], 'repo_tenant_parent_status_idx');
            $table->index(['tenant_id', 'academic_unit_id', 'visibility'], 'repo_tenant_unit_visibility_idx');
            $table->index(['tenant_id', 'checksum'], 'repo_tenant_checksum_idx');
        });
    }

    public function down(): void
    {
        Schema::table('content_repository_items', function (Blueprint $table) {
            $table->dropIndex('repo_tenant_checksum_idx');
            $table->dropIndex('repo_tenant_unit_visibility_idx');
            $table->dropIndex('repo_tenant_parent_status_idx');
        });

        Schema::table('course_components', function (Blueprint $table) {
            $table->dropIndex('components_type_status_idx');
            $table->dropIndex('components_outline_idx');
        });

        Schema::table('course_sections', function (Blueprint $table) {
            $table->dropIndex('sections_course_sort_idx');
            $table->dropIndex('sections_outline_idx');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex('courses_tenant_unit_status_idx');
            $table->dropIndex('courses_tenant_status_type_idx');
        });
    }
};
