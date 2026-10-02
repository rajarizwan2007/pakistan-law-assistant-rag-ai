<?php

namespace App\Console\Commands;

use App\Services\Retrieval\RetrievedChunk;
use App\Services\Retrieval\Retriever;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SearchLegalText extends Command
{
    protected $signature = 'law:search
        {question : The question to search for}
        {--k= : Number of results (default: rag.top_k)}
        {--threshold= : Minimum similarity (default: rag.similarity_threshold)}
        {--source=* : Limit to source IDs (see law:status)}';

    protected $description = 'Show which legal provisions retrieval returns for a question, with similarity scores';

    public function handle(Retriever $retriever): int
    {
        $result = $retriever->retrieve(
            question: $this->argument('question'),
            sourceIds: $this->option('source') ? array_map('intval', $this->option('source')) : null,
            topK: $this->option('k') ? (int) $this->option('k') : null,
            threshold: $this->option('threshold') !== null ? (float) $this->option('threshold') : null,
        );

        $row = fn (RetrievedChunk $r, string $status) => [
            number_format($r->score, 3),
            $status,
            $r->citation(),
            Str::limit($r->chunk->heading ?? '', 60),
            Str::limit(preg_replace('/\s+/', ' ', $r->chunk->content), 70),
        ];

        $this->table(
            ['Score', '', 'Citation', 'Heading', 'Text'],
            [
                ...array_map(fn ($r) => $row($r, '✓'), $result->chunks),
                ...array_map(fn ($r) => $row($r, '✗ below'), $result->belowThreshold),
            ],
        );

        $result->hasRelevantChunks()
            ? $this->info(count($result->chunks)." relevant provision(s) at threshold {$result->threshold}.")
            : $this->warn("Nothing reaches the threshold {$result->threshold}: the assistant would refuse to answer.");

        return self::SUCCESS;
    }
}
