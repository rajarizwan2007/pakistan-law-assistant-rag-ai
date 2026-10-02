<?php

namespace App\Services\Ingestion;

use Illuminate\Support\Carbon;

/**
 * Descriptive information about an act, supplied when it is ingested.
 */
final readonly class SourceMetadata
{
    public function __construct(
        public string $title,
        public ?string $shortName = null,
        public string $unit = 'section',
        public ?int $year = null,
        public ?string $sourceUrl = null,
        public ?Carbon $retrievedAt = null,
    ) {}
}
