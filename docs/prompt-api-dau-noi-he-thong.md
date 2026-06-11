# Prompt code API đấu nối hệ thống EraLMS

Bạn là kiến trúc sư API và senior Laravel engineer. Hãy thiết kế, chuẩn hóa và triển khai bộ API đấu nối hệ thống cho EraLMS theo kiến trúc enterprise, multi-tenant, versioned API `/api/v1`, phục vụ kết nối SIS, HRM, SSO, BI, mobile app, kho học liệu, cổng thi, hệ thống cấp chứng chỉ và webhook.

## Mục tiêu

Tạo bộ API đầy đủ, chi tiết, ổn định và có tài liệu để hệ thống ngoài có thể tích hợp end-to-end với toàn bộ module EraLMS. API phải phủ các module: Core IAM, Course Studio, Content Repository, Learning Path, Video Platform, Question Bank, Online Exam, Assignment, Gradebook, Digital Credential, Attendance, Enrollment, Integration Hub, Learning Standards SCORM/xAPI/LTI, OBE Accreditation, Learning Analytics, Survey Evaluation, Learning Community, AI Learning, Mobile Learning và Admin Operations.

## Yêu cầu kiến trúc

- API đặt dưới `/api/v1`, có thể nâng version mà không phá vỡ client cũ.
- Tất cả endpoint chạy trong ngữ cảnh tenant, nhận `X-Tenant-Code` hoặc cơ chế tenant resolver tương đương.
- Hỗ trợ xác thực `Authorization: Bearer <token>` cho người dùng và `X-API-Key` cho hệ thống ngoài.
- Hỗ trợ phân quyền theo scope: tenant, course, module, action.
- Chuẩn response thống nhất:
  - Thành công: `{ "success": true, "message": "...", "data": {}, "meta": {} }`
  - Lỗi: `{ "success": false, "message": "...", "errors": {}, "code": "...", "meta": {} }`
- Hỗ trợ `Idempotency-Key` cho thao tác ghi dữ liệu, đồng bộ và webhook.
- Hỗ trợ `X-Request-Id` để trace log xuyên hệ thống.
- Có pagination, filter, search, sort, include relationship.
- Có audit log cho thao tác ghi quan trọng.
- Có rate limit theo API key, tenant và endpoint nhạy cảm.
- Có OpenAPI JSON tự sinh hoặc được duy trì cùng code.

## Nhóm endpoint bắt buộc

1. Core IAM: thông tin tenant, me, users, roles, permissions, settings, audit logs.
2. Course Studio: course CRUD, category, section, component, clone, submit review, approve, publish, archive, publish checklist.
3. Repository: tree, file/folder, upload, version, move/copy/share/trash/restore, download URL, approval workflow.
4. Learning Path: rules, learner progress, class progress, learning events, component start/progress/complete, approval completion.
5. Video: upload, process, playback URL, watch session, heartbeat, anti-fake events, progress analytics.
6. Question Bank: bank/category/question CRUD, versioning, tag, outcome mapping, import, coverage, blueprint preview.
7. Exam: exam CRUD, build from blueprint, publish/close, assign user/class, attempt start/submit, proctoring event, manual grading, result.
8. Assignment: assignment CRUD, rubric, submission, file, grading, AI feedback, deadline and late policy.
9. Gradebook: gradebook, grade item, formula, grade input/import, approval, audit, learner view, SIS push.
10. Credential: certificate, badge, rule, issue, revoke, verify portal, wallet, micro-credential.
11. Attendance: live session, attendance session, QR/OTP check-in, manual update, summary, eligibility recalculation.
12. Enrollment: cohort, cohort group/rule, class section, self-enroll, invite, teacher assignment, manual/bulk enroll, import, transition, analytics.
13. Integration Hub: systems, mappings, conflict resolve, inbound webhook, events, retry, sync job, push grades/attendance/progress, health.
14. Learning Standards: SCORM package/launch/track, xAPI statements, LTI registration/launch, external tools, analytics.
15. OBE: outcomes, competency framework, outcome mappings, assessment mappings, matrix, coverage, achievement, accreditation reports.
16. Analytics: dashboard, metrics, risk calculation, alerts, summaries, warehouse build.
17. Survey: form, campaign, response, export, improvement, evidence, analytics.
18. Community: forum, thread, reply, reaction, moderation, reports, wiki, blog, group, notifications, reputation, AI assist.
19. AI Learning: document ingest, ask, summary, quiz, flashcard, coach, outcome analytics.
20. Mobile: bootstrap, offline sync, push subscription.

## Yêu cầu nâng cao

- Tạo endpoint catalog `/api/v1/integration-api/catalog` để hệ thống ngoài đọc danh mục module, endpoint, middleware, event và chuẩn header.
- Tạo endpoint OpenAPI `/api/v1/integration-api/openapi.json`.
- Inbound webhook dùng HMAC-SHA256 trên raw body, header `X-ERALMS-Signature`.
- Outbound webhook có retry, delivery log và trạng thái success/failed.
- Mapping định danh ngoài/nội bộ cho student, teacher, course, class, enrollment, grade, attendance.
- Đồng bộ phải có event log, conflict log, idempotency và khả năng retry.
- Viết feature test cho catalog, OpenAPI và luồng webhook/sync quan trọng.
- Viết tài liệu tiếng Việt có dấu trong `docs/api-guide-vi.md`.

## Tiêu chí hoàn thành

- Route API chạy được, không lỗi syntax.
- Catalog và OpenAPI trả JSON hợp lệ.
- Tài liệu mô tả auth, tenant, response, error, webhook, module endpoint và ví dụ request/response.
- Không phá vỡ các test hiện có.
