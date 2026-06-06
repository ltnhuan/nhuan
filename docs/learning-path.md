# Learning Path Engine

EraLMS Learning Path Engine controls content access and completion from the server side. Client events are evidence only; completion is accepted after `CompletionEngineService` verifies the configured rules.

## Modes

- `free`: learners can open every published item; completion is still tracked.
- `sequential`: each component requires the previous required component.
- `prerequisite`: sections or components define custom requirements.
- `mastery`: learners must reach configured score/watch/attendance thresholds.
- `adaptive`: rules can route learners to remedial or advanced content based on score or evidence.

## Rule JSON

```json
{
  "target": { "type": "course_component", "id": 123 },
  "requires": [
    { "type": "component_completed", "component_id": 122 },
    { "type": "quiz_score_min", "component_id": 124, "min_score": 70 },
    { "type": "video_watch_percent", "component_id": 125, "min_percent": 90 }
  ],
  "unlock_behavior": "all_required",
  "message_locked": "Bạn cần hoàn thành bài trước để mở bài này",
  "adaptive_routes": [
    { "when": "score < 70", "target_type": "course_component", "target_id": 130 },
    { "when": "score >= 90", "target_type": "course_component", "target_id": 131 }
  ]
}
```

Supported requirement types: `component_completed`, `section_completed`, `course_completed`, `quiz_score_min`, `assignment_score_min`, `video_watch_percent`, `video_watch_minutes`, `document_opened`, `document_confirmed`, `text_scrolled_to_end`, `live_attended_minutes`, `manual_approval`, and `date_after`.

## API Flow

1. Course Studio saves rules through `POST /api/v1/courses/{course}/learning-path/rules`.
2. Learner opens content only after `GET /api/v1/components/{component}/access-check` returns `allowed: true`.
3. Player sends append-only events through `POST /api/v1/learning-events` or component progress endpoints.
4. Complete calls go through `POST /api/v1/components/{component}/complete`; the server checks component config and evidence.
5. Manual approval uses request/approve/reject endpoints and recalculates course progress.

## Anti Fake Progress

- Video jumps such as 5% to 95% in a few seconds are flagged as suspicious.
- Duplicate events inside a short window are marked in metadata.
- Suspicious video progress does not increase completion percent.
- Client-provided `completed` state is ignored; server evidence rules decide final status.

## Performance

- `learning_progress_events` is append-only and indexed by tenant/user/course, component, event type, and created time.
- `user_course_progress` is the read-optimized summary for dashboards.
- Access checks are cached briefly and invalidated when completion or manual lock state changes.
- Large event logs should be loaded as summaries first, then details per learner.
