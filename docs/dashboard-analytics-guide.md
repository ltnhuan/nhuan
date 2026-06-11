# EraLMS Dashboard Analytics Guide

## Kiến trúc dữ liệu

Dashboard Enterprise đọc từ summary table, không đọc trực tiếp raw event table trên màn hình chính.

Raw/event tables được dùng bởi job nền và drill-down khi cần:

- `learning_progress_events`
- `video_watch_events`
- `exam_attempt_events`
- `assignment_events`
- `attendance_events`
- `integration_events`
- `audit_logs`

Summary/dashboard tables mới:

- `dashboard_metric_snapshots`
- `dashboard_widget_configs`
- `dashboard_user_preferences`
- `course_operation_snapshots`
- `learner_analytics_snapshots`
- `class_analytics_snapshots`
- `faculty_analytics_snapshots`
- `exam_analytics_snapshots`
- `grade_analytics_snapshots`
- `attendance_analytics_snapshots`
- `risk_analytics_snapshots`
- `sis_sync_analytics_snapshots`
- `ai_usage_snapshots`
- `analytics_forecasts`
- `analytics_alerts`
- `analytics_benchmarks`

`DashboardDataService` chỉ đọc summary tables. Raw/operational aggregation nằm trong `AnalyticsSnapshotService`, chạy bằng command hoặc API rebuild.

## API

Dashboard:

- `GET /api/v1/dashboards/executive`
- `GET /api/v1/dashboards/academic`
- `GET /api/v1/dashboards/faculty`
- `GET /api/v1/dashboards/teacher`
- `GET /api/v1/dashboards/student`
- `GET /api/v1/dashboards/exam`
- `GET /api/v1/dashboards/attendance`
- `GET /api/v1/dashboards/gradebook`
- `GET /api/v1/dashboards/integration`
- `GET /api/v1/dashboards/content`
- `GET /api/v1/dashboards/certificate`
- `GET /api/v1/dashboards/ai`
- `GET /api/v1/dashboards/risk`

Analytics operations:

- `GET /api/v1/analytics/alerts`
- `POST /api/v1/analytics/alerts/{id}/acknowledge`
- `POST /api/v1/analytics/alerts/{id}/resolve`
- `GET /api/v1/analytics/forecasts`
- `POST /api/v1/analytics/rebuild-snapshots`
- `GET /api/v1/analytics/drilldown`
- `GET /api/v1/analytics/benchmark`
- `POST /api/v1/dashboards/export`

Filters thống nhất:

`academic_year_id`, `semester_id`, `campus_id`, `faculty_id`, `program_id`, `class_id`, `course_id`, `user_id`, `teacher_id`, `from`, `to`.

## Commands

- `php artisan lms:analytics-build-daily`
- `php artisan lms:analytics-build-weekly`
- `php artisan lms:analytics-rebuild --scope=tenant`
- `php artisan lms:forecast-build`
- `php artisan lms:alerts-detect`
- `php artisan lms:dashboard-health`

Scheduler:

- Daily snapshot: 01:00
- Forecast: 02:00
- Alert detection: hourly
- Dashboard health: every 15 minutes

## Data quality

Frontend hiển thị `DataQualityBadge`:

- `Good`: snapshot đủ và mới.
- `Warning`: thiếu một phần metric.
- `Missing`: chưa có summary đủ để tính.
- `Stale`: snapshot quá cũ.

Khi thiếu dữ liệu, dashboard không render số 0 giả. KPI trả `null` và UI hiển thị “Chưa có dữ liệu đủ để tính” cùng nút rebuild snapshot.

## Quyền dữ liệu

Backend scope dữ liệu theo user demo nếu có `X-Demo-User-Email`:

- Student: chỉ scope `user_id` của chính mình, bỏ qua `user_id` query khác.
- Teacher: chỉ các `class_id` được phân công trong `teacher_assignments`.
- Faculty: ưu tiên `faculty_id` filter hoặc role scope có `academic_unit_id`.
- Admin/BGH: scope tenant hoặc filter được truyền.

## Frontend

Pages:

- `resources/js/Pages/Lms/Dashboards/ExecutiveDashboard.vue`
- `AcademicDashboard.vue`
- `FacultyDashboard.vue`
- `TeacherDashboard.vue`
- `StudentDashboard.vue`
- `ExamDashboard.vue`
- `AttendanceDashboard.vue`
- `GradebookDashboard.vue`
- `IntegrationDashboard.vue`
- `ContentDashboard.vue`
- `RiskDashboard.vue`
- `CertificateDashboard.vue`
- `AiDashboard.vue`

Reusable components nằm tại `resources/js/Components/Lms/Dashboards`.

## Performance targets

- Dashboard chính đọc theo `tenant_id`, `snapshot_date`, `metric_key` và scope index.
- Chart data giới hạn tối đa 50 điểm.
- Drill-down dùng summary table và giới hạn bản ghi.
- Rebuild snapshot chạy command/API, có thể chuyển sang queue khi triển khai production.
