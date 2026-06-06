# Assignment Deadline Engine

This module reimplements the ILIAS-style assignment phase model in EraLMS without copying ILIAS source code.

## Concepts

- `open_at`: earliest time learners can submit.
- `deadline_mode`: `absolute` or `relative`.
- `common_due_at`: the normal due date.
- `relative_started_at`: learner-specific start time for relative deadlines.
- `individual_due_at`: learner-specific extension.
- `official_due_at`: max of common due and individual due.
- `grace_period_ends_at`: optional late-submission cutoff.
- `effective_due_at`: final allowed submission cutoff. If late submission is allowed and no grace cutoff is configured, late submission stays open for backward compatibility.
- `phase`: `not_open`, `open`, `late_grace`, or `closed`.

## API

### Update Schedule

`PUT /api/v1/assignments/{assignment}/schedule`

Absolute deadline:

```json
{
  "deadline_mode": "absolute",
  "open_at": "2026-06-06T08:00:00+07:00",
  "due_at": "2026-06-10T23:59:00+07:00",
  "allow_late": true,
  "grace_period_ends_at": "2026-06-12T23:59:00+07:00",
  "individual_deadlines": [
    { "user_id": 25, "due_at": "2026-06-14T23:59:00+07:00" }
  ]
}
```

Relative deadline:

```json
{
  "deadline_mode": "relative",
  "open_at": "2026-06-06T08:00:00+07:00",
  "relative_duration_days": 7,
  "relative_duration_hours": 0,
  "relative_duration_minutes": 0,
  "allow_late": false
}
```

### Preview Deadline

`GET /api/v1/assignments/{assignment}/deadline`

The response is learner-aware through the current authenticated user or demo header:

```json
{
  "deadline_mode": "relative",
  "open_at": "2026-06-06T01:00:00.000000Z",
  "relative_started_at": "2026-06-06T03:00:00.000000Z",
  "common_due_at": "2026-06-13T03:00:00.000000Z",
  "individual_due_at": null,
  "official_due_at": "2026-06-13T03:00:00.000000Z",
  "grace_period_ends_at": null,
  "effective_due_at": "2026-06-13T03:00:00.000000Z",
  "allow_late": false,
  "phase": "open"
}
```

## Submit Behavior

- Submit before `open_at`: blocked.
- Submit before or at `official_due_at`: `submitted`.
- Submit after `official_due_at` but before `effective_due_at`: `late_submitted`.
- Submit after `effective_due_at`: blocked.
- Relative deadlines start at the learner's first draft/submit action after `open_at`.
- Every submission stores `metadata.deadline_window` and `metadata.late_submission` for audit.

