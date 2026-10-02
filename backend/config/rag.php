<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Embedding dimensions
    |--------------------------------------------------------------------------
    |
    | Must match the output size of the embedding model (nomic-embed-text = 768)
    | and the vector(...) column size in the chunks migration. Changing the
    | embedding model means a new migration and re-embedding every chunk.
    |
    */

    'embedding_dimensions' => (int) env('RAG_EMBEDDING_DIMENSIONS', 768),

    /*
    |--------------------------------------------------------------------------
    | Retrieval
    |--------------------------------------------------------------------------
    |
    | top_k: number of chunks passed to the LLM as context.
    | similarity_threshold: minimum cosine similarity (0..1) for a chunk to count
    | as relevant. If no chunk passes, the assistant refuses to answer.
    |
    */

    'top_k' => (int) env('RAG_TOP_K', 5),

    'similarity_threshold' => (float) env('RAG_SIMILARITY_THRESHOLD', 0.55),

    'disclaimer' => 'Informational only — not legal advice. Consult a qualified lawyer.',

];
