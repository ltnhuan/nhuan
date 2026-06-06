<?php

namespace App\Services;

use App\Contracts\SISAdapterContract;
use App\Models\IntegrationSystem;

class MockSISAdapter implements SISAdapterContract
{
    public function pullStudents(IntegrationSystem $system): array { return $this->people('student', 25); }
    public function pullTeachers(IntegrationSystem $system): array { return $this->people('teacher', 8); }
    public function pullClasses(IntegrationSystem $system): array { return array_map(fn ($i) => ['external_id' => 'CLS-'.$i, 'code' => 'C'.str_pad((string)$i, 3, '0', STR_PAD_LEFT), 'name' => 'Lớp SIS '.$i], range(1, 10)); }
    public function pullCourseSections(IntegrationSystem $system): array { return array_map(fn ($i) => ['external_id' => 'SEC-'.$i, 'code' => 'SEC'.str_pad((string)$i, 3, '0', STR_PAD_LEFT), 'name' => 'Môn học SIS '.$i], range(1, 10)); }
    public function pullEnrollments(IntegrationSystem $system): array { return array_map(fn ($i) => ['external_id' => 'ENR-'.$i, 'student_external_id' => 'STU-'.$i, 'class_external_id' => 'CLS-'.(($i % 10) + 1)], range(1, 25)); }
    public function pushGrades(IntegrationSystem $system, array $grades): array { return ['accepted' => count($grades), 'provider' => 'mock']; }
    public function pushAttendance(IntegrationSystem $system, array $attendance): array { return ['accepted' => count($attendance), 'provider' => 'mock']; }
    public function pushProgress(IntegrationSystem $system, array $progress): array { return ['accepted' => count($progress), 'provider' => 'mock']; }
    public function healthCheck(IntegrationSystem $system): array { return ['ok' => $system->status === 'active', 'latency_ms' => 12, 'system' => $system->code]; }
    private function people(string $type, int $count): array { return array_map(fn ($i) => ['external_id' => strtoupper(substr($type,0,3)).'-'.$i, 'code' => strtoupper(substr($type,0,3)).str_pad((string)$i, 5, '0', STR_PAD_LEFT), 'email' => $type.$i.'@sis.example.test', 'full_name' => ucfirst($type).' SIS '.$i, 'status' => 'active'], range(1, $count)); }
}
