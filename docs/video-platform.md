# Video Learning Platform

Phân hệ Video Learning Platform xử lý upload, phát video qua storage/CDN và xác minh tiến độ xem ở server. Laravel không stream file video trực tiếp; API chỉ tạo URL phát có hạn và ghi nhận sự kiện học tập.

## Cấu hình

```env
VIDEO_STORAGE_DISK=public
VIDEO_CDN_URL=
VIDEO_SIGNED_URL_TTL=1800
VIDEO_DEFAULT_COMPLETION_PERCENT=90
VIDEO_MAX_SUSPICIOUS_SCORE_FOR_COMPLETION=50
```

Local dev dùng disk `public`. Staging/production có thể chuyển sang `s3` hoặc `minio` trong `config/filesystems.php`, sau đó đặt `VIDEO_CDN_URL` để URL trả về trỏ qua CDN.

## Quy trình xử lý

1. Giảng viên tải video qua `POST /api/v1/videos/upload`.
2. Hệ thống lưu file vào storage và tạo `video_assets`.
3. `POST /api/v1/videos/{id}/process` đưa job `ProcessVideoAsset` vào queue.
4. Nếu có FFmpeg, service tạo HLS metadata/rendition. Nếu chưa có FFmpeg, asset được đánh dấu `ready` với file gốc và metadata ghi rõ HLS đang chờ.
5. Player lấy `GET /api/v1/videos/{id}/playback-url` để phát qua storage/CDN.

## Tracking

Player gửi:

- `play`, `pause`, `seek`, `rate_change`, `ended`
- `heartbeat` mỗi 10-15 giây
- `tab_hidden`, `tab_visible`

`video_watch_events` là append-only. Dashboard chính đọc `video_progress_summaries` để tránh quét raw event log.

## Chống gian lận

- Seek từ vị trí thấp lên rất xa trong vài giây không được cộng thời gian xem.
- Heartbeat trùng vị trí liên tục bị đánh dấu duplicate.
- Playback rate lớn hơn 2.0 bị đánh suspicious.
- Khi tab hidden, heartbeat không cộng `watched_seconds`.
- Video chỉ hoàn thành khi đạt phần trăm yêu cầu, suspicious score dưới ngưỡng, và server xác minh.

## Tích hợp Learning Path

Khi summary đạt điều kiện hoàn thành, `LearningPathIntegrationService` gọi `CompletionEngineService` để mở khóa bài tiếp theo theo rule của Prompt 03. Client không được tự đánh dấu hoàn thành.
