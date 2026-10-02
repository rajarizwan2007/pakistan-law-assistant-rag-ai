<?php

namespace App\Console\Commands;

use App\Jobs\EmbedChunks;
use App\Services\Ingestion\IngestionService;
use App\Services\Ingestion\SourceMetadata;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class IngestLegalText extends Command
{
    protected $signature = 'law:ingest
        {file : PDF or TXT file; relative paths are resolved against the data directory (e.g. raw/ppc.pdf)}
        {--title= : Full title of the act, e.g. "Pakistan Penal Code, 1860" (identifies the source)}
        {--short= : Short name used in citations, e.g. PPC}
        {--unit=section : How provisions are numbered: section or article}
        {--year= : Year the act was enacted}
        {--url= : Where the file was downloaded from}
        {--retrieved= : Date the file was downloaded (YYYY-MM-DD)}
        {--from-page=1 : First PDF page of the actual text (skip the table of contents)}
        {--force : Re-ingest even if the file has not changed}
        {--sync : Create embeddings now instead of queueing jobs for the worker}';

    protected $description = 'Extract, chunk and store a legal text, then create its embeddings';

    public function handle(IngestionService $ingestion): int
    {
        $path = $this->resolvePath($this->argument('file'));
        if ($path === null) {
            $this->error("File not found: {$this->argument('file')}");

            return self::FAILURE;
        }

        if (! $this->option('title')) {
            $this->error('The --title option is required.');

            return self::FAILURE;
        }

        if (! in_array($this->option('unit'), ['section', 'article'], true)) {
            $this->error('--unit must be "section" or "article".');

            return self::FAILURE;
        }

        $metadata = new SourceMetadata(
            title: $this->option('title'),
            shortName: $this->option('short'),
            unit: $this->option('unit'),
            year: $this->option('year') ? (int) $this->option('year') : null,
            sourceUrl: $this->option('url'),
            retrievedAt: $this->option('retrieved') ? Carbon::parse($this->option('retrieved')) : null,
        );

        $this->info("Ingesting {$path} ...");
        $result = $ingestion->ingest($path, $metadata, (int) $this->option('from-page'), (bool) $this->option('force'));

        if ($result->skipped) {
            $this->warn("Unchanged since last ingestion: \"{$result->source->title}\". Use --force to re-ingest.");

            return self::SUCCESS;
        }

        $this->info("Stored {$result->chunkCount} chunks covering {$result->sectionCount} {$metadata->unit}s.");

        if ($this->option('sync')) {
            $this->withProgressBar($result->embeddingBatches, fn (array $ids) => EmbedChunks::dispatchSync($ids));
            $this->newLine();
            $this->info('Embeddings created.');
        } else {
            foreach ($result->embeddingBatches as $ids) {
                EmbedChunks::dispatch($ids);
            }
            $this->info('Queued '.count($result->embeddingBatches).' embedding jobs. Track progress with: php artisan law:status');
        }

        return self::SUCCESS;
    }

    private function resolvePath(string $file): ?string
    {
        foreach ([$file, rtrim(config('rag.data_path'), '/').'/'.ltrim($file, '/')] as $candidate) {
            if (is_file($candidate)) {
                return realpath($candidate);
            }
        }

        return null;
    }
}
