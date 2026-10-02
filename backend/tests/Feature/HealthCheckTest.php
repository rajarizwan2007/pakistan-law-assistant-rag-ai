<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ollama.embed_model' => 'nomic-embed-text',
            'services.ollama.chat_model' => 'qwen2.5:3b',
        ]);
    }

    public function test_it_reports_ok_when_database_and_models_are_available(): void
    {
        Http::fake([
            '*/api/tags' => Http::response(['models' => [
                ['name' => 'nomic-embed-text:latest'],
                ['name' => 'qwen2.5:3b'],
            ]]),
        ]);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.database.ok', true)
            ->assertJsonPath('checks.ollama.missing', []);
    }

    public function test_it_reports_missing_models(): void
    {
        Http::fake([
            '*/api/tags' => Http::response(['models' => [['name' => 'nomic-embed-text:latest']]]),
        ]);

        $this->getJson('/api/health')
            ->assertStatus(503)
            ->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.ollama.missing', ['qwen2.5:3b']);
    }

    public function test_it_reports_degraded_when_ollama_is_unreachable(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->getJson('/api/health')
            ->assertStatus(503)
            ->assertJsonPath('checks.ollama.ok', false);
    }
}
