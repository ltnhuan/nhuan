# EraLMS Enterprise Scaffold

EraLMS is a Laravel 12 + PostgreSQL + Redis architecture scaffold for an enterprise LMS that can run independently and integrate with SIS APIs later. This first implementation covers prompts 01–03: core multi-tenant/RBAC UI shell, Course Studio + Repository, and Learning Path + Completion.

## Run locally

1. Install PHP dependencies: `composer install`.
2. Use the checked local `.env` template values or copy `.env.example` to `.env`.
3. For local development the default connection is SQLite: `DB_CONNECTION=sqlite` and `DB_DATABASE=database/database.sqlite`.
4. Run migrations and demo seed data: `php artisan migrate --seed`.
5. Start the API server: `php artisan serve --host=127.0.0.1 --port=8001`.
6. Build or run the Vue frontend assets: `npm install && npm run build` or `npm run dev`.

To switch to PostgreSQL, set `DB_CONNECTION=pgsql` and fill `PGSQL_HOST`, `PGSQL_PORT`, `PGSQL_DATABASE`, `PGSQL_USERNAME`, and `PGSQL_PASSWORD` in `.env`.

## Checks

- PHP syntax: `composer run test:syntax`
- Prompt 01-02 feature tests: `composer run test:feature`
- Frontend production build: `npm run build`


## Prompt 01 implementation details

Prompt 01 is implemented with tenant resolution, tenant-scoped RBAC, cached menu/settings, audit logging, demo VABIS seed data, and compact Vue admin screens. See `docs/core/prompt-01-core.md` for route-by-route details and the Prompt 01 operating model.

## Core architecture

- Tenant is resolved from `X-Tenant-Code`, exact domain, or the default `VABIS` tenant.
- RBAC uses tenant-scoped roles, permission keys, and the `user_role_scope` table for tenant/campus/faculty/course/class scopes.
- System settings and permissions are designed for Redis caching.
- Audit logs capture actor, module, entity, before/after JSON, IP address, and user agent.


## Prompt 02 implementation details

Prompt 02 is implemented with a Course Studio outline service, course version snapshots, publish validation, repository tree/versioning/share/download-url APIs, activity registry validation, approval workflow transitions and enterprise Course Studio/Repository pages. See `docs/course-studio/prompt-02-course-studio.md` for detailed endpoints and authoring workflow.

## Course Studio and Repository

- Course hierarchy follows `course -> section/subsection/unit -> component`.
- Repository items form a folder tree with local storage first and S3/MinIO-ready paths/checksums.
- Approval workflow supports submit review, approve, reject, and return for edit.
- Activity registry seeds placeholders for text, video, PDF, SCORM, quiz, assignment, forum, survey, wiki, live session, certificate, and external tools.

## Learning Path

- Supports free navigation, sequential, prerequisite, mastery, adaptive, date-lock, and manual approval rules.
- Completion is server-verified by component type and evidence.
- Progress events are append-only and summarized into `user_course_progress` for fast dashboards.
- Video fake-progress detection flags suspicious jumps and duplicate activity.

## Video Learning Platform

- Video upload stores files in storage and prepares HLS/CDN delivery without streaming through Laravel.
- Playback URLs are signed and expire through `VIDEO_SIGNED_URL_TTL`.
- Watch sessions send heartbeat/event data to server-side anti-fake checks.
- Progress dashboards read `video_progress_summaries`; raw video events stay append-only.
- See `docs/video-platform.md` for storage, HLS, CDN and tracking configuration.

## Question Bank Enterprise

- Shared question banks support tenant, faculty, course, chapter, CLO/PLO, Bloom and difficulty classification.
- Question types include single choice, multiple choice, true/false, essay, fill blank, matching, ordering and media questions.
- Every create/update stores a version snapshot for approval and exam audit trails.
- Exam blueprints generate random preview structures and warn when the bank does not have enough matching questions.
- See `docs/question-bank.md` for import formats and blueprint rules.

## Online Exam

- Exams can be built from question blueprints or manually attached questions.
- Attempts store immutable question snapshots and autosaved answers.
- Auto grading handles objective questions; essay/case-study answers go to manual grading.
- Proctoring events are append-only and can flag suspicious attempts.
- See `docs/online-exam.md` for the exam authoring and attempt workflow.

## Demo accounts

- `admin.lms@vabis.edu.vn`
- `daotao.lms@vabis.edu.vn`
- `khoa.lms@vabis.edu.vn`
- `gv.lms@vabis.edu.vn`
- `sv.lms@vabis.edu.vn`
