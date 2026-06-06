# Question Bank Enterprise

Phân hệ Question Bank quản lý kho câu hỏi dùng chung theo tenant, khoa, môn, chương, CLO/PLO, Bloom và độ khó. Đây là nền tảng cho Online Exam ở prompt tiếp theo.

## Loại câu hỏi

- `single_choice`: đúng 1 đáp án.
- `multiple_choice`: có ít nhất 1 đáp án đúng.
- `true_false`: đúng 2 lựa chọn, 1 lựa chọn đúng.
- `essay`: tự luận, chấm theo rubric.
- `fill_blank`: stem có placeholder như `{{blank_1}}`.
- `matching`: tối thiểu 2 cặp trái/phải.
- `ordering`: sắp xếp theo thứ tự.
- `audio`, `image`, `video`: cần `metadata.media_url`.

## JSON Import

```json
[
  {
    "question_bank_id": 1,
    "code": "Q-DEMO-001",
    "question_type": "single_choice",
    "title": "Câu hỏi mẫu",
    "stem": "EraLMS là nền tảng gì?",
    "difficulty": "easy",
    "bloom_level": "remember",
    "default_score": 1,
    "options": [
      { "content": "LMS", "is_correct": true },
      { "content": "CRM", "is_correct": false }
    ]
  }
]
```

CSV tối thiểu gồm các cột: `code,question_type,title,stem,difficulty,bloom_level,correct_answer`.

## Blueprint

Blueprint sinh đề theo từng section. Engine lọc theo question bank, độ khó, Bloom, outcome và tránh trùng câu trong cùng preview. Nếu kho không đủ câu, API trả `warnings` để giảng viên bổ sung câu hỏi trước khi phát hành đề.
