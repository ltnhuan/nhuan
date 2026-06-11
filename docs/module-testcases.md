# EraLMS Module Testcases

Run all feature tests:

```bash
vendor/bin/phpunit --testsuite=Feature
```

Run a module:

```bash
vendor/bin/phpunit tests/Feature/<ModuleFeatureTest.php>
```

## Core / Learning Path / Repository

File: `tests/Feature/EraLmsCoreTest.php`

- Learning rule requires type.
- Learning rule validates required score threshold.
- Learning rule preview explains requirements.
- Learning rule rejects self cycle.
- Fake video progress is detected.
- Normal video progress is not flagged.
- Tenant resolver extracts subdomain code.
- Permission scope allows global and blocks wrong campus.
- Repository service guesses enterprise item types.

File: `tests/Feature/Prompt01Prompt02FeatureTest.php`

- Core `me` resolves tenant and demo user.
- RBAC blocks student from tenant management.
- Course Studio creates outline and publish snapshot.
- Repository upload creates item and version.
- Repository trash, restore, permanent delete workflow.
- Repository bulk trash and restore.
- Editor media upload returns insertable media payload.

File: `tests/Feature/CourseStudioCommercialFlowTest.php`

- Commercial Course Studio action flow.
- Publish checklist detects missing video content.

## AI Learning

File: `tests/Feature/AiLearningPlatformFeatureTest.php`

- Ingest document chunks and embeds content.
- AI tutor answers from vector store.
- Generate quiz from ingested document.
- Generate flashcards from ingested document.

## Assignment

File: `tests/Feature/AssignmentManagementFeatureTest.php`

- Create assignment.
- Submit assignment.
- Late submission is marked late.
- Individual deadline and grace period.
- Relative deadline starts on first submission and is audited.
- Grace cutoff blocks submission after effective deadline.
- Cannot exceed max submissions.
- Rubric grading calculates total score.
- Pass score completes learning path component.
- Student detail only contains own submissions.
- Teacher can view assigned course scope.

## Attendance

File: `tests/Feature/AttendanceFeatureTest.php`

- Create live session.
- Open QR attendance and check in by QR.
- Check in by OTP.
- Manual update.
- Locked attendance blocks normal update.
- Attendance percent and exam eligibility under 80 percent.

## Career Portfolio

File: `tests/Feature/CareerPortfolioFeatureTest.php`

- Create profile and add portfolio item.
- Skill matrix groups radar categories.
- Public portfolio and employer search.
- Verify certificate.
- Student cannot update other profile.

## Digital Credential

File: `tests/Feature/DigitalCredentialFeatureTest.php`

- Issue certificate.
- Verify QR certificate.
- Revoke certificate.
- Badge auto issue.

## Enrollment

File: `tests/Feature/EnrollmentManagementFeatureTest.php`

- Seed creates 50,000 records for virtual table.
- Manual, bulk, invite, self, SIS enrollment sources.
- Bulk action and analytics cover lifecycle.
- API import and teacher assignment are tracked.

## Gradebook

File: `tests/Feature/GradebookFeatureTest.php`

- Weighted grade calculation.
- Pull quiz scores from exam results.
- Pull assignment scores from assignment grades.
- Formula gradebook calculation.
- Override grade writes log.
- Submit, approve, lock, locked override rule.
- Sync SIS calls contract.
- Student matrix only returns own row.

## Integration Hub / SIS

File: `tests/Feature/IntegrationHubFeatureTest.php`

- Mock SIS connection.
- Valid webhook signature accepted and invalid rejected.
- Idempotency prevents duplicate events.
- Mapping local/external and conflict.
- Full sync users creates mapping.
- Push grade creates outbound event.
- Retry failed webhook delivery.
- Dashboard loads event logs.

## Learning Analytics

File: `tests/Feature/LearningAnalyticsFeatureTest.php`

- Risk score and level from learning metrics.
- Early warning alerts for progress, grade, dropout risks.
- Dashboard returns KPIs, charts, alerts.

## Learning Community

File: `tests/Feature/LearningCommunityFeatureTest.php`

- Create thread extracts mentions and notifications.
- Reply, like, mark correct update reputation.
- Moderation hides post and locks thread.
- API analytics returns engagement score.

## Learning Standards

File: `tests/Feature/LearningStandardsFeatureTest.php`

- Upload SCORM package zip.
- Launch SCORM creates attempt.
- Completion tracking updates progress, score, completion.
- Store xAPI actor, verb, object.
- LTI launch records claims.

## Mobile Learning

File: `tests/Feature/MobileLearningFeatureTest.php`

- Mobile bootstrap returns courses, offline contract, wallet.
- Offline lesson progress syncs to server tables.
- Offline quiz answer is saved and idempotent queue is respected.

## Moodle Parity

File: `tests/Feature/MoodleParityFeatureTest.php`

- Parity report maps existing EraLMS modules.
- Sync plan updates existing and tracks planned modules.

## OBE / Accreditation

File: `tests/Feature/OBEAccreditationFeatureTest.php`

- PLO/CLO mapping appears in matrix.
- Coverage flags unassessed CLO and weak PLO.
- Assessment mapping removes unassessed CLO gap.
- Achievement analytics calculates CLO percent.
- Accreditation report export metadata.
- API dashboard loads for accreditation roles.

## Online Exam

File: `tests/Feature/OnlineExamFeatureTest.php`

- Create exam from blueprint and assign user.
- Start attempt generates snapshot without client correct answer.
- Autosave, submit, auto-grade MCQ.
- Proctoring flags high suspicious attempt.

## Question Bank

File: `tests/Feature/QuestionBankFeatureTest.php`

- Create question bank.
- Validate single choice requires one correct answer.
- Create question and version.
- Update question creates next version.
- Map outcome.
- Blueprint preview warns when not enough questions.
- Question create/update preserves requested status.

## Survey / Evaluation

File: `tests/Feature/SurveyEvaluationFeatureTest.php`

- Builder supports question types and reorder.
- Anonymous response calculates average and NPS.
- Analytics, improvement, evidence, exports available.

## Video Platform

File: `tests/Feature/VideoPlatformFeatureTest.php`

- Upload video creates asset.
- Playback URL returns signed storage URL.
- Heartbeat updates progress summary.
- Large seek is suspicious.
- Video completed only after required percent.
- Anti-fake detects impossible speed.

## Performance Architecture

File: `tests/Feature/HighPerformanceArchitectureTest.php`

- Required performance queues are declared.
- Jobs are bound to dedicated queues.

## Latest Run

Feature tests were executed by module batches. All executed module batches passed.

- Core + Attendance: 15 tests, 30 assertions.
- Integration Hub: 8 tests, 19 assertions.
- Course Studio Commercial Flow: 2 tests, 26 assertions.
- AI + Assignment + Career: 20 tests, 52 assertions.
- Digital Credential + Enrollment + Gradebook: 16 tests, 58 assertions.
- Learning Analytics + Community + Standards: 12 tests, 46 assertions.
- Mobile + Moodle Parity + OBE: 11 tests, 47 assertions.
- Online Exam + Core/Repository/Course Flow + Question Bank: 18 tests, 64 assertions.
- Survey + Video + Performance: 11 tests, 42 assertions.
