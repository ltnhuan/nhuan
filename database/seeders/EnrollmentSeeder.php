<?php

namespace Database\Seeders;

use App\Models\ClassSection;
use App\Models\Cohort;
use App\Models\CohortGroup;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LmsUser;
use App\Models\TeacherAssignment;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class EnrollmentSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('code', 'VABIS')->firstOrFail();
        $students = LmsUser::query()->where('tenant_id', $tenant->id)->where('user_type', 'student')->pluck('id')->values();
        $teachers = LmsUser::query()->where('tenant_id', $tenant->id)->whereIn('user_type', ['teacher', 'staff'])->limit(40)->pluck('id')->values();
        $courses = Course::query()->where('tenant_id', $tenant->id)->limit(10)->get();

        $cohort = Cohort::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'K2026-DEMO'],
            ['name' => 'Khóa tuyển sinh 2026 Demo', 'type' => 'academic', 'status' => 'active', 'metadata' => ['seed' => true]]
        );
        $group = CohortGroup::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'cohort_id' => $cohort->id, 'code' => 'LG-2026-A'],
            ['name' => 'Learning Group 2026 A', 'group_type' => 'learning_group', 'capacity' => 5000, 'status' => 'active', 'metadata' => ['seed' => true]]
        );

        $sections = collect();
        foreach ($courses as $courseIndex => $course) {
            for ($i = 1; $i <= 10; $i++) {
                $section = ClassSection::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'code' => $course->code.'-SEC'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)],
                    ['course_id' => $course->id, 'cohort_id' => $cohort->id, 'cohort_group_id' => $group->id, 'sis_section_id' => 'SIS-'.$course->code.'-'.$i, 'name' => $course->title.' - Lớp học phần '.$i, 'section_type' => 'class_section', 'delivery_mode' => $i % 2 === 0 ? 'online' : 'blended', 'status' => 'active', 'capacity' => 600, 'metadata' => ['seed' => true]]
                );
                $sections->push($section);

                foreach (['main_teacher', 'assistant_teacher', 'advisor', 'evaluator'] as $roleIndex => $role) {
                    if ($teachers->isNotEmpty()) {
                        TeacherAssignment::query()->updateOrCreate(
                            ['tenant_id' => $tenant->id, 'class_section_id' => $section->id, 'user_id' => $teachers[($courseIndex + $i + $roleIndex) % $teachers->count()], 'role' => $role],
                            ['course_id' => $course->id, 'status' => 'active', 'assigned_at' => now(), 'metadata' => ['seed' => true]]
                        );
                    }
                }
            }
        }

        if ($students->isEmpty() || $sections->isEmpty()) {
            return;
        }

        $rows = [];
        for ($i = 0; $i < 50000; $i++) {
            $section = $sections[$i % $sections->count()];
            $studentId = $students[(int) floor($i / $sections->count()) % $students->count()];
            $status = match ($i % 20) {
                0 => 'pending',
                1 => 'suspended',
                2 => 'completed',
                3 => 'withdrawn',
                4 => 'expired',
                default => 'active',
            };
            $rows[] = [
                'tenant_id' => $tenant->id,
                'course_id' => $section->course_id,
                'class_section_id' => $section->id,
                'cohort_id' => $cohort->id,
                'cohort_group_id' => $group->id,
                'user_id' => $studentId,
                'source' => $i % 3 === 0 ? 'sis' : 'bulk',
                'sis_enrollment_id' => 'SIS-ENR-'.$i,
                'status' => $status,
                'completion_percent' => $status === 'completed' ? 100 : ($i % 100),
                'risk_score' => $i % 100,
                'enrolled_at' => now(),
                'activated_at' => $status === 'active' ? now() : null,
                'completed_at' => $status === 'completed' ? now() : null,
                'withdrawn_at' => $status === 'withdrawn' ? now() : null,
                'expires_at' => $status === 'expired' ? now() : now()->addMonths(6),
                'metadata' => json_encode(['seed' => true]),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($rows) === 1000) {
                Enrollment::query()->upsert($rows, ['tenant_id', 'class_section_id', 'user_id'], ['source', 'sis_enrollment_id', 'status', 'completion_percent', 'risk_score', 'updated_at']);
                $rows = [];
            }
        }

        if ($rows !== []) {
            Enrollment::query()->upsert($rows, ['tenant_id', 'class_section_id', 'user_id'], ['source', 'sis_enrollment_id', 'status', 'completion_percent', 'risk_score', 'updated_at']);
        }
    }
}
