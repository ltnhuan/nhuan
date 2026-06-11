# Data Integrity Guide

EraLMS dùng SIS và LMS theo nguyên tắc source of truth:

- SIS quản lý student, teacher, class, class section, academic year, semester, faculty, major, program và enrollment status.
- LMS quản lý course online, lesson, component, learning path, video progress, quiz attempt, assignment submission, attendance online, LMS grade draft và certificate online.

## Bảng kiểm tra

Migration `2026_06_10_000001_create_data_integrity_tables.php` tạo:

- `data_integrity_checks`: trạng thái từng check theo tenant/module.
- `data_integrity_issues`: issue chi tiết theo entity, severity, auto-fix và trạng thái xử lý.

## Service

`App\Services\DataIntegrityService` cung cấp:

- `runAllChecks()`
- `runModuleChecks($module, $tenantId)`
- `detectOrphanRecords()`
- `detectBrokenMappings()`
- `detectInvalidStatusTransitions()`
- `detectMissingRequiredRelations()`
- `detectDuplicateCodes()`
- `detectInvalidGradeSources()`
- `detectInvalidCertificateIssues()`
- `autoFixIssue($issue)`
- `ignoreIssue($issue)`

## Dashboard

Route web:

- `/admin/lms/data-integrity`

API:

- `GET /api/v1/admin/lms/data-integrity`
- `POST /api/v1/admin/lms/data-integrity/run`
- `POST /api/v1/admin/lms/data-integrity/run/{module}`
- `POST /api/v1/admin/lms/data-integrity/auto-fix`
- `POST /api/v1/admin/lms/data-integrity/ignore`
- `GET /api/v1/admin/lms/data-integrity/export`

## Commands

```bash
php artisan lms:integrity-check
php artisan lms:integrity-check --module=course
php artisan lms:integrity-fix
php artisan lms:integrity-report
```

## Delete Policy

`DataDeletePolicyService` không cho hard delete dữ liệu đã phát sinh nghiệp vụ:

- course đã `published`
- exam đã có attempt
- assignment đã có submission
- gradebook đã `locked`
- certificate đã `issued`
- attendance đã `locked`
- SIS mapping đã sync

Thay thế bằng archive, inactive hoặc revoked theo module.

## Status Transition

`StatusTransitionValidator` chuẩn hóa luồng trạng thái cho course, exam, attempt, assignment, submission, gradebook, attendance, certificate và sync job. Các service/controller phải gọi validator khi cho phép cập nhật status trực tiếp.
