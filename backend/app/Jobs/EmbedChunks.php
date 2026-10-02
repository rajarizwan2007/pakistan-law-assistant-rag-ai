<?php

namespace App\Jobs;

use App\Models\Chunk;
use App\Services\Ollama\OllamaClient;
use App\Services\Ollama\OllamaException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Creates embeddings for a batch of chunks with one Ollama request.
 *
 * Chunks that already have an embedding are skipped, so a retried or
 * duplicated job does no extra work.
 */
class EmbedChunks implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds to wait before each retry (e.g. while Ollama loads the model). */
    public array $backoff = [10, 60];

    public int $timeout = 300;

    /**
     * @param  list<int>  $chunkIds
     */
    public function __construct(public array $chunkIds) {}

    public function handle(OllamaClient $ollama): void
    {
        $chunks = Chunk::with('source')
            ->whereIn('id', $this->chunkIds)
            ->whereNull('embedding')
            ->orderBy('id')
            ->get();

        if ($chunks->isEmpty()) {
            return;
        }

        $prefix = config('rag.document_prefix');
        $vectors = $ollama->embed($chunks->map(fn (Chunk $chunk) => $prefix.$chunk->embeddingText())->all());

        $dimensions = config('rag.embedding_dimensions');
        if (count($vectors[0]) !== $dimensions) {
            throw new OllamaException(
                "Model {$ollama->embedModel()} returns ".count($vectors[0])." dimensions; the database expects {$dimensions}."
            );
        }

        foreach ($chunks as $i => $chunk) {
            $chunk->update([
                'embedding' => $vectors[$i],
                'embedding_model' => $ollama->embedModel(),
            ]);
        }
    }
}
