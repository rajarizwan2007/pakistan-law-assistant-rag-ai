<?php

namespace App\Console\Commands;

use App\Models\Source;
use Illuminate\Console\Command;

class LegalSourcesStatus extends Command
{
    protected $signature = 'law:status';

    protected $description = 'Show ingested legal sources and their embedding progress';

    public function handle(): int
    {
        $sources = Source::query()
            ->withCount([
                'chunks',
                'chunks as embedded_count' => fn ($query) => $query->whereNotNull('embedding'),
            ])
            ->orderBy('title')
            ->get();

        if ($sources->isEmpty()) {
            $this->warn('No sources ingested yet. Run: php artisan law:ingest <file> --title="..."');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Title', 'Short', 'Chunks', 'Embedded', 'Progress', 'Ingested'],
            $sources->map(fn (Source $source) => [
                $source->id,
                $source->title,
                $source->short_name,
                $source->chunks_count,
                $source->embedded_count,
                $source->chunks_count ? round(100 * $source->embedded_count / $source->chunks_count).'%' : '-',
                $source->updated_at->toDateTimeString(),
            ]),
        );

        return self::SUCCESS;
    }
}
