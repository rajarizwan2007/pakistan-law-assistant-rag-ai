<?php

namespace App\Services\Retrieval;

use App\Models\Chunk;
use App\Services\Ollama\OllamaClient;
use Illuminate\Support\Facades\DB;
use Pgvector\Laravel\Distance;

/**
 * Finds the legal provisions most relevant to a question.
 *
 *   question ──embed("search_query: …")──▶ vector
 *            ──pgvector cosine search (HNSW)──▶ candidates
 *            ──keep best window per section──▶ unique provisions
 *            ──similarity ≥ threshold──▶ top k
 */
class Retriever
{
    /**
     * Fetch this many times top_k candidates, because several windows of one long
     * section can occupy the top places and are collapsed into one result.
     */
    private const CANDIDATE_MULTIPLIER = 4;

    public function __construct(
        private readonly OllamaClient $ollama,
        private readonly int $topK,
        private readonly float $threshold,
        private readonly string $queryPrefix,
    ) {}

    /**
     * @param  list<int>|null  $sourceIds  limit the search to these acts
     */
    public function retrieve(string $question, ?array $sourceIds = null, ?int $topK = null, ?float $threshold = null): RetrievalResult
    {
        $topK ??= $this->topK;
        $threshold ??= $this->threshold;

        [$vector] = $this->ollama->embed([$this->queryPrefix.$question]);

        $candidates = DB::transaction(function () use ($vector, $sourceIds, $topK) {
            // With a WHERE filter, an HNSW scan can return fewer rows than asked for
            // (it filters after the index search). Iterative scans (pgvector ≥ 0.8)
            // keep searching the index until enough rows pass the filter.
            DB::statement('SET LOCAL hnsw.iterative_scan = relaxed_order');

            return Chunk::with('source')
                ->nearestNeighbors('embedding', $vector, Distance::Cosine)
                ->when($sourceIds, fn ($query) => $query->whereIn('source_id', $sourceIds))
                ->take($topK * self::CANDIDATE_MULTIPLIER)
                ->get();
        });

        $unique = $this->bestPerProvision($candidates->all());

        $relevant = array_values(array_filter($unique, fn (RetrievedChunk $r) => $r->score >= $threshold));
        $below = array_values(array_filter($unique, fn (RetrievedChunk $r) => $r->score < $threshold));

        return new RetrievalResult(
            question: $question,
            chunks: array_slice($relevant, 0, $topK),
            belowThreshold: array_slice($below, 0, $topK),
            threshold: $threshold,
        );
    }

    /**
     * Collapse windows of the same section into the best-scoring one, sorted by score.
     *
     * @param  list<Chunk>  $chunks
     * @return list<RetrievedChunk>
     */
    private function bestPerProvision(array $chunks): array
    {
        $best = [];

        foreach ($chunks as $chunk) {
            $score = 1 - $chunk->neighbor_distance;
            // Unnumbered chunks (preamble, chapter intros) are distinct provisions each.
            $key = $chunk->section_ref !== null
                ? "{$chunk->source_id}:{$chunk->section_ref}"
                : "chunk:{$chunk->id}";

            if (! isset($best[$key]) || $score > $best[$key]->score) {
                $best[$key] = new RetrievedChunk($chunk, $score);
            }
        }

        $results = array_values($best);
        usort($results, fn (RetrievedChunk $a, RetrievedChunk $b) => $b->score <=> $a->score);

        return $results;
    }
}
