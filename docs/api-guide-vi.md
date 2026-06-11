# Hướng dẫn API đấu nối hệ thống EraLMS

## Tổng quan

EraLMS cung cấp API version `v1` tại base path:

```text
/api/v1
```

Bộ API dùng để đấu nối SIS, HRM, SSO, BI, mobile app, kho học liệu, hệ thống thi, gradebook, điểm danh, chứng chỉ số và webhook hai chiều.

## Xác thực và tenant

Header khuyến nghị cho mọi request:

```http
Accept: application/json
Content-Type: application/json
X-Tenant-Code: vabis
X-API-Key: <api-key-he-thong-ngoai>
Authorization: Bearer <user-token-neu-co>
Idempotency-Key: <uuid-cho-thao-tac-ghi>
X-Request-Id: <uuid-trace>
```

`X-Tenant-Code` xác định tenant. `X-API-Key` dùng cho hệ thống ngoài. `Authorization: Bearer` dùng cho phiên người dùng hoặc app nội bộ. Với thao tác tạo, cập nhật, đồng bộ, import và webhook, client nên gửi `Idempotency-Key` để tránh ghi trùng khi retry.

## Chuẩn response

Thành công:

```json
{
  "success": true,
  "message": "Thao tác thành công",
  "data": {},
  "meta": {}
}
```

Lỗi:

```json
{
  "success": false,
  "message": "Dữ liệu không hợp lệ",
  "errors": {},
  "code": "VALIDATION_FAILED",
  "meta": {}
}
```

Mã lỗi phổ biến: `VALIDATION_FAILED`, `FORBIDDEN`, `NOT_FOUND`, `ACTION_FAILED`, `SERVER_ERROR`.

## Endpoint tự mô tả API

EraLMS có hai endpoint để đội tích hợp lấy danh mục API mới nhất từ code:

```http
GET /api/v1/integration-api/catalog
GET /api/v1/integration-api/openapi.json
```

`catalog` trả danh sách module, endpoint, middleware nghiệp vụ, chuẩn header và event webhook. `openapi.json` trả đặc tả OpenAPI 3.1 để import vào Postman, Swagger UI hoặc công cụ sinh SDK.

## Module chính

Các nhóm API hiện được phủ trong `/api/v1`:

- Core IAM: tenant, user, role, permission, setting, audit log.
- Course Studio: khóa học, danh mục, section, component, clone, review, approve, publish.
- Content Repository: thư mục, học liệu, upload, version, move/copy/share/trash/restore, approval.
- Learning Path: rule, progress, event học tập, start/progress/complete component, duyệt completion.
- Video Platform: upload, xử lý video, playback URL, watch session, heartbeat, progress analytics.
- Question Bank: ngân hàng câu hỏi, category, question, outcome mapping, import, blueprint.
- Online Exam: kỳ thi, blueprint, assign, attempt, submit, proctoring, chấm tự động/thủ công, kết quả.
- Assignment: bài tập, nộp bài, file, rubric, grading, AI feedback.
- Gradebook: sổ điểm, grade item, formula, import điểm, approval, audit, đồng bộ SIS.
- Digital Credential: certificate, badge, issue, revoke, verify, wallet, micro-credential.
- Attendance: live session, QR/OTP check-in, cập nhật thủ công, tổng hợp, eligibility.
- Enrollment: cohort, class section, self-enroll, invite, teacher assignment, bulk enroll, import, analytics.
- Integration Hub: hệ thống ngoài, mapping, conflict, webhook, event, sync job, push grade/attendance/progress.
- Learning Standards: SCORM, xAPI, LTI, external tool.
- OBE Accreditation: outcome, competency framework, mapping, matrix, coverage, achievement, report.
- Learning Analytics: dashboard, metric, risk, alert, summary.
- Survey Evaluation: form, campaign, response, export, improvement, evidence.
- Learning Community: forum, thread, reply, reaction, moderation, wiki, blog, group, reputation.
- AI Learning: ingest tài liệu, hỏi đáp, tóm tắt, quiz, flashcard, coach.
- Mobile Learning: bootstrap, offline sync, push subscription.

## Ví dụ gọi API

Lấy danh mục endpoint:

```bash
curl -H "Accept: application/json" \
  -H "X-Tenant-Code: vabis" \
  -H "X-API-Key: $ERALMS_API_KEY" \
  http://localhost/api/v1/integration-api/catalog
```

Tạo khóa học:

```http
POST /api/v1/courses
Idempotency-Key: 4fcd7d3c-6d59-46d1-a80d-50de58cc3e2a
```

```json
{
  "code": "CS101",
  "title": "Nhập môn lập trình",
  "level": "undergraduate",
  "course_type": "blended",
  "visibility": "private",
  "language": "vi",
  "estimated_hours": 45
}
```

Đồng bộ điểm sang SIS:

```http
POST /api/v1/integrations/push/grades
Idempotency-Key: grade-sync-20260606-001
```

```json
{
  "system_id": 1,
  "grades": [
    { "user_id": 15, "course_id": 3, "score": 8.5, "grade_item_code": "FINAL" }
  ]
}
```

## Webhook inbound

Hệ thống ngoài gửi webhook vào:

```http
POST /api/v1/integrations/webhooks/inbound/{systemCode}
X-ERALMS-Signature: <hmac-sha256>
Idempotency-Key: sis-student-updated-001
```

Payload mẫu:

```json
{
  "event_key": "sis.student.updated",
  "entity_type": "student",
  "entity_id": "S001",
  "idempotency_key": "sis-student-updated-001",
  "payload": {
    "student_code": "S001",
    "full_name": "Nguyễn Văn A",
    "email": "a@example.edu.vn"
  }
}
```

Chữ ký là HMAC-SHA256 của raw body bằng webhook secret đã cấu hình trong Integration Hub.

## Webhook outbound

EraLMS phát các event như:

- `lms.grade.synced`
- `lms.attendance.synced`
- `lms.progress.updated`
- `credential.issued`
- `certificate.revoked`

Mỗi lần gửi webhook có delivery log, số lần retry, trạng thái phản hồi và thời điểm retry tiếp theo.

## Nguyên tắc tích hợp

- Luôn dùng `Idempotency-Key` với request ghi dữ liệu.
- Không cache OpenAPI quá lâu trong giai đoạn phát triển; ưu tiên đọc lại `/integration-api/openapi.json`.
- Với import hoặc đồng bộ lớn, dùng sync job và đọc trạng thái qua nhóm endpoint `integrations/sync-jobs`.
- Luôn lưu `X-Request-Id` hai phía để đối soát log.
- Khi gặp conflict mapping, xử lý qua `integrations/mappings/resolve-conflict` trước khi retry sync.

## Chi tiết theo chức năng triển khai

Phần này mô tả theo hướng vận hành thực tế: mỗi nhóm chức năng có mục tiêu nghiệp vụ, đầu vào/đầu ra dữ liệu, luồng thao tác và điểm kiểm tra nghiệm thu.

### 1) Core IAM

| Chức năng | Mục tiêu nghiệp vụ | Dữ liệu nhập liệu | Kết quả đầu ra |
|---|---|---|---|
| Tenant | Tách dữ liệu theo khách hàng/chi nhánh | Mã tenant, thông tin pháp lý, trạng thái | Mọi nghiệp vụ đi qua tenant scope đúng |
| Campus/Academic Unit | Tổ chức hạ tầng đào tạo theo cơ sở và đơn vị | Danh sách cơ sở, khoa, phòng ban | Bộ lọc dữ liệu và báo cáo theo đơn vị hoạt động chuẩn |
| User/Role/Permission | Quản trị tài khoản, vai trò và quyền truy cập chức năng | Hồ sơ người dùng, nhóm quyền, action registry | Cô lập đúng màn hình theo vai trò |
| Audit log | Truy vết tất cả thao tác quan trọng | Actor, entity id, action | Log có thể lọc theo thời gian, người dùng, module |
| White-label | Tuỳ biến thương hiệu theo tenant | Logo, tên hệ thống, tham số cấu hình | Giao diện phản ánh thương hiệu khách hàng |

### 2) Quản trị khóa học & học liệu

| Chức năng | API/đường dẫn UI | Luồng kiểm thử |
|---|---|---|
| Khóa học | `/api/v1/courses`, `/courses` | Tạo khóa học, thêm section/component, draft -> review -> published, tạo phiên bản mới không phá vỡ version cũ |
| Course Studio | `/api/v1/courses/*/components`, `/courses/studio` | Soạn structure theo section/unit/component, preview trước publish, cấu hình rule hoàn thành |
| Kho học liệu | `/api/v1/repositories`, `/repository` | Upload tài liệu, phiên bản hóa, chia sẻ lại nội dung, xem lịch sử thay đổi |
| Video Platform | `/api/v1/videos`, `/videos`, `/videos/analytics` | Upload asset -> xử lý rendition -> theo dõi watch session và progress |
| Learning path | `/api/v1/learning-paths`, `/learning-path` | Thiết lập rule mở khóa, kiểm tra điều kiện thành phần trước khi mở nội dung tiếp theo |
| Mobile learning | PWA static + `/api/v1/mobile/*` (nếu bật) | Offline queue, đồng bộ sau khi có mạng, giữ được tiến độ cơ bản |

### 3) Khảo thí & bài tập

| Chức năng | Mục tiêu | Tiêu chí nghiệm thu |
|---|---|---|
| Ngân hàng câu hỏi | Chuẩn hóa nội dung đánh giá | Hỗ trợ nhiều loại question type, mapping outcome, import không mất dữ liệu cũ |
| Blueprint đề thi | Tạo cấu trúc đề theo chuẩn kiểm định nội bộ | Sinh đề theo section, độ khó, phân phối ma trận; random hóa trong giới hạn cho phép |
| Tạo/Assign thi | Triển khai ca thi theo lớp/đối tượng | Người học nhận đúng bài, timer và autosave hoạt động |
| Làm bài & chấm | Ghi attempt và kết quả đáng tin cậy | Save định kỳ, event log đầy đủ, chấm tự động đúng đáp án |
| Bài tập | Giao, thu, nộp, chấm có rubic | Deadline, nộp muộn có trạng thái, phản hồi chấm hiển thị cho học viên |
| Sổ điểm | Tổng hợp toàn bộ nguồn điểm | Điểm thành phần, công thức, import điểm, duyệt theo batch và audit |

### 4) Hành trình người học

| Chức năng | Dữ liệu đầu vào | Đầu ra vận hành |
|---|---|---|
| Enrollment | danh sách học viên, lớp, khóa, đợt ghi danh | Quyền truy cập đúng lớp/khoá và trạng thái học |
| Attendance | lịch học, QR/check-in dữ liệu | Tỷ lệ tham gia, điều kiện dự thi, điểm danh hợp lệ theo phiên |
| Community | forum, nhóm, bài post/reply | Tăng tương tác, gắn nhãn, theo dõi phản ứng và moderation |
| Survey | mẫu khảo sát, campaign, evidence | Tổng hợp phản hồi theo câu hỏi, phát sinh action cải tiến |
| Portfolio | portfolio item, timeline, public view | Hồ sơ học tập gắn với chứng chỉ và skill |
| Credential | issue badge/certificate, wallet | File/tài liệu xác thực, mã xác minh và log cấp/hủy |

### 5) Chuẩn, OBE và kiểm định

| Chức năng | Tương ứng luồng |
|---|---|
| Outcome management | Khai báo PLO/CLO, mapping giữa lesson, question, assignment |
| Outcome matrix | Tính coverage, phát hiện gap theo khóa/lớp/chương trình |
| Competency framework | Khung năng lực, skill record theo học viên |
| Accreditation reports | Gom evidence, xuất mẫu báo cáo kiểm định |
| SCORM/xAPI/LTI | Nạp chuẩn học liệu, ghi nhận event học tập ngoài |

### 6) Tích hợp doanh nghiệp

| Chức năng | Thao tác chuẩn hoá |
|---|---|
| SIS Integration | Đồng bộ users, class, enrollment, grade, attendance |
| Mapping | Chuẩn hóa khóa ngoại (student_code, course_code, class_code) |
| Sync job | Chạy theo lịch/ thủ công, quản lý retry và conflict |
| Webhook | Inbound và outbound, có chữ ký kiểm chứng + delivery log |
| API catalog | Cập nhật catalog/openapi khi thêm module mới |

### 7) Analytics & AI

| Chức năng | Ý nghĩa nghiệp vụ | Kết quả đo |
|---|---|---|
| Learning analytics | Theo dõi tiến độ, engagement, completion | Bảng tổng hợp theo khóa/lớp/đơn vị |
| AI document | Sinh flashcard, quiz, summary từ tài liệu | Học liệu được gắn lại nhanh hơn và đồng nhất |
| AI tutoring | Trợ giảng dựa trên nội dung course | Câu hỏi được lọc theo quyền truy cập |
| Risk alert | Nhận diện học viên có dấu hiệu rơi | Danh sách risk + lịch sử can thiệp |

### 8) Vận hành & Go-live

| Chức năng | Mục tiêu |
|---|---|
| Action check | Chuẩn hóa hành vi nghiệp vụ giữa môi trường test và production |
| System check | Kiểm tra migration, cache, queue, storage, health API |
| UAT | Chạy test case theo vai trò |
| Backup/rollback | Khôi phục được ít nhất 1 checkpoint sau triển khai |

### 9) Chi tiết từng chức năng (mẫu biên bản triển khai)

## 9.1 Core IAM

### 9.1.1 Tenant và tổ chức
- **Mục tiêu nghiệp vụ:** Tách bạch dữ liệu theo khách hàng/chi nhánh.
- **Đầu vào:** `tenant_code`, thông tin pháp lý, trạng thái tenant, cấu hình mặc định.
- **Đầu ra:** Tenant hoạt động trong phạm vi đúng dữ liệu, không lẫn tenant.
- **Luồng chính:** tạo tenant → cấu hình giới hạn → gán admin tenant → kiểm tra phân vùng dữ liệu.
- **Nghiệm thu:** tạo xóa sửa tenant được ghi log, dữ liệu tenant không giao thoa.

### 9.1.2 Campus / đơn vị học thuật
- **Mục tiêu nghiệp vụ:** Quản lý cơ sở, khoa, phòng ban theo cấp.
- **Đầu vào:** danh mục đơn vị, mã định danh, quan hệ cha-con.
- **Đầu ra:** danh sách bộ lọc theo campus/khoa/bộ môn hoạt động chính xác.
- **Luồng chính:** cấu hình đơn vị → mapping người dùng/khóa học → lọc dữ liệu theo scope.
- **Nghiệm thu:** truy xuất dashboard theo đúng đơn vị.

### 9.1.3 Người dùng, Role, Permission
- **Mục tiêu nghiệp vụ:** Cô lập quyền theo vai trò và màn hình.
- **Đầu vào:** hồ sơ user, role template, action registry.
- **Đầu ra:** quyền truy cập theo vai trò, tính nhất quán giữa UI/API.
- **Luồng chính:** tạo user → gán role → test quyền theo actor.
- **Nghiệm thu:** 3 vai trò mẫu (admin, giảng viên, learner) chỉ thấy menu được cấp.

### 9.1.4 Audit log
- **Mục tiêu nghiệp vụ:** Truy vết toàn bộ thao tác quan trọng.
- **Đầu vào:** actor, entity, action, metadata.
- **Đầu ra:** timeline log có thể lọc theo thời gian/module/user.
- **Luồng chính:** middleware ghi log → tra cứu theo khóa.
- **Nghiệm thu:** log không mất, toàn vẹn theo thứ tự thời gian.

## 9.2 Quản trị khóa học & học liệu

### 9.2.1 Quản trị khóa học
- **Mục tiêu:** Chuẩn hóa lifecycle course từ thiết kế đến phát hành.
- **Input:** thông tin khóa học, owner, danh mục, phân quyền.
- **Output:** khóa học có trạng thái `draft/review/published`.
- **Luồng:** tạo khóa học → thêm section/component → review → publish.
- **Nghiệm thu:** khóa học có version, owner, và template xuất bản đúng.

### 9.2.2 Course Studio
- **Mục tiêu:** Xây dựng nội dung theo section/component.
- **Input:** đề cương, CLO/PLO, học liệu đính kèm.
- **Output:** cấu trúc bài học có preview.
- **Luồng:** soạn content → thiết lập completion rule → preview → lock phiên bản.
- **Nghiệm thu:** preview phản ánh đúng nội dung đã lưu.

### 9.2.3 Kho học liệu
- **Mục tiêu:** Lưu trữ và tái sử dụng học liệu.
- **Input:** file, thẻ phân loại, version.
- **Output:** bản ghi version + quyền truy cập rõ ràng.
- **Luồng:** upload → phê duyệt → attach vào bài học.
- **Nghiệm thu:** thay thế file không làm vỡ nội dung đã dùng.

### 9.2.4 Video platform
- **Mục tiêu:** Ghi nhận và phân tích hành vi xem bài giảng.
- **Input:** asset video, metadata, policy bảo mật.
- **Output:** progress per user, watch event, playback URL.
- **Luồng:** upload → encode → publish → theo dõi session.
- **Nghiệm thu:** tiếp tục xem được sau gián đoạn.

### 9.2.5 Learning path
- **Mục tiêu:** Ràng buộc hành trình học cá nhân/lớp.
- **Input:** rule mở khóa, dependency.
- **Output:** tiến độ mở khóa theo điều kiện.
- **Luồng:** cấu hình rule → chạy engine mở khóa theo attempt/progress.
- **Nghiệm thu:** khóa học bị khóa khi chưa đạt điều kiện.

### 9.2.6 Mobile learning
- **Mục tiêu:** Dùng tốt trên mobile và offline.
- **Input:** manifest, service worker, manifest resource.
- **Output:** queue sync và offline progress.
- **Luồng:** tạo task offline → sync khi có mạng.
- **Nghiệm thu:** xem lại và học được phần cơ bản khi mất mạng.

## 9.3 Khảo thí & bài tập

### 9.3.1 Question bank
- **Mục tiêu:** Quản lý bộ câu hỏi đa dạng loại.
- **Input:** metadata câu hỏi, đáp án, mapping outcome.
- **Output:** ngân hàng câu hỏi tái sử dụng.
- **Luồng:** tạo/import → duyệt bản quyền/độ khó → dùng lại trong đề.
- **Nghiệm thu:** import không mất dữ liệu cũ, mapping outcome đúng.

### 9.3.2 Blueprint đề thi
- **Mục tiêu:** Sinh đề theo quy tắc nghiệp vụ.
- **Input:** ma trận đề, mức độ khó, số lượng mỗi loại.
- **Output:** đề thi theo section và luật random.
- **Luồng:** cấu hình seed → sinh đề → duyệt mẫu.
- **Nghiệm thu:** đề không trùng khi vượt ngưỡng cho phép.

### 9.3.3 Tạo/Assign thi
- **Mục tiêu:** Triển khai lịch thi đúng đối tượng.
- **Input:** kỳ thi, lớp, cohort, lịch.
- **Output:** ca thi, attempt, timer.
- **Luồng:** assign vào lớp → open window → nhận bài thi.
- **Nghiệm thu:** điểm danh nộp bài, autosave vận hành.

### 9.3.4 Chấm bài
- **Mục tiêu:** Nghiệm thu công bằng, đầy đủ.
- **Input:** kết quả trắc nghiệm + file tự luận.
- **Output:** điểm thành phần, feedback, lịch sử chỉnh sửa.
- **Luồng:** auto grade → manual grade → phê duyệt.
- **Nghiệm thu:** có trace thay đổi điểm.

### 9.3.5 Assignment
- **Mục tiêu:** Quản lý toàn bộ lifecycle bài tập.
- **Input:** đề bài, deadline, rubric.
- **Output:** submission, trạng thái, nhận xét.
- **Luồng:** giao bài → nộp file → chấm → public phản hồi.
- **Nghiệm thu:** nộp muộn đánh dấu đúng trạng thái.

### 9.3.6 Gradebook
- **Mục tiêu:** Tổng hợp điểm đa nguồn.
- **Input:** điểm quiz, bài tập, điểm thi, công thức.
- **Output:** sổ điểm lớp, audit, export.
- **Luồng:** import điểm → áp formula → approve → publish.
- **Nghiệm thu:** công thức hoạt động đúng theo rule.

## 9.4 Người học & Cộng đồng

### 9.4.1 Enrollment
- **Mục tiêu:** Quản trị hồ sơ học viên theo khóa/học phần.
- **Input:** danh sách học viên, cohort, class section.
- **Output:** trạng thái ghi danh.
- **Luồng:** import → duyệt → gán class.
- **Nghiệm thu:** không trùng, học viên đúng quyền truy cập.

### 9.4.2 Attendance
- **Mục tiêu:** Chứng thực tham gia học tập.
- **Input:** lịch, session, token check-in.
- **Output:** tỷ lệ tham dự, điều kiện dự thi.
- **Luồng:** QR/OTP/session check-in.
- **Nghiệm thu:** bảng tổng hợp tương thích với kỳ thi.

### 9.4.3 Community
- **Mục tiêu:** Tăng tương tác học tập.
- **Input:** thread, nhóm, phản hồi, reaction.
- **Output:** tương tác đã gắn moderation.
- **Luồng:** tạo post/reply → moderation → báo cáo.
- **Nghiệm thu:** spam và xung đột phản hồi có xử lý.

### 9.4.4 Survey
- **Mục tiêu:** Thu thập feedback nghiệp vụ.
- **Input:** form, campaign, câu trả lời.
- **Output:** báo cáo tổng hợp.
- **Luồng:** phát survey theo class/program → collect → xuất dữ liệu.
- **Nghiệm thu:** evidence lưu và action improvement.

### 9.4.5 Portfolio
- **Mục tiêu:** Hồ sơ năng lực học tập.
- **Input:** mục portfolio, evidence, skill record.
- **Output:** timeline portfolio, public view.
- **Luồng:** tạo portfolio → cập nhật skill → xuất bản.
- **Nghiệm thu:** liên kết chứng chỉ/đạt chuẩn.

### 9.4.6 Credential
- **Mục tiêu:** Phát hành chứng chỉ/badge số.
- **Input:** kết quả học tập, template, quyết định cấp.
- **Output:** mã xác thực/chứng chỉ PDF/wallet.
- **Luồng:** tạo yêu cầu -> duyệt -> issue/revoke.
- **Nghiệm thu:** verify endpoint xác nhận hợp lệ.

## 9.5 Chuẩn, OBE, kiểm định

### 9.5.1 Outcome management
- **Mục tiêu:** Liên kết đầu ra từ chương trình đến hoạt động.
- **Input:** PLO/CLO, ma trận, mapping.
- **Output:** báo cáo liên kết course/assessment/outcome.
- **Luồng:** define outcomes → map component → tính coverage.
- **Nghiệm thu:** coverage có thể giải thích nguồn dữ liệu.

### 9.5.2 Accreditation
- **Mục tiêu:** Chuẩn bị đợt đánh giá ngoài.
- **Input:** evidence list, report template, KPI.
- **Output:** gói minh chứng + lộ trình cải tiến.
- **Luồng:** gom minh chứng → sinh report → lưu bản phát hành.
- **Nghiệm thu:** xuất được báo cáo theo kỳ kiểm định.

### 9.5.3 SCORM/xAPI/LTI
- **Mục tiêu:** Tương thích nội dung bên ngoài.
- **Input:** package SCORM, statement xAPI, LTI launch.
- **Output:** event tracking chuẩn hóa.
- **Luồng:** import/registration → launch content → capture events.
- **Nghiệm thu:** tracking hoàn tất và truy xuất được.

## 9.6 Tích hợp

### 9.6.1 SIS Integration
- **Mục tiêu:** Đồng bộ nghiệp vụ cốt lõi với hệ thống trường.
- **Input:** mã học viên, lớp, điểm, điểm danh.
- **Output:** trạng thái sync và conflict.
- **Luồng:** mapping key → sync pull/push → conflict resolve.
- **Nghiệm thu:** sync lặp lại không nhân đôi dữ liệu.

### 9.6.2 API catalog / openapi
- **Mục tiêu:** Công bố endpoint mới nhất.
- **Input:** cấu hình module.
- **Output:** `/integration-api/catalog`, `/openapi.json`.
- **Luồng:** build update module → refresh catalog.
- **Nghiệm thu:** tài liệu phản ánh đúng endpoint đang active.

### 9.6.3 Webhook
- **Mục tiêu:** Gửi/nhận sự kiện hai chiều.
- **Input:** event_key, payload, chữ ký HMAC.
- **Output:** delivery log, status retry.
- **Luồng:** emit event → sign → gửi, nhận response, retry khi fail.
- **Nghiệm thu:** tối thiểu 1 lần retry và log đầy đủ.

### 9.6.4 Security integration
- **Mục tiêu:** Bảo mật toàn vẹn request ngoài.
- **Input:** X-API-Key, X-Request-Id, signature.
- **Output:** từ chối request không hợp lệ.
- **Luồng:** kiểm tra auth/rate/middleware.
- **Nghiệm thu:** audit request denied có lý do rõ.

## 9.7 AI & Analytics

### 9.7.1 AI document pipeline
- **Mục tiêu:** Sinh tài nguyên học tập tự động.
- **Input:** tài liệu gốc, chunk size, prompt policy.
- **Output:** flashcard/quiz/summary.
- **Luồng:** ingest → index → inference.
- **Nghiệm thu:** nội dung sinh ra có thể review và version.

### 9.7.2 AI tutor
- **Mục tiêu:** Hỗ trợ học tập theo bối cảnh.
- **Input:** câu hỏi học viên, context khóa học.
- **Output:** gợi ý học tiếp.
- **Luồng:** receive query → retrieve context → answer.
- **Nghiệm thu:** phản hồi có source trích dẫn nội bộ.

### 9.7.3 Learning analytics & risk
- **Mục tiêu:** Dự báo nguy cơ rời học.
- **Input:** attendance, progress, score trends.
- **Output:** danh sách risk + cảnh báo.
- **Luồng:** scheduled metrics → scoring → alert.
- **Nghiệm thu:** tỷ lệ cảnh báo trùng với review manual khoảng chấp nhận.

### 9.7.4 Reports & BI
- **Mục tiêu:** Tổng hợp KPI quản trị.
- **Input:** logs, điểm, outcome, chứng chỉ.
- **Output:** dashboard theo quyền.
- **Luồng:** extract → transform → publish.
- **Nghiệm thu:** truy xuất được theo tenant/campus/đơn vị.

## 9.8 Vận hành & Go-live

### 9.8.1 Action/system check
- **Mục tiêu:** Giảm sai lệch giữa test và production.
- **Input:** checklist role-based.
- **Output:** kết quả trước go-live.
- **Luồng:** chạy action, check log, fix sai lệch.
- **Nghiệm thu:** môi trường đã đạt ngưỡng green.

### 9.8.2 UAT
- **Mục tiêu:** Xác nhận nghiệp vụ với người dùng thật.
- **Input:** kịch bản theo persona.
- **Output:** biên bản UAT.
- **Luồng:** lập case → chạy → ghi lỗi → retest.
- **Nghiệm thu:** no critical blocker.

### 9.8.3 Backup/rollback
- **Mục tiêu:** Đảm bảo khôi phục sau triển khai.
- **Input:** lịch backup, checkpoint.
- **Output:** kế hoạch DR runbook.
- **Luồng:** backup dữ liệu + restore drill.
- **Nghiệm thu:** khôi phục thành công tại least 1 checkpoint.

### 10) Mẫu checklist theo chức năng (gợi ý)

```text
1. UI/UX: đúng route, đúng quyền, đúng ngôn ngữ
2. Dữ liệu: validate input, validate output, log đầy đủ
3. Luồng nghiệp vụ: xử lý trạng thái chuẩn (draft/review/published/closed)
4. Tương thích tích hợp: API, webhook, mapping, sync job
5. Nghiệm thu: điểm KPI đo được, dữ liệu audit và logs
6. Go-live: chạy được rollback, backup và hỗ trợ vận hành 24/7
```
