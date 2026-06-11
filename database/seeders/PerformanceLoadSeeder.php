<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PerformanceLoadSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->firstOrCreate(
            ['code' => 'PERF'],
            ['name' => 'EraLMS Performance Tenant', 'status' => 'active', 'locale' => 'vi', 'timezone' => 'Asia/Bangkok']
        );

        $now = now();

        if (DB::table('lms_users')->where('tenant_id', $tenant->id)->where('code', 'like', 'PERF-SV%')->count() < 5000) {
            collect(range(1, 5000))->chunk(1000)->each(function ($chunk) use ($tenant, $now) {
                DB::table('lms_users')->insert($chunk->map(fn ($i) => [
                    'tenant_id' => $tenant->id,
                    'code' => 'PERF-SV'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                    'full_name' => 'Performance Learner '.$i,
                    'email' => 'perf-sv'.$i.'@eralms.local',
                    'user_type' => 'student',
                    'status' => 'active',
                    'metadata' => json_encode(['load_seed' => true]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });
        }

        if (DB::table('courses')->where('tenant_id', $tenant->id)->where('code', 'like', 'PERF-C%')->count() < 200) {
            collect(range(1, 200))->chunk(200)->each(function ($chunk) use ($tenant, $now) {
                DB::table('courses')->insert($chunk->map(fn ($i) => [
                    'tenant_id' => $tenant->id,
                    'code' => 'PERF-C'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'title' => 'Performance Course '.$i,
                    'slug' => 'performance-course-'.$i,
                    'status' => 'published',
                    'visibility' => 'tenant',
                    'language' => 'vi',
                    'settings' => json_encode(['cdn_ready' => true]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });
        }

        $courseIds = DB::table('courses')->where('tenant_id', $tenant->id)->where('code', 'like', 'PERF-C%')->pluck('id')->values();

        if (DB::table('course_sections')->where('tenant_id', $tenant->id)->where('title', 'like', 'Performance Section%')->count() < 200) {
            $rows = $courseIds->map(fn ($courseId, $i) => [
                'tenant_id' => $tenant->id,
                'course_id' => $courseId,
                'type' => 'module',
                'title' => 'Performance Section '.($i + 1),
                'sort_order' => '1',
                'status' => 'published',
                'settings' => json_encode([]),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            DB::table('course_sections')->insert($rows);
        }

        $sectionByCourse = DB::table('course_sections')
            ->where('tenant_id', $tenant->id)
            ->where('title', 'like', 'Performance Section%')
            ->pluck('id', 'course_id');

        if (DB::table('course_components')->where('tenant_id', $tenant->id)->where('title', 'like', 'Performance Lesson%')->count() < 1000) {
            collect(range(1, 1000))->chunk(500)->each(function ($chunk) use ($tenant, $courseIds, $sectionByCourse, $now) {
                DB::table('course_components')->insert($chunk->map(function ($i) use ($tenant, $courseIds, $sectionByCourse, $now) {
                    $courseId = $courseIds[($i - 1) % max(1, $courseIds->count())];

                    return [
                        'tenant_id' => $tenant->id,
                        'course_id' => $courseId,
                        'section_id' => $sectionByCourse[$courseId] ?? null,
                        'component_type' => $i % 3 === 0 ? 'quiz' : 'lesson',
                        'title' => 'Performance Lesson '.$i,
                        'config' => json_encode(['lazy_load' => true, 'prefetch_next' => true]),
                        'sort_order' => (string) $i,
                        'required' => true,
                        'status' => 'published',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->all());
            });
        }

        $userIds = DB::table('lms_users')->where('tenant_id', $tenant->id)->where('code', 'like', 'PERF-SV%')->limit(5000)->pluck('id')->values();
        $componentIds = DB::table('course_components')->where('tenant_id', $tenant->id)->where('title', 'like', 'Performance Lesson%')->pluck('id', 'course_id');
        $courseIdsArray = $courseIds->all();

        if (DB::table('learning_progress_events')->where('tenant_id', $tenant->id)->where('metadata->load_seed', true)->count() < 100000) {
            collect(range(1, 100000))->chunk(1000)->each(function ($chunk) use ($tenant, $userIds, $courseIdsArray, $componentIds, $now) {
                DB::table('learning_progress_events')->insert($chunk->map(function ($i) use ($tenant, $userIds, $courseIdsArray, $componentIds, $now) {
                    $courseId = $courseIdsArray[($i - 1) % count($courseIdsArray)];

                    return [
                        'tenant_id' => $tenant->id,
                        'user_id' => $userIds[($i - 1) % max(1, $userIds->count())],
                        'course_id' => $courseId,
                        'component_id' => $componentIds[$courseId] ?? null,
                        'event_type' => $i % 5 === 0 ? 'component_completed' : 'lesson_progress',
                        'event_value' => $i % 100,
                        'metadata' => json_encode(['load_seed' => true, 'request_id' => (string) Str::uuid()]),
                        'created_at' => $now->copy()->subSeconds($i % 86400),
                        'updated_at' => $now,
                    ];
                })->all());
            });
        }
    }
}
