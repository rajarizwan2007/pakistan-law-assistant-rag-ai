<?php

namespace App\Services\Ingestion;

use App\Models\Source;

final readonly class IngestionResult
{
    public function __construct(
        public Source $source,
        public bool $skipped,
        public int $chunkCount = 0,
        public int $sectionCount = 0,
        /** @var list<list<int>> chunk ids grouped into embedding batches */
        public array $embeddingBatches = [],
    ) {}
}
