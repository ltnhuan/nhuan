# OBE and Accreditation

The OBE module supports AUN-QA, TVET, and GDNN evidence workflows.

## Scope

- PLO and CLO management reuses `learning_outcomes`.
- Competency and skill frameworks use `competency_frameworks`, `competency_framework_items`, and existing career skill tables.
- `outcome_mappings` stores the dynamic path from PLO/CLO to course, lesson, competency, and evidence.
- `assessment_outcome_mappings` links quiz, assignment, project, and exam to outcomes.
- `outcome_achievement_summaries` stores calculated CLO, PLO, and competency attainment.
- `accreditation_reports` stores generated report metadata for PDF, Excel, and Word.

## Coverage Rules

Coverage analysis flags:

- CLOs without direct assessment.
- PLOs without enough evidence.
- Courses without mapped outcomes.

## Reports

Report generation currently records export metadata and metrics. File rendering can be connected later to PDF/Excel/Word libraries without changing the API surface.
