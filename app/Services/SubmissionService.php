<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentEvent;
use App\Models\AssignmentSubmission;
use App\Models\AssignmentSubmissionFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SubmissionService
{
    public function __construct(private readonly AssignmentService $assignments) {}

    public function saveDraft(Assignment $assignment, int $userId, array $data): AssignmentSubmission
    {
        $assignment = $this->assignments->startSubmissionWindow($assignment, $userId);

        $draft = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $userId)
            ->where('status', 'draft')
            ->latest('submission_no')
            ->first();

        $submission = $draft ?: AssignmentSubmission::query()->create([
            'tenant_id' => $assignment->tenant_id,
            'assignment_id' => $assignment->id,
            'user_id' => $userId,
            'group_id' => $data['group_id'] ?? null,
            'submission_no' => $this->nextSubmissionNo($assignment, $userId),
            'status' => 'draft',
            'metadata' => $data['metadata'] ?? [],
        ]);

        $submission->fill($this->contentPayload($data))->save();
        $this->event($assignment, $submission, $userId, 'draft_saved');
        return $submission->fresh('files');
    }

    public function submit(Assignment $assignment, int $userId, array $data): AssignmentSubmission
    {
        $this->assertCanSubmit($assignment, $userId);
        $assignment = $this->assignments->startSubmissionWindow($assignment, $userId);
        $window = $this->assignments->assertWithinSubmissionWindow($assignment, $userId);
        $submission = $this->saveDraft($assignment, $userId, $data);
        $submittedAt = now();
        $late = $this->assignments->isLate($window, $submittedAt);

        $submission->forceFill([
            'status' => $late ? 'late_submitted' : 'submitted',
            'submitted_at' => $submittedAt,
            'metadata' => array_merge($submission->metadata ?? [], [
                'deadline_window' => $window,
                'late_submission' => $late,
            ]),
        ])->save();
        $this->event($assignment, $submission, $userId, $late ? 'late' : 'submitted', ['deadline_window' => $window]);
        return $submission->fresh('files');
    }

    public function resubmit(AssignmentSubmission $previous, array $data): AssignmentSubmission
    {
        $assignment = $previous->assignment;
        $this->assertCanSubmit($assignment, $previous->user_id);
        $assignment = $this->assignments->startSubmissionWindow($assignment, $previous->user_id);
        $window = $this->assignments->assertWithinSubmissionWindow($assignment, $previous->user_id);
        $submittedAt = now();
        $late = $this->assignments->isLate($window, $submittedAt);

        $submission = AssignmentSubmission::query()->create([
            'tenant_id' => $assignment->tenant_id,
            'assignment_id' => $assignment->id,
            'user_id' => $previous->user_id,
            'group_id' => $data['group_id'] ?? $previous->group_id,
            'submission_no' => $this->nextSubmissionNo($assignment, $previous->user_id),
            'status' => $late ? 'late_submitted' : 'resubmitted',
            'submitted_at' => $submittedAt,
            'metadata' => [
                'previous_submission_id' => $previous->id,
                'deadline_window' => $window,
                'late_submission' => $late,
            ] + ($data['metadata'] ?? []),
        ] + $this->contentPayload($data));
        $this->event($assignment, $submission, $previous->user_id, $late ? 'late_resubmitted' : 'resubmitted', ['deadline_window' => $window]);
        return $submission;
    }

    public function attachFiles(AssignmentSubmission $submission, array $files, int $uploadedBy): array
    {
        $created = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $path = $file->store("assignment-submissions/{$submission->tenant_id}/{$submission->id}");
            $created[] = AssignmentSubmissionFile::query()->create([
                'tenant_id' => $submission->tenant_id,
                'submission_id' => $submission->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize() ?: 0,
                'checksum' => hash_file('sha256', Storage::path($path)),
                'uploaded_by' => $uploadedBy,
                'created_at' => now(),
            ]);
        }
        $this->event($submission->assignment, $submission, $uploadedBy, 'draft_saved', ['files' => count($created)]);
        return $created;
    }

    public function history(Assignment $assignment, int $userId)
    {
        return AssignmentSubmission::query()->where('assignment_id', $assignment->id)->where('user_id', $userId)->with(['files','grade'])->orderBy('submission_no')->get();
    }

    private function assertCanSubmit(Assignment $assignment, int $userId): void
    {
        if ($assignment->status !== 'published') {
            throw new \InvalidArgumentException('Bài tập chưa được mở nộp.');
        }
        if ($assignment->max_submissions !== null) {
            $count = AssignmentSubmission::query()->where('assignment_id', $assignment->id)->where('user_id', $userId)->whereNotIn('status', ['draft','cancelled'])->count();
            if ($count >= $assignment->max_submissions) {
                throw new \InvalidArgumentException('Đã vượt số lần nộp cho phép.');
            }
        }
    }

    private function nextSubmissionNo(Assignment $assignment, int $userId): int
    {
        return (int) AssignmentSubmission::query()->where('assignment_id', $assignment->id)->where('user_id', $userId)->max('submission_no') + 1;
    }

    private function contentPayload(array $data): array
    {
        return [
            'content_text' => $data['content_text'] ?? null,
            'content_url' => $data['content_url'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ];
    }

    private function event(Assignment $assignment, ?AssignmentSubmission $submission, int $userId, string $type, array $metadata = []): void
    {
        AssignmentEvent::query()->create(['tenant_id'=>$assignment->tenant_id,'assignment_id'=>$assignment->id,'submission_id'=>$submission?->id,'user_id'=>$userId,'event_type'=>$type,'metadata'=>$metadata,'created_at'=>now()]);
    }
}
