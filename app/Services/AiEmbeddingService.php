<?php

namespace App\Services;

class AiEmbeddingService
{
    public function embed(string $text, int $dimensions = 32): array
    {
        $vector = array_fill(0, $dimensions, 0.0);
        $tokens = $this->tokens($text);

        foreach ($tokens as $token) {
            $index = abs(crc32($token)) % $dimensions;
            $vector[$index] += 1.0;
        }

        $length = sqrt(array_sum(array_map(fn ($value) => $value * $value, $vector)));
        if ($length <= 0) {
            return $vector;
        }

        return array_map(fn ($value) => round($value / $length, 6), $vector);
    }

    public function similarity(array $left, array $right): float
    {
        $total = 0.0;
        $limit = min(count($left), count($right));

        for ($i = 0; $i < $limit; $i++) {
            $total += ((float) $left[$i]) * ((float) $right[$i]);
        }

        return round($total, 6);
    }

    private function tokens(string $text): array
    {
        preg_match_all('/[\pL\pN]+/u', mb_strtolower($text), $matches);

        return array_values(array_filter($matches[0] ?? [], fn ($token) => mb_strlen($token) > 1));
    }
}
