<?php

namespace App\Services\Ingestion;

use App\Models\Source;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Loads one act into the database: extract text -> split into chunks -> store.
 *
 * Re-running it is safe. A source is identified by its title; if the file's
 * checksum has not changed, nothing happens. If the file changed, the source's
 * old chunks are replaced. Embeddings are created afterwards by EmbedChunks jobs.
 */
class IngestionService
{
    public function __construct(
        private readonly DocumentTextExtractor $extractor,
        private readonly LegalTextChunker $chunker,
        private readonly int $embedBatchSize,
    ) {}

    public function ingest(string $path, SourceMetadata $metadata, int $fromPage = 1, bool $force = false): IngestionResult
    {
        $checksum = hash_file('sha256', $path);
        $existing = Source::firstWhere('title', $metadata->title);

        if ($existing && $existing->checksum === $checksum && ! $force) {
            return new IngestionResult($existing, skipped: true);
        }

        $chunks = $this->chunker->chunk($this->extractor->extract($path, $fromPage));

        if ($chunks === []) {
            throw new RuntimeException("No text could be extracted from {$path}. Is --from-page correct?");
        }

        $source = DB::transaction(function () use ($existing, $metadata, $path, $checksum, $chunks) {
            $source = $existing ?? new Source;
            $source->fill([
                'title' => $metadata->title,
                'short_name' => $metadata->shortName,
                'unit' => $metadata->unit,
                'year' => $metadata->year,
                'source_url' => $metadata->sourceUrl,
                'retrieved_at' => $metadata->retrievedAt,
                'file_name' => basename($path),
                'checksum' => $checksum,
            ])->save();

            $source->chunks()->delete();

            $now = now();
            $rows = array_map(fn (TextChunk $chunk, int $index) => [
                'source_id' => $source->id,
                'chunk_index' => $index,
                'chapter' => $chunk->chapter,
                'section_ref' => $chunk->sectionRef,
                'heading' => $chunk->heading,
                'content' => $chunk->content,
                'token_count' => $chunk->tokenCount,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunks, array_keys($chunks));

            // Bulk insert in slices: one query per 200 rows instead of one per chunk.
            foreach (array_chunk($rows, 200) as $slice) {
                DB::table('chunks')->insert($slice);
            }

            return $source;
        });

        $chunkIds = $source->chunks()->orderBy('chunk_index')->pluck('id')->all();

        return new IngestionResult(
            source: $source,
            skipped: false,
            chunkCount: count($chunks),
            sectionCount: count(array_unique(array_filter(array_map(fn (TextChunk $c) => $c->sectionRef, $chunks)))),
            embeddingBatches: array_chunk($chunkIds, $this->embedBatchSize),
        );
    }
}
