<?php

namespace App\Console\Commands;

use App\Models\Source;
use App\Services\Retrieval\RetrievedChunk;
use App\Services\Retrieval\Retriever;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Measures retrieval quality against a hand-written question set.
 *
 * - Hit@1 / Hit@k: how often an expected section is ranked first / in the top k.
 * - MRR (mean reciprocal rank): average of 1/rank of the first correct hit (0 if missed).
 * - Threshold sweep: for each similarity threshold, how many in-scope questions would
 *   be answered and how many out-of-scope questions would be correctly refused.
 */
class EvaluateRetrieval extends Command
{
    protected $signature = 'law:eval
        {--file=resources/eval/ppc-retrieval.json : Question set, relative to the backend directory}
        {--k=5 : Rank cut-off for Hit@k}
        {--details : Show the result for every question}';

    protected $description = 'Evaluate retrieval quality and suggest a similarity threshold';

    public function handle(Retriever $retriever): int
    {
        $path = base_path($this->option('file'));
        if (! is_file($path)) {
            $this->error("Question set not found: {$path}");

            return self::FAILURE;
        }

        $set = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $source = Source::firstWhere('short_name', $set['source_short_name']);
        if (! $source) {
            $this->error("Source \"{$set['source_short_name']}\" is not ingested.");

            return self::FAILURE;
        }

        $k = (int) $this->option('k');
        $inScope = [];
        $outOfScope = [];
        $rows = [];

        $this->withProgressBar($set['questions'], function (array $item) use ($retriever, $source, $k, &$inScope, &$outOfScope, &$rows) {
            // Threshold 0: rank everything, so the sweep below can apply any threshold afterwards.
            $result = $retriever->retrieve($item['question'], [$source->id], $k, threshold: 0.0);
            $refs = array_map(fn (RetrievedChunk $r) => $r->chunk->section_ref, $result->chunks);
            $topScore = $result->topScore() ?? 0.0;

            if ($item['expected'] === []) {
                $outOfScope[] = $topScore;
                $rank = null;
            } else {
                $rank = null;
                foreach ($refs as $i => $ref) {
                    if (in_array($ref, $item['expected'], true)) {
                        $rank = $i + 1;
                        break;
                    }
                }
                $inScope[] = ['rank' => $rank, 'score' => $topScore];
            }

            $rows[] = [
                Str::limit($item['question'], 55),
                $item['expected'] === [] ? '(out of scope)' : implode(',', $item['expected']),
                implode(', ', array_slice(array_map(fn ($ref) => $ref ?? '-', $refs), 0, 3)),
                $item['expected'] === [] ? '' : ($rank === null ? '✗ miss' : ($rank === 1 ? '✓ 1' : "~ {$rank}")),
                number_format($topScore, 3),
            ];
        });
        $this->newLine(2);

        if ($this->option('details')) {
            $this->table(['Question', 'Expected', 'Top 3 retrieved', 'Rank', 'Top score'], $rows);
        }

        $this->reportRanking($inScope, $k);
        $this->reportThresholds($inScope, $outOfScope);

        return self::SUCCESS;
    }

    /**
     * @param  list<array{rank: ?int, score: float}>  $inScope
     */
    private function reportRanking(array $inScope, int $k): void
    {
        $n = count($inScope);
        $hit1 = count(array_filter($inScope, fn ($q) => $q['rank'] === 1));
        $hitK = count(array_filter($inScope, fn ($q) => $q['rank'] !== null));
        $mrr = array_sum(array_map(fn ($q) => $q['rank'] ? 1 / $q['rank'] : 0, $inScope)) / max($n, 1);

        $this->info("Ranking quality ({$n} in-scope questions)");
        $this->table(['Metric', 'Value'], [
            ['Hit@1', sprintf('%d/%d (%.0f%%)', $hit1, $n, 100 * $hit1 / max($n, 1))],
            ["Hit@{$k}", sprintf('%d/%d (%.0f%%)', $hitK, $n, 100 * $hitK / max($n, 1))],
            ['MRR', sprintf('%.3f', $mrr)],
        ]);
    }

    /**
     * @param  list<array{rank: ?int, score: float}>  $inScope
     * @param  list<float>  $outOfScope
     */
    private function reportThresholds(array $inScope, array $outOfScope): void
    {
        $inScores = array_column($inScope, 'score');
        $total = count($inScores) + count($outOfScope);

        $this->info('Top-score distribution');
        $this->table(['Questions', 'Min', 'Median', 'Max'], [
            ['In scope', ...$this->stats($inScores)],
            ['Out of scope', ...$this->stats($outOfScope)],
        ]);

        $sweep = [];
        for ($t = 0.50; $t <= 0.801; $t += 0.01) {
            $answered = count(array_filter($inScores, fn ($s) => $s >= $t));
            $refused = count(array_filter($outOfScope, fn ($s) => $s < $t));
            $sweep[] = ['t' => round($t, 2), 'answered' => $answered, 'refused' => $refused, 'correct' => $answered + $refused];
        }

        $bestCorrect = max(array_column($sweep, 'correct'));
        $best = array_values(array_filter($sweep, fn ($row) => $row['correct'] === $bestCorrect));
        // Several thresholds can tie; the middle of the tied range leaves the most margin on both sides.
        $suggested = $best[intdiv(count($best) - 1, 2)]['t'];

        $this->info('Threshold sweep (answer if top score ≥ threshold)');
        $this->table(
            ['Threshold', 'In-scope answered', 'Out-of-scope refused', 'Correct decisions'],
            array_map(fn ($row) => [
                number_format($row['t'], 2).($row['t'] === $suggested ? '  ◀ suggested' : ''),
                "{$row['answered']}/".count($inScores),
                "{$row['refused']}/".count($outOfScope),
                sprintf('%d/%d', $row['correct'], $total),
            ], array_filter($sweep, fn ($row) => fmod(round($row['t'] * 100), 2) == 0 || $row['t'] === $suggested)),
        );

        $this->line(sprintf(
            'Suggested threshold: <info>%.2f</info> (current RAG_SIMILARITY_THRESHOLD: %.2f)',
            $suggested,
            config('rag.similarity_threshold'),
        ));
    }

    /**
     * @param  list<float>  $values
     * @return array{string, string, string}
     */
    private function stats(array $values): array
    {
        if ($values === []) {
            return ['-', '-', '-'];
        }
        sort($values);
        $median = $values[intdiv(count($values), 2)];

        return [number_format($values[0], 3), number_format($median, 3), number_format(end($values), 3)];
    }
}
