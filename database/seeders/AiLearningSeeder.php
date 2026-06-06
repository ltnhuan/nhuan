<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Document;
use App\Models\Tenant;
use App\Services\AiDocumentPipelineService;
use Illuminate\Database\Seeder;

class AiLearningSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('code', 'VABIS')->firstOrFail();
        $courses = Course::query()->where('tenant_id', $tenant->id)->limit(20)->get();
        $pipeline = app(AiDocumentPipelineService::class);

        for ($i = 1; $i <= 100; $i++) {
            $course = $courses[($i - 1) % max(1, $courses->count())] ?? null;
            $source = ['pdf', 'ppt', 'docx', 'repository'][$i % 4];
            $title = 'AI học liệu mẫu '.$i;

            if (Document::query()->where('tenant_id', $tenant->id)->where('title', $title)->exists()) {
                continue;
            }

            $pipeline->ingest([
                'tenant_id' => $tenant->id,
                'course_id' => $course?->id,
                'source_type' => $source,
                'title' => $title,
                'mime_type' => match ($source) {
                    'pdf' => 'application/pdf',
                    'ppt' => 'application/vnd.ms-powerpoint',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    default => 'text/plain',
                },
                'content' => $this->documentContent($i, $course?->title ?? 'Khóa học EraLMS'),
                'metadata' => ['seeded' => true, 'pipeline' => ['chunk', 'embedding', 'vector_store']],
            ]);
        }

        foreach ($courses->take(5) as $index => $course) {
            $title = 'Transcript mẫu '.$course->code;
            if (Document::query()->where('tenant_id', $tenant->id)->where('title', $title)->exists()) {
                continue;
            }

            $pipeline->ingest([
                'tenant_id' => $tenant->id,
                'course_id' => $course->id,
                'source_type' => 'video_transcript',
                'title' => $title,
                'mime_type' => 'text/vtt',
                'content' => $this->transcriptContent($index + 1, $course->title),
                'metadata' => ['video_id' => 'DEMO-VIDEO-'.($index + 1), 'language' => 'vi'],
            ]);
        }
    }

    private function documentContent(int $index, string $courseTitle): string
    {
        return implode(' ', [
            "{$courseTitle} giới thiệu mục tiêu học tập, tiêu chí hoàn thành và năng lực đầu ra cho người học.",
            "Tài liệu {$index} tập trung vào khái niệm trọng tâm, ví dụ thực tế trong doanh nghiệp, và các lỗi thường gặp khi áp dụng.",
            'AI Tutor có thể giải thích lại bài học, tạo ví dụ thực tế, hỗ trợ ôn tập và trích dẫn từ học liệu khóa học.',
            'AI Summary sinh tóm tắt, key points và mindmap JSON để giáo viên rà soát nhanh nội dung.',
            'AI Quiz Generator tạo MCQ, Essay, Fill Blank và Matching dựa trên chunk học liệu đã embedding.',
            'AI Learning Coach theo dõi tiến độ, điểm số, thời gian học và đề xuất bài tiếp theo hoặc bài cần ôn tập.',
            'AI Outcome Analyzer liên kết CLO, PLO và competency để hỗ trợ OBE và kiểm định chất lượng.',
        ]);
    }

    private function transcriptContent(int $index, string $courseTitle): string
    {
        return implode(' ', [
            "00:00 Giảng viên mở đầu video {$index} của {$courseTitle} và nêu bối cảnh bài học.",
            '02:15 Nội dung chính giải thích quy trình học theo bước: đọc tài liệu, xem slide, làm quiz, nộp bài.',
            '05:40 Ví dụ thực tế mô tả cách người học dùng AI Assistant bên phải màn hình để hỏi PDF, hỏi video và hỏi assignment.',
            '09:10 Phần ôn tập nhấn mạnh việc dùng flashcard khó và quiz tổng hợp để cải thiện learning impact.',
        ]);
    }
}
