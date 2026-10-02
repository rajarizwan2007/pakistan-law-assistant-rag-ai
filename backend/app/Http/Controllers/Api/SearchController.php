<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chunk;
use App\Services\Ollama\OllamaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pgvector\Laravel\Distance;

/**
 * Preview of semantic search: returns the chunks closest in meaning to the query.
 * No threshold or de-duplication yet; that comes with the Retriever in Phase 3.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request, OllamaClient $ollama): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:500'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        [$vector] = $ollama->embed([config('rag.query_prefix').$validated['q']]);

        $chunks = Chunk::with('source')
            ->nearestNeighbors('embedding', $vector, Distance::Cosine)
            ->take($validated['limit'] ?? 5)
            ->get();

        return response()->json([
            'data' => $chunks->map(fn (Chunk $chunk) => [
                'chunk_id' => $chunk->id,
                'citation' => $chunk->source->cite($chunk->section_ref),
                'source' => $chunk->source->title,
                'chapter' => $chunk->chapter,
                'heading' => $chunk->heading,
                'content' => $chunk->content,
                'score' => round(1 - $chunk->neighbor_distance, 3),
            ]),
        ]);
    }
}
