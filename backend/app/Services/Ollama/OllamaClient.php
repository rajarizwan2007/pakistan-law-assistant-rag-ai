<?php

namespace App\Services\Ollama;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the Ollama HTTP API.
 *
 * Chat calls are added in a later phase.
 */
class OllamaClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout,
        private readonly string $embedModel,
    ) {}

    public function embedModel(): string
    {
        return $this->embedModel;
    }

    /**
     * Embed several texts in one request.
     *
     * @param  list<string>  $texts
     * @return list<list<float>> one vector per input text, in the same order
     */
    public function embed(array $texts): array
    {
        $response = $this->request()
            ->post('/api/embed', [
                'model' => $this->embedModel,
                'input' => array_values($texts),
            ])
            ->throw();

        $embeddings = $response->json('embeddings');

        if (! is_array($embeddings) || count($embeddings) !== count($texts)) {
            throw new OllamaException('Ollama returned '.count($embeddings ?? []).' embeddings for '.count($texts).' inputs.');
        }

        return $embeddings;
    }

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
