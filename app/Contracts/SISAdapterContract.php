<?php

namespace App\Contracts;

use App\Models\IntegrationSystem;

interface SISAdapterContract
{
    public function pullStudents(IntegrationSystem $system): array;
    public function pullTeachers(IntegrationSystem $system): array;
    public function pullClasses(IntegrationSystem $system): array;
    public function pullCourseSections(IntegrationSystem $system): array;
    public function pullEnrollments(IntegrationSystem $system): array;
    public function pushGrades(IntegrationSystem $system, array $grades): array;
    public function pushAttendance(IntegrationSystem $system, array $attendance): array;
    public function pushProgress(IntegrationSystem $system, array $progress): array;
    public function healthCheck(IntegrationSystem $system): array;
}
