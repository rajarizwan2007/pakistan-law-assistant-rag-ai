<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');            // order within the source
            $table->string('chapter')->nullable();             // "CHAPTER XVII — OF OFFENCES AGAINST PROPERTY"
            $table->string('section_ref', 20)->nullable();     // "379", "381A"; null for preamble
            $table->string('heading', 500)->nullable();        // "Punishment for theft"
            $table->text('content');
            $table->unsignedInteger('token_count');
            // 768 = nomic-embed-text output size (config/rag.php). A different
            // embedding model needs a new migration and re-embedding all chunks.
            $table->vector('embedding', 768)->nullable();
            $table->string('embedding_model')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'chunk_index']);
            $table->index(['source_id', 'section_ref']);
        });

        // Full-text search column, kept up to date by Postgres itself (used for hybrid search later).
        DB::statement(<<<'SQL'
            ALTER TABLE chunks ADD COLUMN content_tsv tsvector
            GENERATED ALWAYS AS (
                to_tsvector('english', coalesce(heading, '') || ' ' || content)
            ) STORED
        SQL);
        DB::statement('CREATE INDEX chunks_content_tsv_index ON chunks USING gin (content_tsv)');

        // Approximate nearest-neighbour index for cosine similarity search.
        DB::statement('CREATE INDEX chunks_embedding_index ON chunks USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('chunks');
    }
};
