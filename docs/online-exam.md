# Online Exam / Quiz Attempt

Phân hệ Online Exam tạo bài kiểm tra từ Question Bank, quản lý enrollment, attempt/session, autosave, chấm tự động và nền tảng proctoring.

## Quy trình tạo bài

1. Tạo `exam` với `question_bank_id` hoặc `blueprint_id`.
2. Dùng `POST /api/v1/exams/{exam}/build-from-blueprint` để sinh cấu trúc đề.
3. Cấu hình thời gian, số lần làm, điểm đạt, shuffle câu hỏi/đáp án và chế độ hiện kết quả.
4. Publish exam rồi gán cho học viên/lớp bằng API assign.

## Làm bài

- Attempt lưu `session_uuid`, IP, user agent và device id.
- `exam_attempt_questions` lưu snapshot câu hỏi để đề không đổi khi câu hỏi gốc chỉnh sửa.
- Client chỉ nhận nội dung hiển thị; đáp án đúng không được trả xuống khi đang thi.
- Autosave ghi nhẹ vào `exam_answers.autosaved_at`.
- Submit dùng lock theo attempt để chống nộp trùng.

## Chấm điểm

- Trắc nghiệm, đúng/sai, điền khuyết, ghép đôi và sắp xếp được chấm tự động từ snapshot.
- Essay/case study chuyển sang hàng chờ chấm tay.
- Mọi override/chấm tay ghi `exam_grading_logs`.

## Proctoring

- Level 0: không giám sát.
- Level 1: tab switch, fullscreen exit, copy/paste.
- Level 2: webcam placeholder và metadata snapshot.
- Level 3: placeholder AI monitoring service.

Nếu suspicious score vượt ngưỡng, attempt chuyển `flagged`.
