<?php

namespace App\Services\Retrieval;

use App\Models\Chunk;

final readonly class RetrievedChunk
{
    public function __construct(
        public Chunk $chunk,
        /** Cosine similarity between question and chunk: 1 = same meaning, 0 = unrelated. */
        public float $score,
    ) {}

    public function citation(): string
    {
        return $this->chunk->source->cite($this->chunk->section_ref);
    }
}
