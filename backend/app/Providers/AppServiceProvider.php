<?php

namespace App\Providers;

use App\Services\Ingestion\DocumentTextExtractor;
use App\Services\Ingestion\IngestionService;
use App\Services\Ingestion\LegalTextChunker;
use App\Services\Ollama\OllamaClient;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
