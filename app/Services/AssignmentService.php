<?php

namespace App\Services;

use App\Models\Assignment;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class AssignmentService
{
    public function create(array $data): Assignment
    {
        $this->validateScore($data);
        return Assignment::query()->create($data + ['status' => 'draft', 'settings' => []]);
    }

    public function update(Assignment $assignment, array $data): Assignment
    {
        $this->validateScore($data + $assignment->toArray());
        $assignment->fill($data)->save();
        return $assignment;
    }

    public function publish(Assignment $assignment): Assignment
    {
        $assignment->forceFill(['status' => 'published'])->save();
        return $assignment;
    }

    public function close(Assignment $assignment): Assignment
    {
        $assignment->forceFill(['status' => 'closed'])->save();
        return $assignment;
    }

    public function schedule(Assignment $assignment, array $data): Assignment
    {
        $deadline = $this->normalizeDeadlineEngine($data);
        $settings = $assignment->settings ?? [];
        $settings['deadline_engine'] = array_merge($settings['deadline_engine'] ?? [], $deadline);

        $assignment->forceFill([
            'open_at' => $deadline['open_at'] ?? $assignment->open_at,
            'due_at' => $deadline['deadline_mode'] === 'absolute' ? ($deadline['due_at'] ?? $assignment->due_at) : $assignment->due_at,
            'allow_late' => (bool) ($deadline['allow_late'] ?? $assignment->allow_late),
            'late_penalty_config' => $data['late_penalty_config'] ?? $assignment->late_penalty_config,
            'settings' => $settings,
        ])->save();

        return $assignment->fresh();
    }

    public function startSubmissionWindow(Assignment $assignment, int $userId, ?Carbon $now = null): Assignment
    {
        $now ??= now();
        $engine = $this->deadlineEngine($assignment);

        if (($engine['deadline_mode'] ?? 'absolute') !== 'relative') {
            return $assignment;
        }

        $openAt = $this->date($engine['open_at'] ?? $assignment->open_at);
        if ($openAt && $now->lessThan($openAt)) {
            return $assignment;
        }

        $settings = $assignment->settings ?? [];
        $starts = $settings['deadline_engine']['user_starts'] ?? [];
        if (! isset($starts[$userId])) {
            $starts[$userId] = $now->toISOString();
            $settings['deadline_engine']['user_starts'] = $starts;
            $assignment->forceFill(['settings' => $settings])->save();
        }

        return $assignment->fresh();
    }

    public function deadlineWindow(Assignment $assignment, int $userId, ?Carbon $now = null): array
    {
        $now ??= now();
        $engine = $this->deadlineEngine($assignment);
        $mode = $engine['deadline_mode'] ?? 'absolute';
        $openAt = $this->date($engine['open_at'] ?? $assignment->open_at);
        $individualStart = $this->relativeStart($engine, $userId);
        $commonDue = $mode === 'relative'
            ? $this->relativeDueAt($individualStart, $engine)
            : $this->date($engine['due_at'] ?? $assignment->due_at);
        $individualDue = $this->individualDueAt($engine, $userId);
        $officialDue = $this->maxDate($commonDue, $individualDue);
        $gracePeriodEndsAt = $this->date($engine['grace_period_ends_at'] ?? null);
        $allowLate = (bool) ($engine['allow_late'] ?? $assignment->allow_late);
        $effectiveDue = $allowLate
            ? ($gracePeriodEndsAt ? $this->maxDate($officialDue, $gracePeriodEndsAt) : null)
            : $officialDue;

        return [
            'deadline_mode' => $mode,
            'open_at' => $openAt?->toISOString(),
            'relative_started_at' => $individualStart?->toISOString(),
            'common_due_at' => $commonDue?->toISOString(),
            'individual_due_at' => $individualDue?->toISOString(),
            'official_due_at' => $officialDue?->toISOString(),
            'grace_period_ends_at' => $gracePeriodEndsAt?->toISOString(),
            'effective_due_at' => $effectiveDue?->toISOString(),
            'allow_late' => $allowLate,
            'phase' => $this->deadlinePhase($openAt, $officialDue, $effectiveDue, $allowLate, $now),
        ];
    }

    public function assertWithinSubmissionWindow(Assignment $assignment, int $userId, ?Carbon $now = null): array
    {
        $now ??= now();
        $window = $this->deadlineWindow($assignment, $userId, $now);

        if ($window['phase'] === 'not_open') {
            throw new \InvalidArgumentException('Bài tập chưa đến thời gian mở nộp.');
        }

        if ($window['phase'] === 'closed') {
            throw new \InvalidArgumentException('Bài tập đã quá hạn nộp.');
        }

        return $window;
    }

    public function isLate(array $window, ?Carbon $submittedAt = null): bool
    {
        $submittedAt ??= now();
        $officialDue = $this->date($window['official_due_at'] ?? null);

        return $officialDue !== null && $submittedAt->greaterThan($officialDue);
    }

    private function validateScore(array $data): void
    {
        if (($data['pass_score'] ?? 0) > ($data['max_score'] ?? PHP_INT_MAX)) {
            throw new \InvalidArgumentException('Điểm đạt không được lớn hơn điểm tối đa.');
        }
    }

    private function normalizeDeadlineEngine(array $data): array
    {
        $mode = $data['deadline_mode'] ?? Arr::get($data, 'deadline_engine.deadline_mode', 'absolute');
        if (! in_array($mode, ['absolute', 'relative'], true)) {
            throw new \InvalidArgumentException('deadline_mode phải là absolute hoặc relative.');
        }

        $engine = [
            'deadline_mode' => $mode,
            'open_at' => $this->iso($data['open_at'] ?? Arr::get($data, 'deadline_engine.open_at')),
            'due_at' => $this->iso($data['due_at'] ?? Arr::get($data, 'deadline_engine.due_at')),
            'allow_late' => (bool) ($data['allow_late'] ?? Arr::get($data, 'deadline_engine.allow_late', false)),
            'grace_period_ends_at' => $this->iso($data['grace_period_ends_at'] ?? Arr::get($data, 'deadline_engine.grace_period_ends_at')),
            'individual_deadlines' => $data['individual_deadlines'] ?? Arr::get($data, 'deadline_engine.individual_deadlines', []),
        ];

        if ($mode === 'relative') {
            $days = (int) ($data['relative_duration_days'] ?? Arr::get($data, 'deadline_engine.relative_duration_days', 0));
            $hours = (int) ($data['relative_duration_hours'] ?? Arr::get($data, 'deadline_engine.relative_duration_hours', 0));
            $minutes = (int) ($data['relative_duration_minutes'] ?? Arr::get($data, 'deadline_engine.relative_duration_minutes', 0));
            if ($days + $hours + $minutes <= 0) {
                throw new \InvalidArgumentException('Deadline tương đối cần thời lượng lớn hơn 0.');
            }
            $engine['relative_duration_days'] = $days;
            $engine['relative_duration_hours'] = $hours;
            $engine['relative_duration_minutes'] = $minutes;
        }

        return array_filter($engine, fn ($value) => $value !== null);
    }

    private function deadlineEngine(Assignment $assignment): array
    {
        return array_merge([
            'deadline_mode' => 'absolute',
            'open_at' => $assignment->open_at?->toISOString(),
            'due_at' => $assignment->due_at?->toISOString(),
            'allow_late' => $assignment->allow_late,
        ], $assignment->settings['deadline_engine'] ?? []);
    }

    private function relativeStart(array $engine, int $userId): ?Carbon
    {
        return $this->date($engine['user_starts'][$userId] ?? null);
    }

    private function relativeDueAt(?Carbon $start, array $engine): ?Carbon
    {
        if (! $start) {
            return null;
        }

        return $start->copy()
            ->addDays((int) ($engine['relative_duration_days'] ?? 0))
            ->addHours((int) ($engine['relative_duration_hours'] ?? 0))
            ->addMinutes((int) ($engine['relative_duration_minutes'] ?? 0));
    }

    private function individualDueAt(array $engine, int $userId): ?Carbon
    {
        foreach ($engine['individual_deadlines'] ?? [] as $deadline) {
            if ((int) ($deadline['user_id'] ?? 0) === $userId) {
                return $this->date($deadline['due_at'] ?? null);
            }
        }

        return null;
    }

    private function deadlinePhase(?Carbon $openAt, ?Carbon $officialDue, ?Carbon $effectiveDue, bool $allowLate, Carbon $now): string
    {
        if ($openAt && $now->lessThan($openAt)) {
            return 'not_open';
        }

        if ($officialDue && $now->lessThanOrEqualTo($officialDue)) {
            return 'open';
        }

        if ($effectiveDue && $now->lessThanOrEqualTo($effectiveDue)) {
            return 'late_grace';
        }

        if ($officialDue && $allowLate && ! $effectiveDue) {
            return 'late_grace';
        }

        return $officialDue || $effectiveDue ? 'closed' : 'open';
    }

    private function maxDate(?Carbon $left, ?Carbon $right): ?Carbon
    {
        if (! $left) {
            return $right;
        }
        if (! $right) {
            return $left;
        }

        return $left->greaterThan($right) ? $left : $right;
    }

    private function date(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        return $value instanceof Carbon ? $value->copy() : Carbon::parse($value);
    }

    private function iso(mixed $value): ?string
    {
        return $this->date($value)?->toISOString();
    }
}
