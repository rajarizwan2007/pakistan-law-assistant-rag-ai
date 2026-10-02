<?php

namespace Tests\Feature\Ingestion;

use App\Jobs\EmbedChunks;
use App\Models\Chunk;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

class IngestLegalTextTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $this->file = tempnam(sys_get_temp_dir(), 'act').'.txt';
        $this->writeAct('three years');

        config([
            'services.ollama.embed_model' => 'nomic-embed-text',
            'rag.embedding_dimensions' => 768,
            'rag.embed_batch_size' => 2,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);

        parent::tearDown();
    }

    private function writeAct(string $theftTerm): void
    {
        file_put_contents($this->file, <<<TXT
            378. Theft. Whoever, intending to take dishonestly any moveable property out of the possession of any person, moves that property, is said to commit theft.
            379. Punishment for theft. Whoever commits theft shall be punished with imprisonment for a term which may extend to {$theftTerm}, or with fine.
            380. Theft in dwelling house. Whoever commits theft in any building used as a human dwelling shall be punished with imprisonment up to seven years.
            TXT);
    }

    private function ingest(array $options = []): PendingCommand
    {
        return $this->artisan('law:ingest', [
            'file' => $this->file,
            '--title' => 'Pakistan Penal Code, 1860',
            '--short' => 'PPC',
            ...$options,
        ]);
    }

    public function test_it_stores_the_source_and_its_chunks_and_queues_embedding_batches(): void
    {
        Queue::fake();

        $this->ingest()->assertSuccessful();

        $source = Source::sole();
        $this->assertSame('PPC', $source->short_name);
        $this->assertSame(hash_file('sha256', $this->file), $source->checksum);
        $this->assertSame(['378', '379', '380'], $source->chunks()->orderBy('chunk_index')->pluck('section_ref')->all());

        // 3 chunks with a batch size of 2 -> 2 jobs
        Queue::assertPushed(EmbedChunks::class, 2);
    }

    public function test_running_it_again_on_an_unchanged_file_does_nothing(): void
    {
        Queue::fake();
        $this->ingest()->assertSuccessful();
        $firstIds = Chunk::pluck('id')->all();

        $this->ingest()->expectsOutputToContain('Unchanged since last ingestion')->assertSuccessful();

        $this->assertSame($firstIds, Chunk::pluck('id')->all());
        Queue::assertPushed(EmbedChunks::class, 2);
    }

    public function test_a_changed_file_replaces_the_previous_chunks(): void
    {
        Queue::fake();
        $this->ingest()->assertSuccessful();

        $this->writeAct('five years');
        $this->ingest()->assertSuccessful();

        $this->assertSame(1, Source::count());
        $this->assertSame(3, Chunk::count());
        $this->assertStringContainsString('five years', Chunk::firstWhere('section_ref', '379')->content);
    }

    public function test_sync_mode_stores_embeddings_from_ollama(): void
    {
        Http::fake([
            '*/api/embed' => function ($request) {
                $this->assertStringStartsWith('search_document: PPC s.', $request['input'][0]);

                return Http::response([
                    'embeddings' => array_fill(0, count($request['input']), array_fill(0, 768, 0.01)),
                ]);
            },
        ]);

        $this->ingest(['--sync' => true])->assertSuccessful();

        $this->assertSame(3, Chunk::whereNotNull('embedding')->count());
        $this->assertSame('nomic-embed-text', Chunk::first()->embedding_model);
    }

    public function test_it_rejects_embeddings_with_the_wrong_dimensions(): void
    {
        Http::fake(['*/api/embed' => Http::response(['embeddings' => [array_fill(0, 384, 0.1), array_fill(0, 384, 0.1)]])]);

        $this->expectExceptionMessage('returns 384 dimensions; the database expects 768');

        $this->ingest(['--sync' => true]);
    }

    public function test_it_fails_for_a_missing_file(): void
    {
        $this->artisan('law:ingest', ['file' => 'raw/does-not-exist.pdf', '--title' => 'X'])
            ->expectsOutputToContain('File not found')
            ->assertFailed();
    }
}
