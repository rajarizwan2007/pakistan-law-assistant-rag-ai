<?php

namespace App\Services\Retrieval;

final readonly class RetrievalResult
{
    public function __construct(
        public string $question,
        /** @var list<RetrievedChunk> best match first, all at or above the threshold */
        public array $chunks,
        /** @var list<RetrievedChunk> closest matches that fell below the threshold (for debugging/UI) */
        public array $belowThreshold,
        public float $threshold,
    ) {}

    /**
     * False when nothing is similar enough to answer from: the assistant must refuse.
     */
    public function hasRelevantChunks(): bool
    {
        return $this->chunks !== [];
    }

    public function topScore(): ?float
    {
        return ($this->chunks[0] ?? $this->belowThreshold[0] ?? null)?->score;
    }
}
