# Prompt 02 — Course Studio Builder + Learning Content Repository

Prompt 02 builds the authoring foundation for EraLMS: course management, Studio outline editing, repository tree, versioning, activity registry and approval/publishing workflow.

## Course Studio model

- `courses` stores course metadata, status, visibility, owner, approval and publish timestamps.
- `course_sections` stores `section`, `subsection` and `unit` nodes with parent-child nesting and release/due dates.
- `course_components` stores unit-level components such as text, video, PDF, file, SCORM, quiz, assignment and forum placeholders.
- `course_versions` stores immutable JSON snapshots from `CourseStructureService::outline()`.
- `course_publish_logs` records clone, submit review, approve, publish and archive actions.

## Services

- `CourseStudioService` creates/clones courses, validates publish readiness, snapshots structure, publishes and archives.
- `CourseStructureService` creates/updates sections and components, validates hierarchy and reorders outline nodes.
- `ActivityRegistryService` registers activity types and validates component config against required schema keys.
- `ApprovalWorkflowService` supports submit review, approve, reject and return-for-edit transitions.
- `RepositoryService` creates folders, uploads files with checksum deduplication, adds versions, moves/copies/shares items and returns download URLs.

## API additions

- `GET /api/v1/course-categories`
- `GET /api/v1/activity-types`
- `GET /api/v1/courses/{course}/validate-publish`
- `GET /api/v1/repository/tree`
- `GET /api/v1/repository/items/{item}`
- `POST /api/v1/repository/items/{item}/share`
- `GET /api/v1/repository/items/{item}/versions`
- `GET /api/v1/repository/items/{item}/download-url`
- `POST /api/v1/repository/items/{item}/reject`
- `POST /api/v1/repository/items/{item}/return`

## Publish validation

A course cannot publish unless it has at least one section, one unit and one component. Components must use enabled activity types. Components that represent files or packages (`video`, `pdf`, `file`, `scorm`) must be linked to repository content.

## Repository storage

The current implementation uses the configured Laravel disk first and stores only paths/checksums in JSON APIs. S3/MinIO can be enabled by changing `ERALMS_REPOSITORY_DISK`. Binary file content is never returned through JSON endpoints.
