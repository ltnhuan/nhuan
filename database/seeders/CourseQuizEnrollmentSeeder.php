<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\ExamEnrollment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CourseQuizEnrollmentSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('PRAGMA busy_timeout = 60000');

        Exam::query()
            ->whereIn('status', ['published', 'open'])
            ->orderBy('id')
            ->chunkById(100, function ($exams): void {
                foreach ($exams as $exam) {
                    $this->seedExam($exam);
                }
            });
    }

    private function seedExam(Exam $exam): void
    {
        $users = DB::table('enrollments')
            ->where('tenant_id', $exam->tenant_id)
            ->where('course_id', $exam->course_id)
            ->whereIn('status', ['active', 'completed'])
            ->orderBy('id')
            ->limit(100)
            ->get(['user_id', 'class_section_id'])
            ->mapWithKeys(fn ($row) => [(int) $row->user_id => $row->class_section_id])
            ->all();

        DB::table('lms_users')
            ->where('tenant_id', $exam->tenant_id)
            ->whereIn('email', [
                'admin.lms@vabis.edu.vn',
                'daotao.lms@vabis.edu.vn',
                'khoa.lms@vabis.edu.vn',
                'gv.lms@vabis.edu.vn',
                'sv.lms@vabis.edu.vn',
            ])
            ->pluck('id')
            ->each(function ($userId) use (&$users): void {
                $users[(int) $userId] = $users[(int) $userId] ?? null;
            });

        if ($users === []) {
            DB::table('lms_users')
                ->where('tenant_id', $exam->tenant_id)
                ->where('user_type', 'student')
                ->orderBy('id')
                ->limit(25)
                ->pluck('id')
                ->each(function ($userId) use (&$users): void {
                    $users[(int) $userId] = null;
                });
        }

        foreach ($users as $userId => $classId) {
            ExamEnrollment::query()->updateOrCreate([
                'tenant_id' => $exam->tenant_id,
                'exam_id' => $exam->id,
                'user_id' => (int) $userId,
            ], [
                'course_id' => $exam->course_id,
                'class_id' => $classId,
                'status' => 'available',
                'assigned_by' => $exam->created_by,
                'available_from' => now()->subDay(),
                'available_until' => now()->addMonths(6),
                'metadata' => [
                    'seeded' => true,
                    'source' => 'course_quiz_enrollment_seed',
                    'component_id' => $exam->component_id,
                ],
            ]);
        }
    }
}
