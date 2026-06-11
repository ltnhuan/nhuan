# ILIAS Feature Analysis for EraLMS

Source reviewed: `https://github.com/ILIAS-eLearning/ILIAS.git`, shallow cloned read-only to `%TEMP%/ILIAS-codex-readonly`.

## License Boundary

ILIAS is GPL. EraLMS is currently marked `proprietary` in `composer.json`. Do not copy ILIAS source files, class bodies, templates, language files, or database migrations into EraLMS unless the product licensing strategy changes. The safe path is to copy feature ideas, business rules, workflows, and data-model concepts, then reimplement them in Laravel/Vue using EraLMS conventions.

## ILIAS Code Areas Read

- Root structure: `components/ILIAS`, `docs/configuration`, `docs/development`.
- Main object modules from `components/ILIAS/*/module.xml`: Course, Group, StudyProgramme, LearningModule, LearningSequence, SCORM 2004, SCORM/AICC, CmiXapi, LTIConsumer, Test, TestQuestionPool, Exercise, Survey, SurveyQuestionPool, Forum, Wiki, Blog, Portfolio, BookingManager, MediaCast, MediaPool, DataCollection, Category, Folder, File, WebResource, Session, OrgUnit.
- Service components reviewed by name and README where present: Repository, AccessControl, Membership, Tracking, Certificate, Badge, Calendar, Mail, Notification, Tasks, Metadata, Taxonomy, Skill, Search, WebDAV, WOPI, VirusScanner, Authentication, LDAP, SAML, OpenIdConnect.
- Business-rule docs read directly: `Course/README.md`, `Exercise/README.md`, `Repository/README.md`, `Badge/README.md`.

## EraLMS Current Coverage

EraLMS already has a broad Laravel/Vue implementation surface:

- Core tenant/RBAC/audit/settings/menu.
- Course Studio, repository, versioning, approval, publish checklist.
- Learning path, completion, progress events, unlock rules.
- Video upload/playback/progress/fake-progress detection.
- Question bank, question versions, outcomes, import, blueprints.
- Online exam, attempts, autosave, auto grading, manual grading, proctoring events.
- Assignment, attendance/live session, gradebook, enrollment/cohorts.
- SIS integration hub, webhooks, sync jobs, health checks.
- SCORM/LTI/xAPI foundations.
- OBE/competency/accreditation, analytics, survey, community, AI assistant, mobile offline, credentials, career portfolio.

## Feature Mapping

| ILIAS area | EraLMS status | Copy/reimplement target |
| --- | --- | --- |
| Repository object tree | Partial | Add trash, clipboard/bulk manage mode, role-based recommended content, last visited/favorites behavior. |
| Course | Partial | Add start objects, course timing view, object timeframe notifications, stricter membership lifecycle. |
| Group | Partial via enrollment/community | Add standalone collaborative groups with membership roles, group references, shared resources. |
| Study Programme | Partial via learning path/OBE | Add formal curriculum/program tree, assignments to cohorts, completion roll-up, references. |
| Learning Module / HTML Learning Module | Partial via Course Studio | Add structured page/chapter learning module authoring, page versioning, glossary/media pool embedding. |
| Learning Sequence | Partial via learning path | Add sequence object as reusable course item with ordered launch/progress state. |
| SCORM 2004 / AICC | Partial | Harden package import, manifest parsing, launch runtime, event tracking, completion/score mapping. |
| CmiXapi | Partial | Add xAPI LRS-style statement validation, actor/activity registry, reporting filters. |
| LTI Consumer / Provider | Partial consumer | Add LTI 1.3 deep linking, grade return, tool registration lifecycle, provider mode if needed. |
| Test / TestQuestionPool | Strong partial | Add ILIAS-style question pool separation, test settings depth, participant states, print/archive/export workflows. |
| Exercise | Partial assignment | Add relative deadlines, individual deadlines, grace periods, team assignments, peer review phases, tutor reminders. |
| Survey / SurveyQuestionPool | Partial | Add reusable survey question pools, 360 survey mode, anonymous evaluation constraints, print/export views. |
| Forum | Partial community | Add moderation queue, correct-answer workflow, thread subscriptions, richer permission matrix. |
| Wiki | Partial community | Add page history/diff, page-level permissions, wiki export/import, collaborative editing rules. |
| Blog | Partial community/career | Add course/user blogs as first-class repository objects with feed/subscription behavior. |
| Portfolio | Partial career portfolio | Add portfolio templates, page/block builder, access sharing, export/public presentation rules. |
| Booking Manager | Missing | Add bookable resources, slots, reservations, approvals, waitlists, conflict checks. |
| MediaCast / MediaPool | Partial video/repository | Add media collections, reusable media pool, object embedding, transcript/metadata management. |
| DataCollection | Missing | Add low-code table/form collections for course/admin records and exports. |
| Certificate | Partial credentials | Add certificate trigger parity: learning progress completed vs course passed; template/object-level activation. |
| Badge | Partial credentials | Add badge provider registry, object-level enablement, automatic event-based awarding, no-retroactive rule. |
| Calendar / Tasks / Notifications | Partial | Add unified learner/tutor task engine tied to deadlines, course timings, assignment phases, calendar aggregation. |
| Metadata / Taxonomy / Skill | Partial OBE/question tags | Add central metadata schema, taxonomy tree reuse across repository/course/question/search. |
| Search | Missing/implicit | Add global indexed search across courses, files, forums, wiki, tests, repository metadata. |
| WebDAV / WOPI / Cloud | Missing | Add optional document integration roadmap only; lower priority unless institution requires online office editing. |
| Auth LDAP/SAML/OIDC/Shibboleth | Partial config | Add production-grade SSO connectors, role mapping, tenant-aware login policies. |

## Priority Backlog

### P0: High-value parity that fits current EraLMS architecture

1. Repository trash and bulk manage mode. Implemented in `RepositoryService` and repository API routes.
2. Course start objects and course timing notifications.
3. Assignment deadline engine: absolute, relative, individual deadline, grace period, late flag. Implemented in `AssignmentService`, `SubmissionService`, and `docs/assignment-deadline-engine.md`.
4. Team assignments and peer review phase.
5. Unified tasks/calendar notification engine.
6. Certificate trigger rules tied to completion/pass status.
7. Badge provider registry and event-based auto awarding.
8. SCORM/LTI/xAPI hardening around import, launch, grade/completion return, and reporting.

### P1: Differentiating academic workflows

1. Study programme/curriculum object with completion roll-up.
2. Survey question pool and 360 survey mode.
3. Wiki history/diff and page-level permission model.
4. Forum subscriptions, moderation, correct-answer workflow.
5. Reusable media pool and media cast collections.
6. Central taxonomy/metadata service across course, content, questions, and analytics.
7. Global search index.

### P2: Optional enterprise extensions

1. Booking manager for rooms, devices, labs, teacher consultation slots.
2. DataCollection-style custom forms/tables.
3. WebDAV/WOPI/cloud document editing integrations.
4. Full SSO matrix: LDAP, SAML, OIDC, Shibboleth.
5. Remote object references for federated LMS/content catalogs.

## Suggested Implementation Order

1. Add a local `IliasParityService` or extend `MoodleParityService` into a generic `LmsParityService` with provider key `ilias`.
2. Seed `lms_action_registry` entries for the P0 workflows so UI/action smoke tests can detect missing routes.
3. Implement repository trash/manage mode first because it is isolated and improves admin safety.
4. Implement assignment deadline engine next because ILIAS has the clearest business rules and EraLMS already has assignment models.
5. Implement tasks/calendar notifications as a cross-module service after deadlines are stable.
6. Extend credentials with certificate/badge trigger rules.
7. Harden SCORM/LTI/xAPI after the core academic workflows are stable.

## Concrete Files to Touch First

- `app/Services/MoodleParityService.php`: generalize parity reporting or add ILIAS feature list.
- `database/seeders/LmsActionRegistrySeeder.php`: add action coverage for ILIAS parity items.
- `app/Services/RepositoryService.php`, `app/Http/Controllers/Api/V1/RepositoryController.php`: trash, restore, permanent delete, clipboard/bulk actions.
- `app/Services/AssignmentService.php`, `app/Http/Controllers/Api/V1/AssignmentController.php`: deadline engine, teams, peer review.
- `app/Services/DigitalCredentialService.php`: certificate and badge trigger parity.
- `app/Services/LearningStandardsService.php`: SCORM/LTI/xAPI hardening.
- `resources/js/Pages/Admin/MoodleParity.vue`: rename or add `IliasParity.vue` for comparison UI.
