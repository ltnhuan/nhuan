<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Embedding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiDocumentPipelineService
{
    public function __construct(private readonly AiEmbeddingService $embedding)
    {
    }

    public function ingest(array $data): Document
    {
        return DB::transaction(function () use ($data) {
            $document = Document::query()->create([
                'tenant_id' => $data['tenant_id'],
                'course_id' => $data['course_id'] ?? null,
                'content_repository_item_id' => $data['content_repository_item_id'] ?? null,
                'source_type' => $data['source_type'],
                'title' => $data['title'],
                'mime_type' => $data['mime_type'] ?? null,
                'storage_path' => $data['storage_path'] ?? null,
                'status' => 'processing',
                'metadata' => $data['metadata'] ?? [],
            ]);

            foreach ($this->chunk((string) $data['content']) as $index => $content) {
                $chunk = $document->chunks()->create([
                    'tenant_id' => $document->tenant_id,
                    'course_id' => $document->course_id,
                    'chunk_index' => $index,
                    'content' => $content,
                    'token_count' => str_word_count(strip_tags($content)),
                    'metadata' => ['source_type' => $document->source_type],
                ]);

                Embedding::query()->create([
                    'tenant_id' => $document->tenant_id,
                    'document_chunk_id' => $chunk->id,
                    'provider' => config('eralms.ai.embedding_provider', 'local'),
                    'model' => config('eralms.ai.embedding_model', 'hash-embedding-v1'),
                    'dimensions' => 32,
                    'vector' => $this->embedding->embed($content),
                ]);
            }

            $document->update(['status' => 'ready', 'ingested_at' => now()]);

            return $document->fresh('chunks.embedding');
        });
    }

    public function chunk(string $content, int $maxWords = 120): array
    {
        $words = preg_split('/\s+/u', trim($content), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $chunks = [];

        foreach (array_chunk($words, $maxWords) as $part) {
            $text = trim(implode(' ', $part));
            if ($text !== '') {
                $chunks[] = Str::limit($text, 4000, '');
            }
        }

        return $chunks ?: [trim($content)];
    }
}
