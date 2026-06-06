<?php

namespace App\Services;

use App\Contracts\SISAdapterContract;
use App\Models\IntegrationEvent;
use App\Models\IntegrationSystem;
use App\Models\SyncJob;

class SyncJobService
{
    public function __construct(private SISAdapterContract $adapter, private MappingService $mappings, private OutboundWebhookService $outbound) {}

    public function fullSyncUsers(IntegrationSystem $system, ?int $actorId = null): SyncJob
    {
        $job = $this->start($system, 'full_sync', 'user', $actorId);
        $items = array_merge($this->adapter->pullStudents($system), $this->adapter->pullTeachers($system));
        foreach ($items as $i => $item) $this->mappings->createMapping($system, str_contains($item['external_id'], 'TEA') ? 'teacher' : 'student', (string)($i + 1), $item['external_id'], $item['code'] ?? null, $item);
        return $this->finish($job, count($items), count($items), 0);
    }

    public function fullSyncClasses(IntegrationSystem $system, ?int $actorId = null): SyncJob
    {
        $job = $this->start($system, 'full_sync', 'class', $actorId);
        $items = $this->adapter->pullClasses($system);
        foreach ($items as $i => $item) $this->mappings->createMapping($system, 'class', (string)($i + 1), $item['external_id'], $item['code'] ?? null, $item);
        return $this->finish($job, count($items), count($items), 0);
    }

    public function fullSyncEnrollments(IntegrationSystem $system, ?int $actorId = null): SyncJob
    {
        $job = $this->start($system, 'full_sync', 'enrollment', $actorId);
        $items = $this->adapter->pullEnrollments($system);
        foreach ($items as $i => $item) $this->mappings->createMapping($system, 'enrollment', (string)($i + 1), $item['external_id'], null, $item);
        return $this->finish($job, count($items), count($items), 0);
    }

    public function pushGradesToSIS(IntegrationSystem $system, array $grades = [], ?int $actorId = null): SyncJob
    {
        $job = $this->start($system, 'push_to_sis', 'grade', $actorId);
        $result = $this->adapter->pushGrades($system, $grades);
        $this->outbound->createOutboundEvent($system, 'lms.grade.synced', 'grade', null, ['grades'=>$grades,'result'=>$result], 'push-grade-'.$job->id);
        return $this->finish($job, count($grades), $result['accepted'] ?? count($grades), 0);
    }

    public function pushAttendanceToSIS(IntegrationSystem $system, array $attendance = [], ?int $actorId = null): SyncJob
    {
        $job = $this->start($system, 'push_to_sis', 'attendance', $actorId);
        $result = $this->adapter->pushAttendance($system, $attendance);
        $this->outbound->createOutboundEvent($system, 'lms.attendance.updated', 'attendance', null, ['attendance'=>$attendance,'result'=>$result], 'push-attendance-'.$job->id);
        return $this->finish($job, count($attendance), $result['accepted'] ?? count($attendance), 0);
    }

    public function pushProgressToSIS(IntegrationSystem $system, array $progress = [], ?int $actorId = null): SyncJob
    {
        $job = $this->start($system, 'push_to_sis', 'progress', $actorId);
        $result = $this->adapter->pushProgress($system, $progress);
        $this->outbound->createOutboundEvent($system, 'lms.progress.updated', 'progress', null, ['progress'=>$progress,'result'=>$result], 'push-progress-'.$job->id);
        return $this->finish($job, count($progress), $result['accepted'] ?? count($progress), 0);
    }

    public function retryFailedJob(SyncJob $job): SyncJob
    {
        $job->forceFill(['status'=>'pending','error_report'=>null])->save();
        return $job->fresh();
    }

    private function start(IntegrationSystem $system, string $type, string $entity, ?int $actorId): SyncJob
    {
        return SyncJob::query()->create(['tenant_id'=>$system->tenant_id,'system_id'=>$system->id,'job_type'=>$type,'entity_type'=>$entity,'status'=>'running','started_at'=>now(),'created_by'=>$actorId]);
    }

    private function finish(SyncJob $job, int $total, int $success, int $failed): SyncJob
    {
        $job->forceFill(['status'=>$failed > 0 ? 'failed' : 'success','total_count'=>$total,'success_count'=>$success,'failed_count'=>$failed,'finished_at'=>now()])->save();
        return $job->fresh();
    }
}
