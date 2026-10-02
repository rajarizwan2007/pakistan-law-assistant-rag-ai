<?php

namespace App\Providers;

use App\Services\Ingestion\DocumentTextExtractor;
use App\Services\Ingestion\IngestionService;
use App\Services\Ingestion\LegalTextChunker;
use App\Services\Ollama\OllamaClient;
use App\Services\Retrieval\Retriever;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OllamaClient::class, fn () => new OllamaClient(
            baseUrl: config('services.ollama.base_url'),
            timeout: config('services.ollama.timeout'),
            embedModel: config('services.ollama.embed_model'),
        ));

        $this->app->bind(LegalTextChunker::class, fn () => new LegalTextChunker(
            maxTokens: config('rag.chunk_max_tokens'),
            overlapTokens: config('rag.chunk_overlap_tokens'),
        ));

        $this->app->bind(IngestionService::class, fn ($app) => new IngestionService(
            extractor: $app->make(DocumentTextExtractor::class),
            chunker: $app->make(LegalTextChunker::class),
            embedBatchSize: config('rag.embed_batch_size'),
        ));

        $this->app->bind(Retriever::class, fn ($app) => new Retriever(
            ollama: $app->make(OllamaClient::class),
            topK: config('rag.top_k'),
            threshold: config('rag.similarity_threshold'),
            queryPrefix: config('rag.query_prefix'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
