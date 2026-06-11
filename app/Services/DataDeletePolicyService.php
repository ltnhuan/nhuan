<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DataDeletePolicyService
{
    public function assertCanHardDelete(Model $model): void
    {
        $table = $model->getTable();

        match ($table) {
            'courses' => $this->guardCourse($model),
            'exams' => $this->guardExam($model),
            'assignments' => $this->guardAssignment($model),
            'gradebooks' => $this->guardGradebook($model),
            'certificate_issues' => $this->guardCertificateIssue($model),
            'attendance_sessions' => $this->guardAttendance($model),
            'integration_mappings' => $this->guardIntegrationMapping($model),
            default => null,
        };
    }

    public function archiveInstead(Model $model, string $status = 'archived'): Model
    {
        if (! Schema::hasColumn($model->getTable(), 'status')) {
            throw new \RuntimeException('Entity không hỗ trợ archive/inactive bằng status.');
        }

        $model->forceFill(['status' => $status])->save();

        return $model->fresh();
    }

    private function guardCourse(Model $course): void
    {
        if ($course->status === 'published') {
            throw new \RuntimeException('Không được xóa cứng khóa học đã published. Hãy archive.');
        }
    }

    private function guardExam(Model $exam): void
    {
        if ($this->exists('exam_attempts', 'exam_id', $exam->id)) {
            throw new \RuntimeException('Không được xóa cứng exam đã có attempt. Hãy archive.');
        }
    }

    private function guardAssignment(Model $assignment): void
    {
        if ($this->exists('assignment_submissions', 'assignment_id', $assignment->id)) {
            throw new \RuntimeException('Không được xóa cứng assignment đã có submission. Hãy archive.');
        }
    }

    private function guardGradebook(Model $gradebook): void
    {
        if ($gradebook->status === 'locked' || $gradebook->locked_at) {
            throw new \RuntimeException('Không được xóa cứng gradebook đã locked.');
        }
    }

    private function guardCertificateIssue(Model $issue): void
    {
        if ($issue->status === 'issued' || $issue->issued_at) {
            throw new \RuntimeException('Không được xóa cứng certificate đã issued. Hãy revoke.');
        }
    }

    private function guardAttendance(Model $session): void
    {
        if ($session->status === 'locked' || $session->locked_at) {
            throw new \RuntimeException('Không được xóa cứng attendance đã locked.');
        }
    }

    private function guardIntegrationMapping(Model $mapping): void
    {
        if ($mapping->mapping_status === 'synced' || ($mapping->metadata['synced_at'] ?? null)) {
            throw new \RuntimeException('Không được xóa cứng SIS mapping đã sync. Hãy inactive.');
        }
    }

    private function exists(string $table, string $column, mixed $value): bool
    {
        return Schema::hasTable($table) && DB::table($table)->where($column, $value)->exists();
    }
}
