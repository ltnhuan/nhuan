<?php

namespace App\Services;

use App\Jobs\ProcessEnrollmentImport;
use App\Models\ClassSection;
use App\Models\EnrollmentImportJob;
use App\Models\LmsUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class EnrollmentImportService
{
    public function __construct(private readonly EnrollmentService $enrollments)
    {
    }

    public function createFromUpload(int $tenantId, UploadedFile $file, string $format, int $createdBy = null, bool $queue = true): EnrollmentImportJob
    {
        $path = $file->store("enrollment-imports/{$tenantId}", 'local');
        $job = EnrollmentImportJob::query()->create([
            'tenant_id' => $tenantId,
            'source' => 'bulk',
            'format' => $format,
            'status' => 'pending',
            'file_path' => $path,
            'created_by' => $createdBy,
        ]);

        if ($queue) {
            ProcessEnrollmentImport::dispatch($job->id);
            return $job;
        }

        return $this->process($job);
    }

    public function createFromApiRows(int $tenantId, array $rows, int $createdBy = null, bool $queue = false): EnrollmentImportJob
    {
        $job = EnrollmentImportJob::query()->create([
            'tenant_id' => $tenantId,
            'source' => 'api',
            'format' => 'api',
            'status' => 'pending',
            'payload' => ['rows' => $rows],
            'total_rows' => count($rows),
            'created_by' => $createdBy,
        ]);

        if ($queue) {
            ProcessEnrollmentImport::dispatch($job->id);
            return $job;
        }

        return $this->process($job);
    }

    public function process(EnrollmentImportJob $job): EnrollmentImportJob
    {
        $job->forceFill(['status' => 'processing', 'started_at' => now()])->save();

        try {
            $rows = $this->rows($job);
            $normalized = array_map(fn (array $row) => $this->normalizeRow($job->tenant_id, $row), $rows);
            $result = $this->enrollments->bulkEnroll($job->tenant_id, $normalized, $job->created_by, $job->source === 'api' ? 'api' : 'bulk');

            $job->forceFill([
                'status' => $result['failed'] > 0 ? 'completed_with_errors' : 'completed',
                'total_rows' => $result['total'],
                'success_rows' => $result['success'],
                'failed_rows' => $result['failed'],
                'errors' => $result['errors'],
                'completed_at' => now(),
            ])->save();
        } catch (\Throwable $exception) {
            $job->forceFill([
                'status' => 'failed',
                'failed_rows' => max(1, $job->total_rows),
                'errors' => [['message' => $exception->getMessage()]],
                'completed_at' => now(),
            ])->save();
        }

        return $job->fresh();
    }

    private function rows(EnrollmentImportJob $job): array
    {
        if ($job->format === 'api') {
            return $job->payload['rows'] ?? [];
        }

        if (in_array($job->format, ['xlsx', 'excel'], true)) {
            throw new \RuntimeException('Excel import job đã được ghi nhận; cần parser xlsx để xử lý nội dung file.');
        }

        $content = Storage::disk('local')->get($job->file_path);
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\n|\r/', $content))));
        if ($lines === []) {
            return [];
        }

        $headers = str_getcsv(array_shift($lines));
        return array_map(function (string $line) use ($headers) {
            $values = str_getcsv($line);
            return array_combine($headers, array_pad($values, count($headers), null)) ?: [];
        }, $lines);
    }

    private function normalizeRow(int $tenantId, array $row): array
    {
        $section = isset($row['class_section_id'])
            ? ClassSection::query()->where('tenant_id', $tenantId)->find($row['class_section_id'])
            : ClassSection::query()->where('tenant_id', $tenantId)->where('code', $row['section_code'] ?? $row['class_section_code'] ?? null)->first();

        $user = isset($row['user_id'])
            ? LmsUser::query()->where('tenant_id', $tenantId)->find($row['user_id'])
            : LmsUser::query()->where('tenant_id', $tenantId)->where('code', $row['user_code'] ?? null)->first();

        return [
            'course_id' => $row['course_id'] ?? $section?->course_id,
            'class_section_id' => $section?->id ?? ($row['class_section_id'] ?? null),
            'cohort_id' => $row['cohort_id'] ?? $section?->cohort_id,
            'cohort_group_id' => $row['cohort_group_id'] ?? $section?->cohort_group_id,
            'user_id' => $user?->id ?? ($row['user_id'] ?? null),
            'source' => $row['source'] ?? 'bulk',
            'sis_enrollment_id' => $row['sis_enrollment_id'] ?? null,
            'status' => $row['status'] ?? 'active',
            'expires_at' => $row['expires_at'] ?? null,
            'metadata' => ['raw' => $row],
        ];
    }
}
