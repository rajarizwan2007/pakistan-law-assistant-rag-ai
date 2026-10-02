<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Retrieval\RetrievedChunk;
use App\Services\Retrieval\Retriever;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Retrieval only: returns the provisions most relevant to a question, without
 * generating an answer. Useful for inspecting what the LLM will be given.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request, Retriever $retriever): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:500'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
            'source_ids' => ['nullable', 'array'],
            'source_ids.*' => ['integer'],
        ]);

        $result = $retriever->retrieve(
            question: $validated['q'],
            sourceIds: $validated['source_ids'] ?? null,
            topK: $validated['limit'] ?? null,
        );

        $present = fn (RetrievedChunk $r) => [
            'chunk_id' => $r->chunk->id,
            'citation' => $r->citation(),
            'source' => $r->chunk->source->title,
            'chapter' => $r->chunk->chapter,
            'heading' => $r->chunk->heading,
            'content' => $r->chunk->content,
            'score' => round($r->score, 3),
        ];

        return response()->json([
            'data' => array_map($present, $result->chunks),
            'below_threshold' => array_map($present, $result->belowThreshold),
            'meta' => [
                'threshold' => $result->threshold,
                'has_relevant' => $result->hasRelevantChunks(),
            ],
        ]);
    }
}
