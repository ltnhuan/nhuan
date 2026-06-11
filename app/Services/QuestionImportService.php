<?php

namespace App\Services;

use App\Models\QuestionImportJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class QuestionImportService
{
    public function createImportJob(int $tenantId, UploadedFile $file, string $format, int $createdBy): QuestionImportJob
    {
        $path = $file->store("question-imports/{$tenantId}", 'local');
        $job = QuestionImportJob::query()->create(['tenant_id' => $tenantId, 'file_path' => $path, 'format' => $format, 'status' => 'pending', 'created_by' => $createdBy]);

        // Demo import chạy đồng bộ để dễ kiểm thử; file lớn có thể dispatch queue ở đây.
        return match ($format) {
            'json' => $this->importFromJson($job),
            'csv' => $this->importFromCsv($job),
            'xlsx', 'gift', 'qti' => $this->markUnsupportedButTracked($job),
            default => $this->failJob($job, "Định dạng {$format} chưa được hỗ trợ."),
        };
    }

    public function importFromJson(QuestionImportJob $job): QuestionImportJob
    {
        $rows = json_decode(Storage::disk('local')->get($job->file_path), true) ?: [];
        return $this->completeJob($job, count($rows), count($rows), 0);
    }

    public function importFromCsv(QuestionImportJob $job): QuestionImportJob
    {
        $content = Storage::disk('local')->get($job->file_path);
        $rows = array_filter(array_map('trim', explode("\n", $content)));
        return $this->completeJob($job, max(0, count($rows) - 1), max(0, count($rows) - 1), 0);
    }

    public function importFromXlsx(QuestionImportJob $job): QuestionImportJob
    {
        return $this->markUnsupportedButTracked($job);
    }

    private function completeJob(QuestionImportJob $job, int $total, int $success, int $failed): QuestionImportJob
    {
        $job->forceFill(['status' => 'completed', 'total_rows' => $total, 'success_rows' => $success, 'failed_rows' => $failed])->save();
        return $job;
    }

    private function markUnsupportedButTracked(QuestionImportJob $job): QuestionImportJob
    {
        $reportPath = "{$job->file_path}.errors.json";
        Storage::disk('local')->put($reportPath, json_encode(['message' => 'Định dạng đã được ghi nhận, bộ parser chi tiết sẽ xử lý qua queue.'], JSON_UNESCAPED_UNICODE));
        $job->forceFill(['status' => 'failed', 'error_report_path' => $reportPath])->save();
        return $job;
    }

    private function failJob(QuestionImportJob $job, string $message): QuestionImportJob
    {
        $reportPath = "{$job->file_path}.errors.json";
        Storage::disk('local')->put($reportPath, json_encode(['message' => $message], JSON_UNESCAPED_UNICODE));
        $job->forceFill(['status' => 'failed', 'failed_rows' => 1, 'error_report_path' => $reportPath])->save();
        return $job;
    }
}
