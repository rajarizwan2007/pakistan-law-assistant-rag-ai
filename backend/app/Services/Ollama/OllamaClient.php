<?php

namespace App\Services\Ollama;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the Ollama HTTP API.
 *
 * Embedding and chat calls are added in later phases; for now this
 * only exposes what the health check needs.
 */
class OllamaClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout,
    ) {}

    /**
     * Names of the models currently pulled into Ollama (e.g. "qwen2.5:3b").
     *
     * @return list<string>
     */
    public function installedModels(): array
    {
        $response = $this->request()->timeout(5)->get('/api/tags')->throw();

        return array_column($response->json('models', []), 'name');
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout($this->timeout);
    }
}
