<?php

namespace Tests\Feature\Retrieval;

use App\Models\Chunk;
use App\Models\Source;
use App\Services\Retrieval\RetrievedChunk;
use App\Services\Retrieval\Retriever;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Uses hand-made 768-dimension vectors so cosine similarities are known exactly:
 * the question is the unit vector e0, and a chunk built from (a·e0 + b·e1) with
 * a² + b² = 1 has similarity a.
 */
class RetrieverTest extends TestCase
{
    use RefreshDatabase;

    private Source $ppc;

    protected function setUp(): void
    {
        parent::setUp();

        config(['rag.top_k' => 3, 'rag.similarity_threshold' => 0.5]);

        Http::fake(['*/api/embed' => Http::response(['embeddings' => [$this->vector(1.0)]])]);

        $this->ppc = Source::create(['title' => 'Pakistan Penal Code, 1860', 'short_name' => 'PPC', 'file_name' => 'ppc.pdf', 'checksum' => str_repeat('a', 64)]);
    }

    /** Vector with cosine similarity $similarity to e0. */
    private function vector(float $similarity): array
    {
        $v = array_fill(0, 768, 0.0);
        $v[0] = $similarity;
        $v[1] = sqrt(1 - $similarity ** 2);

        return $v;
    }

    private function chunk(Source $source, ?string $ref, float $similarity, int $index): Chunk
    {
        return Chunk::create([
            'source_id' => $source->id,
            'chunk_index' => $index,
            'section_ref' => $ref,
            'heading' => "Heading {$ref}",
            'content' => "Text of section {$ref} part {$index}",
            'token_count' => 10,
            'embedding' => $this->vector($similarity),
            'embedding_model' => 'nomic-embed-text',
        ]);
    }

    private function refs(array $chunks): array
    {
        return array_map(fn (RetrievedChunk $r) => $r->chunk->section_ref, $chunks);
    }

    public function test_it_returns_provisions_ranked_by_similarity_with_scores(): void
    {
        $this->chunk($this->ppc, '378', 0.7, 0);
        $this->chunk($this->ppc, '379', 0.9, 1);
        $this->chunk($this->ppc, '380', 0.8, 2);

        $result = app(Retriever::class)->retrieve('punishment for theft');

        $this->assertSame(['379', '380', '378'], $this->refs($result->chunks));
        $this->assertEqualsWithDelta(0.9, $result->chunks[0]->score, 0.001);
        $this->assertSame('PPC s.379', $result->chunks[0]->citation());
    }

    public function test_it_sends_the_question_with_the_search_query_prefix(): void
    {
        $this->chunk($this->ppc, '379', 0.9, 0);

        app(Retriever::class)->retrieve('punishment for theft');

        Http::assertSent(fn ($request) => $request['input'] === ['search_query: punishment for theft']);
    }

    public function test_windows_of_the_same_section_are_collapsed_into_the_best_one(): void
    {
        $this->chunk($this->ppc, '302', 0.95, 0);
        $this->chunk($this->ppc, '302', 0.90, 1);
        $this->chunk($this->ppc, '303', 0.60, 2);

        $result = app(Retriever::class)->retrieve('punishment for murder');

        $this->assertSame(['302', '303'], $this->refs($result->chunks));
        $this->assertSame(0, $result->chunks[0]->chunk->chunk_index);
    }

    public function test_chunks_below_the_threshold_are_reported_separately(): void
    {
        $this->chunk($this->ppc, '379', 0.9, 0);
        $this->chunk($this->ppc, '500', 0.3, 1);

        $result = app(Retriever::class)->retrieve('punishment for theft');

        $this->assertSame(['379'], $this->refs($result->chunks));
        $this->assertSame(['500'], $this->refs($result->belowThreshold));
        $this->assertTrue($result->hasRelevantChunks());
    }

    public function test_it_reports_no_relevant_chunks_when_nothing_reaches_the_threshold(): void
    {
        $this->chunk($this->ppc, '379', 0.4, 0);

        $result = app(Retriever::class)->retrieve('how do I register a company?');

        $this->assertFalse($result->hasRelevantChunks());
        $this->assertEqualsWithDelta(0.4, $result->topScore(), 0.001);
    }

    public function test_it_limits_results_to_top_k(): void
    {
        foreach (['1', '2', '3', '4', '5'] as $i => $ref) {
            $this->chunk($this->ppc, $ref, 0.9 - $i * 0.05, $i);
        }

        $this->assertCount(3, app(Retriever::class)->retrieve('question')->chunks);
        $this->assertCount(2, app(Retriever::class)->retrieve('question', topK: 2)->chunks);
    }

    public function test_it_can_be_limited_to_specific_sources(): void
    {
        $constitution = Source::create(['title' => 'Constitution of Pakistan, 1973', 'short_name' => 'Constitution', 'unit' => 'article', 'file_name' => 'c.pdf', 'checksum' => str_repeat('b', 64)]);
        $this->chunk($this->ppc, '379', 0.7, 0);
        $this->chunk($constitution, '25', 0.9, 0);

        $result = app(Retriever::class)->retrieve('equality', sourceIds: [$this->ppc->id]);

        $this->assertSame(['379'], $this->refs($result->chunks));
    }

    public function test_the_search_endpoint_returns_relevant_and_below_threshold_results(): void
    {
        $this->chunk($this->ppc, '379', 0.9, 0);
        $this->chunk($this->ppc, '500', 0.3, 1);

        $this->getJson('/api/search?q=punishment+for+theft')
            ->assertOk()
            ->assertJsonPath('data.0.citation', 'PPC s.379')
            ->assertJsonPath('below_threshold.0.citation', 'PPC s.500')
            ->assertJsonPath('meta.has_relevant', true)
            ->assertJsonPath('meta.threshold', 0.5);
    }
}
