<?php

namespace App\Services;

class QuestionTypeValidator
{
    public function validate(string $type, array $payload): void
    {
        match ($type) {
            'single_choice' => $this->validateSingleChoice($payload['options'] ?? []),
            'multiple_choice' => $this->validateMultipleChoice($payload['options'] ?? []),
            'true_false' => $this->validateTrueFalse($payload['options'] ?? []),
            'fill_blank' => $this->validateFillBlank($payload['fill_blank_answers'] ?? [], $payload['stem'] ?? ''),
            'matching' => $this->validateMatching($payload['matching_pairs'] ?? []),
            'ordering' => $this->validateOrdering($payload['options'] ?? []),
            'audio', 'image', 'video' => $this->validateMediaQuestion($payload['metadata'] ?? []),
            'essay', 'case_study' => $this->validateEssayLike($payload),
            default => throw new \InvalidArgumentException("Loại câu hỏi {$type} chưa được hỗ trợ."),
        };
    }

    private function validateSingleChoice(array $options): void
    {
        $this->requireMinimumOptions($options, 2);
        if (collect($options)->where('is_correct', true)->count() !== 1) {
            throw new \InvalidArgumentException('Câu hỏi một đáp án phải có đúng 1 lựa chọn đúng.');
        }
    }

    private function validateMultipleChoice(array $options): void
    {
        $this->requireMinimumOptions($options, 2);
        if (collect($options)->where('is_correct', true)->count() < 1) {
            throw new \InvalidArgumentException('Câu hỏi nhiều đáp án phải có ít nhất 1 lựa chọn đúng.');
        }
    }

    private function validateTrueFalse(array $options): void
    {
        if (count($options) !== 2 || collect($options)->where('is_correct', true)->count() !== 1) {
            throw new \InvalidArgumentException('Câu hỏi đúng/sai cần đúng 2 lựa chọn và 1 đáp án đúng.');
        }
    }

    private function validateFillBlank(array $answers, string $stem): void
    {
        if ($answers === [] || ! str_contains($stem, '{{')) {
            throw new \InvalidArgumentException('Câu điền khuyết cần placeholder dạng {{blank_1}} và đáp án chấp nhận.');
        }
    }

    private function validateMatching(array $pairs): void
    {
        if (count($pairs) < 2) {
            throw new \InvalidArgumentException('Câu ghép đôi cần tối thiểu 2 cặp trái/phải.');
        }
    }

    private function validateOrdering(array $options): void
    {
        $this->requireMinimumOptions($options, 2);
    }

    private function validateMediaQuestion(array $metadata): void
    {
        if (empty($metadata['media_url'])) {
            throw new \InvalidArgumentException('Câu hỏi media cần media_url từ kho học liệu hoặc CDN.');
        }
    }

    private function validateEssayLike(array $payload): void
    {
        if (empty($payload['stem'])) {
            throw new \InvalidArgumentException('Câu tự luận hoặc case study cần nội dung đề bài.');
        }
    }

    private function requireMinimumOptions(array $options, int $minimum): void
    {
        if (count($options) < $minimum) {
            throw new \InvalidArgumentException("Câu hỏi cần tối thiểu {$minimum} lựa chọn.");
        }
    }
}
