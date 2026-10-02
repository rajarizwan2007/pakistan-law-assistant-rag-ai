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
    | Embedding prefixes
    |--------------------------------------------------------------------------
    |
    | nomic-embed-text is trained with task prefixes: stored passages use
    | "search_document: " and user questions use "search_query: ". Using the
    | right prefix on each side noticeably improves retrieval.
    |
    */

    'document_prefix' => 'search_document: ',

    'query_prefix' => 'search_query: ',

    /*
    |--------------------------------------------------------------------------
    | Chunking & embedding jobs
    |--------------------------------------------------------------------------
    |
    | Each legal section becomes one chunk. Sections longer than max_tokens are
    | split into windows that overlap by overlap_tokens so no sentence loses
    | its context. Token counts are estimated as characters / 4.
    |
    */

    'chunk_max_tokens' => (int) env('RAG_CHUNK_MAX_TOKENS', 500),

    'chunk_overlap_tokens' => (int) env('RAG_CHUNK_OVERLAP_TOKENS', 50),

    'embed_batch_size' => (int) env('RAG_EMBED_BATCH_SIZE', 16),

    /*
    |--------------------------------------------------------------------------
    | Data directory
    |--------------------------------------------------------------------------
    |
    | Relative file paths given to `php artisan law:ingest` are resolved here.
    | In Docker, the repository's data/ folder is mounted at /var/www/data.
    |
    */

    'data_path' => env('RAG_DATA_PATH', '/var/www/data'),

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
