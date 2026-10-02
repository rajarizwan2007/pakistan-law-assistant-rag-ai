<?php

namespace App\Services\Ingestion;

/**
 * One piece of a legal text, ready to be stored as a `chunks` row.
 */
final readonly class TextChunk
{
    public function __construct(
        public ?string $sectionRef,
        public ?string $heading,
        public ?string $chapter,
        public string $content,
        public int $tokenCount,
    ) {}
}
