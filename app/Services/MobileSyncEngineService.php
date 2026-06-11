<?php

namespace App\Services;

use App\Models\ExamAnswer;
use App\Models\ExamAttemptQuestion;
use App\Models\LearningProgressEvent;
use App\Models\OfflineProgress;
use App\Models\OfflineQueue;
use App\Models\OfflineQuizAnswer;
use App\Models\UserCourseProgress;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MobileSyncEngineService
{
    public function sync(int $tenantId, int $userId, array $items, ?string $deviceId = null): array
    {
        $results = [];

        foreach ($items as $item) {
            $clientUuid = $item['client_uuid'] ?? (string) Str::uuid();
            $operation = $item['operation'] ?? 'unknown';
            $payload = $item['payload'] ?? [];

            $queue = OfflineQueue::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'user_id' => $userId, 'client_uuid' => $clientUuid],
                [
                    'device_id' => $deviceId,
                    'operation' => $operation,
                    'payload' => $payload,
                    'available_at' => now(),
                ]
            );

            if ($queue->status === 'synced') {
                $results[] = $this->result($queue, 'already_synced');
                continue;
            }

            try {
                DB::transaction(function () use ($queue, $operation, $payload, $tenantId, $userId, $deviceId): void {
                    $queue->forceFill([
                        'operation' => $operation,
                        'payload' => $payload,
                        'attempts' => $queue->attempts + 1,
                    ])->save();

                    $result = match ($operation) {
                        'progress.upsert' => $this->syncProgress($tenantId, $userId, $payload, $deviceId),
                        'quiz.answer' => $this->syncQuizAnswer($tenantId, $userId, $payload, $deviceId),
                        default => throw new \InvalidArgumentException("Unsupported offline operation {$operation}."),
                    };

                    $queue->forceFill([
                        'status' => 'synced',
                        'result' => $result,
                        'conflict' => null,
                        'synced_at' => now(),
                    ])->save();
                });
            } catch (\Throwable $exception) {
                $queue->forceFill([
                    'status' => $this->isConflict($exception) ? 'conflict' : 'failed',
                    'conflict' => [
                        'message' => $exception->getMessage(),
                        'strategy' => $item['conflict_strategy'] ?? 'server_wins',
                    ],
                ])->save();
            }

            $results[] = $this->result($queue->fresh(), 'processed');
        }

        return [
            'synced' => collect($results)->where('status', 'synced')->count(),
            'failed' => collect($results)->where('status', 'failed')->count(),
            'conflicts' => collect($results)->where('status', 'conflict')->count(),
            'items' => $results,
        ];
    }

    private function syncProgress(int $tenantId, int $userId, array $payload, ?string $deviceId): array
    {
        foreach (['course_id', 'component_id'] as $field) {
            if (empty($payload[$field])) {
                throw new \InvalidArgumentException("Missing {$field} for offline progress sync.");
            }
        }

        $progress = OfflineProgress::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'course_id' => (int) $payload['course_id'],
                'component_id' => (int) $payload['component_id'],
            ],
            [
                'device_id' => $deviceId,
                'progress_percent' => max(0, min(100, (float) ($payload['progress_percent'] ?? 0))),
                'status' => $payload['status'] ?? 'in_progress',
                'metadata' => $payload['metadata'] ?? [],
                'client_updated_at' => $this->date($payload['client_updated_at'] ?? null),
                'synced_at' => now(),
            ]
        );

        LearningProgressEvent::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'course_id' => $progress->course_id,
            'component_id' => $progress->component_id,
            'event_type' => $progress->status === 'completed' ? 'offline_lesson_completed' : 'offline_progress',
            'event_value' => $progress->progress_percent,
            'metadata' => ['source' => 'mobile_offline'] + ($payload['metadata'] ?? []),
            'device_id' => is_numeric($deviceId) ? (int) $deviceId : null,
        ]);

        UserCourseProgress::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $userId, 'course_id' => $progress->course_id],
            [
                'status' => $progress->status === 'completed' ? 'in_progress' : $progress->status,
                'progress_percent' => $progress->progress_percent,
                'last_component_id' => $progress->component_id,
                'last_accessed_at' => now(),
                'metadata' => ['last_sync_source' => 'mobile_offline'],
            ]
        );

        return ['offline_progress_id' => $progress->id];
    }

    private function syncQuizAnswer(int $tenantId, int $userId, array $payload, ?string $deviceId): array
    {
        if (empty($payload['answer_data'])) {
            throw new \InvalidArgumentException('Missing answer_data for offline quiz sync.');
        }

        $offlineAnswer = OfflineQuizAnswer::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'exam_id' => $payload['exam_id'] ?? null,
            'attempt_id' => $payload['attempt_id'] ?? null,
            'attempt_question_id' => $payload['attempt_question_id'] ?? null,
            'question_id' => $payload['question_id'] ?? null,
            'device_id' => $deviceId,
            'answer_data' => $payload['answer_data'],
            'status' => 'synced',
            'client_updated_at' => $this->date($payload['client_updated_at'] ?? null),
            'synced_at' => now(),
        ]);

        $examAnswer = null;
        if (! empty($payload['attempt_id']) && ! empty($payload['attempt_question_id'])) {
            $attemptQuestion = ExamAttemptQuestion::query()->find($payload['attempt_question_id']);
            $examAnswer = ExamAnswer::query()->updateOrCreate(
                [
                    'attempt_id' => (int) $payload['attempt_id'],
                    'attempt_question_id' => (int) $payload['attempt_question_id'],
                ],
                [
                    'tenant_id' => $tenantId,
                    'question_id' => (int) ($payload['question_id'] ?? $attemptQuestion?->question_id ?? 0),
                    'answer_data' => $payload['answer_data'],
                    'autosaved_at' => now(),
                ]
            );

            $attemptQuestion?->forceFill(['is_answered' => true])->save();
            $offlineAnswer->forceFill(['sync_result' => ['exam_answer_id' => $examAnswer->id]])->save();
        }

        return [
            'offline_quiz_answer_id' => $offlineAnswer->id,
            'exam_answer_id' => $examAnswer?->id,
        ];
    }

    private function result(OfflineQueue $queue, string $mode): array
    {
        return [
            'client_uuid' => $queue->client_uuid,
            'operation' => $queue->operation,
            'status' => $queue->status,
            'mode' => $mode,
            'result' => $queue->result,
            'conflict' => $queue->conflict,
            'synced_at' => $queue->synced_at?->toISOString(),
        ];
    }

    private function isConflict(\Throwable $exception): bool
    {
        return str_contains(Str::lower($exception->getMessage()), 'conflict');
    }

    private function date(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }
}
