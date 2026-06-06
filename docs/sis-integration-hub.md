# SIS Integration Hub

EraLMS runs independently and integrates with SIS through API gateway/webhooks/queues. The hub never reads the SIS database directly.

## Core Concepts

- SIS is source of truth for students, teachers, classes, course sections, enrollments, semesters, and academic status.
- LMS is source of truth for online learning activity, progress, quiz, assignment, attendance, grade approval, and certificates.
- `integration_mappings` links local ids to external SIS ids.
- `integration_events` stores inbound and outbound payloads with idempotency.
- `sync_jobs` tracks full sync, incremental sync, retries, and LMS-to-SIS pushes.
- `webhook_deliveries` tracks outbound webhook attempts and retry state.

## Security

- Inbound webhook signatures use HMAC SHA-256 in `X-EraLMS-Signature`.
- API keys are stored as hashes only.
- System credentials are encrypted with Laravel `Crypt`.
- Inbound webhook route is rate limited.
- Sync/push operations are guarded by integration permissions.

## Queues

Configured queue names:

- `sync-sis`
- `webhook`
- `integration-retry`

The current implementation stores jobs/events synchronously for testability. Queue workers can later dispatch event/job processing to these queues.

## Mock Adapter

`MockSISAdapter` is bound as the default `SISAdapterContract` so local tests and demos can run without a real SIS. Replace the binding with `DefaultSISAdapter` or a tenant-specific adapter when a production SIS gateway is available.
