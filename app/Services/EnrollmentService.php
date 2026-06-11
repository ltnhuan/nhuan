<?php

namespace App\Services;

use App\Models\ClassSection;
use App\Models\CohortEnrollment;
use App\Models\Enrollment;
use App\Models\EnrollmentEvent;
use App\Models\LmsUser;
use App\Models\TeacherAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EnrollmentService
{
    public const STATUSES = ['pending', 'active', 'suspended', 'completed', 'withdrawn', 'expired'];
    public const SOURCES = ['manual', 'bulk', 'sis', 'self', 'invite', 'api'];
    public const TEACHER_ROLES = ['main_teacher', 'assistant_teacher', 'advisor', 'evaluator'];

    public function createSection(array $data): ClassSection
    {
        return ClassSection::query()->create([
            'tenant_id' => $data['tenant_id'],
            'course_id' => $data['course_id'],
            'cohort_id' => $data['cohort_id'] ?? null,
            'cohort_group_id' => $data['cohort_group_id'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'sis_section_id' => $data['sis_section_id'] ?? null,
            'code' => $data['code'],
            'name' => $data['name'],
            'section_type' => $data['section_type'] ?? 'class_section',
            'delivery_mode' => $data['delivery_mode'] ?? null,
            'status' => $data['status'] ?? 'active',
            'capacity' => $data['capacity'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'schedule' => $data['schedule'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    public function enroll(array $data): Enrollment
    {
        $section = ClassSection::query()->find($data['class_section_id'] ?? null);
        $source = $data['source'] ?? 'manual';
        $status = $data['status'] ?? ($source === 'invite' ? 'pending' : 'active');
        $this->assertStatus($status);

        $attributes = [
            'tenant_id' => $data['tenant_id'],
            'class_section_id' => $data['class_section_id'] ?? null,
            'user_id' => $data['user_id'],
        ];

        $values = [
            'course_id' => $data['course_id'] ?? $section?->course_id,
            'cohort_id' => $data['cohort_id'] ?? $section?->cohort_id,
            'cohort_group_id' => $data['cohort_group_id'] ?? $section?->cohort_group_id,
            'source' => $source,
            'sis_enrollment_id' => $data['sis_enrollment_id'] ?? null,
            'status' => $status,
            'completion_percent' => $data['completion_percent'] ?? 0,
            'risk_score' => $data['risk_score'] ?? 0,
            'invited_at' => $status === 'pending' && ($data['source'] ?? null) === 'invite' ? now() : ($data['invited_at'] ?? null),
            'enrolled_at' => $data['enrolled_at'] ?? now(),
            'activated_at' => $status === 'active' ? now() : null,
            'expires_at' => $data['expires_at'] ?? null,
            'created_by' => $data['created_by'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ];

        if (! $values['course_id']) {
            throw new InvalidArgumentException('course_id or class_section_id is required.');
        }

        return DB::transaction(function () use ($attributes, $values, $data) {
            $enrollment = Enrollment::query()->updateOrCreate($attributes, $values);
            $this->recordEvent($enrollment, 'enrolled', null, $enrollment->status, $data['created_by'] ?? null, ['source' => $enrollment->source]);
            $this->syncCohortEnrollment($enrollment);
            return $enrollment->fresh();
        });
    }

    public function bulkEnroll(int $tenantId, array $rows, int $actorId = null, string $source = 'bulk'): array
    {
        $success = 0;
        $failed = 0;
        $errors = [];

        foreach (array_chunk($rows, 500) as $chunk) {
            foreach ($chunk as $offset => $row) {
                try {
                    $this->enroll(array_replace($row, ['tenant_id' => $tenantId, 'created_by' => $actorId, 'source' => $row['source'] ?? $source]));
                    $success++;
                } catch (\Throwable $exception) {
                    $failed++;
                    if (count($errors) < 100) {
                        $errors[] = ['row' => $success + $failed + $offset, 'message' => $exception->getMessage(), 'data' => $row];
                    }
                }
            }
        }

        return ['total' => count($rows), 'success' => $success, 'failed' => $failed, 'errors' => $errors];
    }

    public function transition(Enrollment $enrollment, string $status, int $actorId = null, array $metadata = []): Enrollment
    {
        $this->assertStatus($status);
        $from = $enrollment->status;

        $timestampColumn = match ($status) {
            'active' => 'activated_at',
            'suspended' => 'suspended_at',
            'completed' => 'completed_at',
            'withdrawn' => 'withdrawn_at',
            default => null,
        };

        $updates = ['status' => $status];
        if ($timestampColumn) {
            $updates[$timestampColumn] = now();
        }

        $enrollment->forceFill($updates)->save();
        $this->recordEvent($enrollment, 'status_changed', $from, $status, $actorId, $metadata);

        return $enrollment->fresh();
    }

    public function bulkAction(int $tenantId, array $ids, string $action, int $actorId = null): array
    {
        $status = match ($action) {
            'activate' => 'active',
            'suspend' => 'suspended',
            'complete' => 'completed',
            'withdraw' => 'withdrawn',
            'expire' => 'expired',
            default => throw new InvalidArgumentException("Unsupported bulk action {$action}."),
        };

        $updated = 0;
        Enrollment::query()->where('tenant_id', $tenantId)->whereIn('id', $ids)->chunkById(500, function (Collection $enrollments) use ($status, $actorId, &$updated) {
            foreach ($enrollments as $enrollment) {
                $this->transition($enrollment, $status, $actorId, ['bulk' => true]);
                $updated++;
            }
        });

        return ['updated' => $updated, 'status' => $status];
    }

    public function assignTeacher(ClassSection $section, array $data): TeacherAssignment
    {
        $role = $data['role'] ?? 'main_teacher';
        if (! in_array($role, self::TEACHER_ROLES, true)) {
            throw new InvalidArgumentException("Unsupported teacher assignment role {$role}.");
        }

        return TeacherAssignment::query()->updateOrCreate([
            'tenant_id' => $section->tenant_id,
            'class_section_id' => $section->id,
            'user_id' => $data['user_id'],
            'role' => $role,
        ], [
            'course_id' => $section->course_id,
            'status' => $data['status'] ?? 'active',
            'assigned_at' => $data['assigned_at'] ?? now(),
            'ended_at' => $data['ended_at'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ]);
    }

    private function syncCohortEnrollment(Enrollment $enrollment): void
    {
        if (! $enrollment->cohort_id) {
            return;
        }

        CohortEnrollment::query()->updateOrCreate([
            'tenant_id' => $enrollment->tenant_id,
            'cohort_id' => $enrollment->cohort_id,
            'user_id' => $enrollment->user_id,
        ], [
            'cohort_group_id' => $enrollment->cohort_group_id,
            'source' => $enrollment->source,
            'status' => in_array($enrollment->status, ['withdrawn', 'expired'], true) ? 'inactive' : 'active',
            'joined_at' => now(),
            'metadata' => ['enrollment_id' => $enrollment->id],
        ]);
    }

    private function recordEvent(Enrollment $enrollment, string $type, ?string $from, ?string $to, ?int $actorId, array $metadata = []): void
    {
        EnrollmentEvent::query()->create([
            'tenant_id' => $enrollment->tenant_id,
            'enrollment_id' => $enrollment->id,
            'actor_id' => $actorId,
            'event_type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'metadata' => $metadata,
        ]);
    }

    private function assertStatus(string $status): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException("Unsupported enrollment status {$status}.");
        }
    }
}
