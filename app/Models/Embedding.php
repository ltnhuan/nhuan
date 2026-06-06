<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Embedding extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'document_chunk_id', 'provider', 'model', 'dimensions', 'vector',
    ];

    protected $casts = [
        'vector' => 'array',
    ];

    public function chunk()
    {
        return $this->belongsTo(DocumentChunk::class, 'document_chunk_id');
    }
}
