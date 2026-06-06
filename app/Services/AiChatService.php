<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiChatService
{
    public function answer(string $question, string $context, string $assistantType): ?string
    {
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
            'course_assistant' => 'AI Course Assistant cho giang vien.',
            'pdf' => 'AI Assistant tra loi theo PDF tai lieu khoa hoc.',
            'video' => 'AI Assistant tra loi theo transcript video.',
            'assignment' => 'AI Assistant ho tro bai tap va rubric.',
            default => 'AI Tutor cho nguoi hoc.',
        };

        return $role.' Tra loi bang tieng Viet, ngan gon, dung hoc lieu duoc cung cap, neu thieu nguon thi noi ro. Luon kem buoc on tap hoac vi du thuc te khi phu hop.';
    }
}
