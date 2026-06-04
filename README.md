# EraLMS Enterprise Scaffold

EraLMS is a Laravel 12 + PostgreSQL + Redis architecture scaffold for an enterprise LMS that can run independently and integrate with SIS APIs later. This first implementation covers prompts 01–03: core multi-tenant/RBAC UI shell, Course Studio + Repository, and Learning Path + Completion.

## Run locally

1. Install PHP dependencies when Packagist access is available: `composer install`.
2. Configure PostgreSQL and Redis in `.env`.
3. Run migrations: `php artisan migrate`.
4. Seed demo data: `php artisan db:seed`.
5. Start queue/Horizon if installed: `php artisan queue:work` or `php artisan horizon`.
6. Build the Vue/Inertia frontend in the host Laravel application with `npm install && npm run dev`.


## Prompt 01 implementation details

Prompt 01 is implemented with tenant resolution, tenant-scoped RBAC, cached menu/settings, audit logging, demo VABIS seed data, and compact Vue admin screens. See `docs/core/prompt-01-core.md` for route-by-route details and the Prompt 01 operating model.

## Core architecture

- Tenant is resolved from `X-Tenant-Code`, exact domain, or the default `VABIS` tenant.
- RBAC uses tenant-scoped roles, permission keys, and the `user_role_scope` table for tenant/campus/faculty/course/class scopes.
- System settings and permissions are designed for Redis caching.
- Audit logs capture actor, module, entity, before/after JSON, IP address, and user agent.

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

## Demo accounts

- `admin.lms@vabis.edu.vn`
- `daotao.lms@vabis.edu.vn`
- `khoa.lms@vabis.edu.vn`
- `gv.lms@vabis.edu.vn`
- `sv.lms@vabis.edu.vn`
