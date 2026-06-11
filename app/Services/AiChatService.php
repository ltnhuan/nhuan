<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiChatService
{
    public function answer(string $question, string $context, string $assistantType): ?string
    {
        if (app()->environment('testing')) {
            return null;
        }

        if (config('eralms.ai.provider') !== 'openrouter') {
            return null;
        }

        $apiKey = config('eralms.ai.openrouter.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            return null;
        }

        try {
            $response = Http::timeout((int) config('eralms.ai.openrouter.timeout', 30))
                ->withToken($apiKey)
                ->withHeaders(array_filter([
                    'Content-Type' => 'application/json',
                    'HTTP-Referer' => config('eralms.ai.openrouter.referer'),
                    'X-Title' => config('eralms.ai.openrouter.title'),
                ]))
                ->post(rtrim((string) config('eralms.ai.openrouter.base_url'), '/').'/chat/completions', [
                    'model' => config('eralms.ai.openrouter.model'),
                    'temperature' => (float) config('eralms.ai.openrouter.temperature', 0.2),
                    'max_tokens' => (int) config('eralms.ai.openrouter.max_tokens', 700),
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->systemPrompt($assistantType),
                        ],
                        [
                            'role' => 'user',
                            'content' => "Cau hoi:\n{$question}\n\nHoc lieu truy hoi:\n{$context}",
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('OpenRouter chat request failed.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return data_get($response->json(), 'choices.0.message.content');
        } catch (\Throwable $exception) {
            Log::warning('OpenRouter chat request exception.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function systemPrompt(string $assistantType): string
    {
        $role = match ($assistantType) {
            'course_assistant' => 'AI Course Assistant cho giảng viên.',
            'pdf' => 'AI Reading Assistant trả lời theo PDF, slide và học liệu khóa học.',
            'video' => 'AI Video Coach trả lời theo transcript video hoặc live session.',
            'assignment' => 'AI Assignment Coach hỗ trợ phân tích đề, rubric và checklist tự kiểm.',
            'planner' => 'AI Study Planner lập kế hoạch học cá nhân theo tiến độ, deadline và mức rủi ro.',
            'quiz' => 'AI Quiz Coach tạo câu hỏi luyện tập và giải thích ngắn theo nguồn.',
            'flashcard' => 'AI Flashcard Coach chuyển học liệu thành thẻ ôn tập dễ nhớ.',
            'coach' => 'AI Learning Coach tư vấn bước học tiếp theo dựa trên tiến độ.',
            default => 'AI Tutor cho người học.',
        };

        return implode("\n", [
            $role,
            'Bạn là trợ lý học tập đa năng của EraLMS, thiết kế theo hướng human-centered AI.',
            'Trả lời bằng tiếng Việt rõ ràng, ngắn gọn, có cấu trúc, phù hợp sinh viên Gen Z.',
            'Chỉ dùng học liệu truy hồi trong prompt. Nếu học liệu không đủ, nói rõ thiếu nguồn nào và đề xuất cách bổ sung.',
            'Luôn tách phần: Trả lời chính, Bằng chứng từ học liệu, Bước tiếp theo.',
            'Khi có citation/chunk/source trong ngữ cảnh, nhắc lại tên nguồn hoặc ý chính của nguồn.',
            'Không làm thay bài nộp, bài kiểm tra đang chấm điểm hoặc hành vi gian lận. Với assignment/exam, chỉ đưa gợi ý, rubric, checklist và cách tự kiểm.',
            'Không hỏi lại nhiều câu. Chỉ hỏi 1 câu làm rõ nếu thật sự cần để trả lời đúng.',
            'Kết thúc bằng một hành động nhỏ: học bài nào, làm quiz nào, tạo flashcard nào hoặc kiểm tra lại điểm yếu nào.',
        ]);
    }
}
