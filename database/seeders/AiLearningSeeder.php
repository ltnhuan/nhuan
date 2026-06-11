<?php

namespace Database\Seeders;

use App\Models\AiConversation;
use App\Models\AiFlashcard;
use App\Models\AiGeneratedQuiz;
use App\Models\Course;
use App\Models\Document;
use App\Models\LmsUser;
use App\Models\Tenant;
use App\Services\AiDocumentPipelineService;
use App\Services\AiLearningPlatformService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AiLearningSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('code', 'VABIS')->firstOrFail();
        $courses = Course::query()->where('tenant_id', $tenant->id)->limit(20)->get();
        $pipeline = app(AiDocumentPipelineService::class);
        $ai = app(AiLearningPlatformService::class);

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

        $this->seedStudyCompanionWorkspace($tenant->id, $courses, $pipeline, $ai);
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

    private function seedStudyCompanionWorkspace(int $tenantId, $courses, AiDocumentPipelineService $pipeline, AiLearningPlatformService $ai): void
    {
        $defaultCourse = $courses->first(fn (Course $course) => Str::contains(Str::lower($course->code.' '.$course->title), 'hsk'))
            ?? $courses->first();

        $curatedDocuments = [
            [
                'title' => 'AI Study Companion Playbook',
                'source_type' => 'repository',
                'course_id' => $defaultCourse?->id,
                'content' => implode(' ', [
                    'Era AI Study Companion là trợ lý học tập đa năng cho sinh viên.',
                    'Trợ lý ưu tiên trả lời theo nguồn học liệu đã ingest, hiển thị citation và nói rõ khi thiếu dữ liệu.',
                    'Các chế độ chính gồm Ask with sources, Study planner, Quiz maker, Flashcards, Assignment coach và Video coach.',
                    'Mỗi câu trả lời tốt cần có trả lời chính, bằng chứng từ học liệu và bước học tiếp theo.',
                    'Trợ lý không làm thay bài nộp hoặc bài kiểm tra đang chấm điểm; trợ lý chỉ đưa rubric, checklist, ví dụ tương tự và câu hỏi tự kiểm.',
                    'Mục tiêu sản phẩm là tăng tốc ôn tập, giảm rỗng dữ liệu trong bài học và giúp người học biết việc cần làm ngay hôm nay.',
                ]),
            ],
            [
                'title' => 'HSK1 AI Coach - Kế hoạch 7 ngày',
                'source_type' => 'pdf',
                'course_id' => $defaultCourse?->id,
                'content' => implode(' ', [
                    'Ngày 1 ôn pinyin, thanh điệu và 20 từ vựng chào hỏi.',
                    'Ngày 2 luyện mẫu câu giới thiệu bản thân và hỏi tên, quốc tịch, lớp học.',
                    'Ngày 3 luyện nghe câu ngắn, đánh dấu từ khóa và tạo flashcard cho từ dễ nhầm.',
                    'Ngày 4 làm quiz từ vựng, sửa lỗi phát âm và ghi lại câu sai.',
                    'Ngày 5 luyện hội thoại theo tình huống ở lớp học, căn tin và ký túc xá.',
                    'Ngày 6 làm assignment nói 2 phút, tự kiểm theo rubric phát âm, từ vựng, ngữ pháp và lưu loát.',
                    'Ngày 7 làm mock test HSK1, xem lại câu sai và lập kế hoạch ôn điểm yếu.',
                ]),
            ],
            [
                'title' => 'Assignment Rubric - Dự án thực hành xưởng',
                'source_type' => 'assignment',
                'course_id' => $defaultCourse?->id,
                'content' => implode(' ', [
                    'Rubric bài tập gồm bốn tiêu chí: hiểu yêu cầu, quy trình thực hiện, bằng chứng sản phẩm và phản tư sau khi hoàn thành.',
                    'Mức xuất sắc cần mô tả rõ mục tiêu, giải thích lựa chọn công cụ, đính kèm hình ảnh hoặc log thao tác, và nêu bài học rút ra.',
                    'Mức đạt yêu cầu cần hoàn thành đúng đầu việc, có sản phẩm kiểm chứng và trả lời được câu hỏi tại sao làm theo quy trình đó.',
                    'Người học nên dùng AI Assignment Coach để phân tích đề, tạo checklist, kiểm tra thiếu bằng chứng và luyện phần phản tư.',
                    'AI không viết toàn bộ bài nộp thay người học; AI chỉ gợi ý cấu trúc, câu hỏi phản biện và tiêu chí tự đánh giá.',
                ]),
            ],
            [
                'title' => 'Video Transcript - Cách học với AI Tutor',
                'source_type' => 'video_transcript',
                'course_id' => $defaultCourse?->id,
                'content' => implode(' ', [
                    '00:00 Mở đầu: người học vào trang AI để chọn nguồn học liệu và đặt câu hỏi theo bài đang học.',
                    '01:30 Giảng viên nhấn mạnh câu hỏi tốt gồm mục tiêu, phần chưa hiểu và yêu cầu định dạng câu trả lời.',
                    '03:15 AI Tutor trả lời theo nguồn, không bịa, và đưa ra ví dụ thực tế.',
                    '05:20 Người học chuyển sang Quiz maker để kiểm tra nhớ bài trong 10 phút.',
                    '07:40 Người học tạo flashcard cho khái niệm khó và hẹn ôn lại vào cuối ngày.',
                    '09:00 Kết luận: dùng AI như huấn luyện viên học tập, không dùng để thay thế việc tự làm bài.',
                ]),
            ],
        ];

        $seededDocuments = collect();
        foreach ($curatedDocuments as $document) {
            $existing = Document::query()
                ->where('tenant_id', $tenantId)
                ->where('title', $document['title'])
                ->first();

            $seededDocuments->push($existing ?: $pipeline->ingest($document + [
                'tenant_id' => $tenantId,
                'mime_type' => $document['source_type'] === 'video_transcript' ? 'text/vtt' : 'text/plain',
                'metadata' => ['seeded' => true, 'workspace' => 'ai_study_companion'],
            ]));
        }

        $studentId = LmsUser::query()
            ->where('tenant_id', $tenantId)
            ->where('email', 'sv.lms@vabis.edu.vn')
            ->value('id') ?: LmsUser::query()
                ->where('tenant_id', $tenantId)
                ->where('user_type', 'student')
                ->value('id');

        $document = $seededDocuments->first();

        if ($document && ! AiConversation::query()->where('tenant_id', $tenantId)->where('question', 'Demo AI Companion: kế hoạch học 7 ngày')->exists()) {
            $ai->ask([
                'tenant_id' => $tenantId,
                'course_id' => $document->course_id,
                'document_id' => $document->id,
                'user_id' => $studentId,
                'assistant_type' => 'planner',
                'question' => 'Demo AI Companion: kế hoạch học 7 ngày',
            ]);
        }

        if ($document && ! AiGeneratedQuiz::query()->where('tenant_id', $tenantId)->where('title', 'Demo AI Quiz - Study Companion')->exists()) {
            $ai->generateQuiz([
                'tenant_id' => $tenantId,
                'course_id' => $document->course_id,
                'document_id' => $document->id,
                'title' => 'Demo AI Quiz - Study Companion',
                'types' => ['mcq', 'essay', 'fill_blank', 'matching'],
            ]);
        }

        $flashcards = [
            ['front' => 'AI Study Companion', 'back' => 'Trợ lý học tập đa năng trả lời theo nguồn, có citation và đề xuất bước học tiếp theo.', 'difficulty' => 'easy'],
            ['front' => 'Ask with sources', 'back' => 'Chế độ hỏi đáp dựa trên học liệu đã ingest, ưu tiên bằng chứng từ document chunks.', 'difficulty' => 'easy'],
            ['front' => 'Assignment Coach', 'back' => 'Hỗ trợ phân tích đề, rubric và checklist; không làm thay bài nộp của người học.', 'difficulty' => 'medium'],
            ['front' => 'Next best action', 'back' => 'Mỗi phản hồi nên kết thúc bằng một hành động nhỏ: học bài, làm quiz, tạo flashcard hoặc ôn điểm yếu.', 'difficulty' => 'medium'],
            ['front' => 'Citation-first AI', 'back' => 'Khi thiếu nguồn, trợ lý phải nói rõ giới hạn và đề xuất ingest thêm PDF, slide hoặc transcript.', 'difficulty' => 'hard'],
        ];

        foreach ($flashcards as $card) {
            AiFlashcard::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'front' => $card['front']],
                [
                    'course_id' => $document?->course_id,
                    'document_id' => $document?->id,
                    'back' => $card['back'],
                    'difficulty' => $card['difficulty'],
                    'metadata' => ['demo' => 'ai_study_companion'],
                ],
            );
        }
    }
}
