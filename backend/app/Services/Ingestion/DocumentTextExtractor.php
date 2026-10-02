<?php

namespace App\Services\Ingestion;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

/**
 * Turns a source document (.pdf or .txt) into plain text, one line per printed line.
 *
 * For PDFs we use poppler's `pdftohtml -xml`, which reports the font size of every
 * text fragment. Official Pakistani law PDFs put amendment footnotes and footnote
 * markers (e.g. the "2" in "2[Pakistan]") in a smaller font than the body, so keeping
 * only body-sized text removes them without fragile regexes. Page footers
 * ("Page 30 of 179") are only slightly smaller than the body, so they are removed by pattern.
 */
class DocumentTextExtractor
{
    /** Fragments this many points smaller than the body font are dropped. */
    private const FONT_SIZE_TOLERANCE = 1;

    /** Fragments whose tops are within this many pixels are on the same line. */
    private const LINE_TOLERANCE_PX = 5;

    private const PAGE_FOOTER = '/^Page \d+ of \d+$/u';

    /** Look-alike characters some statute PDFs use, mapped to plain ASCII. */
    private const CHARACTER_FIXES = [
        "\u{037E}" => ';', // Greek question mark used as a semicolon
        "\u{00A0}" => ' ', // non-breaking space
        "\u{00AD}" => '-', // soft hyphen used as a visible hyphen ("qatl shibh-i-amd")
    ];

    public function extract(string $path, int $fromPage = 1, ?int $toPage = null): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf' => $this->extractPdf($path, $fromPage, $toPage),
            'txt' => strtr(file_get_contents($path), self::CHARACTER_FIXES),
            default => throw new InvalidArgumentException("Unsupported file type: {$path} (use .pdf or .txt)"),
        };
    }

    private function extractPdf(string $path, int $fromPage, ?int $toPage): string
    {
        $command = ['pdftohtml', '-xml', '-i', '-q', '-stdout', '-f', (string) $fromPage];
        if ($toPage !== null) {
            array_push($command, '-l', (string) $toPage);
        }
        $command[] = $path;

        $result = Process::timeout(300)->run($command);

        if ($result->failed()) {
            throw new RuntimeException('pdftohtml failed: '.$result->errorOutput());
        }

        return $this->xmlToText($result->output());
    }

    /**
     * Public for testing: converts pdftohtml XML output to body text.
     */
    public function xmlToText(string $xml): string
    {
        $dom = new DOMDocument;
        // pdftohtml output is not always perfectly valid XML; recover what we can.
        $dom->loadXML($xml, LIBXML_RECOVER | LIBXML_NOERROR | LIBXML_NOWARNING);

        $fontSizes = [];
        foreach ($dom->getElementsByTagName('fontspec') as $spec) {
            $fontSizes[$spec->getAttribute('id')] = (int) $spec->getAttribute('size');
        }

        $bodySize = $this->bodyFontSize($dom, $fontSizes);
        $lines = [];

        foreach ($dom->getElementsByTagName('page') as $page) {
            $fragments = [];

            foreach ($page->getElementsByTagName('text') as $text) {
                /** @var DOMElement $text */
                $size = $fontSizes[$text->getAttribute('font')] ?? 0;

                if ($size < $bodySize - self::FONT_SIZE_TOLERANCE || trim($text->textContent) === '') {
                    continue;
                }

                $fragments[] = [
                    'top' => (int) $text->getAttribute('top'),
                    'left' => (int) $text->getAttribute('left'),
                    'right' => (int) $text->getAttribute('left') + (int) $text->getAttribute('width'),
                    'text' => $text->textContent,
                ];
            }

            array_push($lines, ...$this->groupIntoLines($fragments));
        }

        return implode("\n", $lines);
    }

    /**
     * The font size that covers the most characters is the body text size.
     */
    private function bodyFontSize(DOMDocument $dom, array $fontSizes): int
    {
        $charsPerSize = [];

        foreach ($dom->getElementsByTagName('text') as $text) {
            $size = $fontSizes[$text->getAttribute('font')] ?? 0;
            $charsPerSize[$size] = ($charsPerSize[$size] ?? 0) + mb_strlen(trim($text->textContent));
        }

        arsort($charsPerSize);

        return (int) array_key_first($charsPerSize);
    }

    /**
     * @param  list<array{top: int, left: int, right: int, text: string}>  $fragments
     * @return list<string>
     */
    private function groupIntoLines(array $fragments): array
    {
        usort($fragments, fn ($a, $b) => [$a['top'], $a['left']] <=> [$b['top'], $b['left']]);

        $rows = [];
        foreach ($fragments as $fragment) {
            $last = array_key_last($rows);

            if ($last !== null && abs($fragment['top'] - $rows[$last][0]['top']) <= self::LINE_TOLERANCE_PX) {
                $rows[$last][] = $fragment;
            } else {
                $rows[] = [$fragment];
            }
        }

        $lines = [];
        foreach ($rows as $row) {
            usort($row, fn ($a, $b) => $a['left'] <=> $b['left']);

            $line = '';
            $previousRight = null;
            foreach ($row as $fragment) {
                // Add a space when fragments are visibly apart but neither side carries one.
                $gap = $previousRight === null ? 0 : $fragment['left'] - $previousRight;
                if ($gap > 2 && ! str_ends_with($line, ' ') && ! str_starts_with($fragment['text'], ' ')) {
                    $line .= ' ';
                }
                $line .= $fragment['text'];
                $previousRight = $fragment['right'];
            }

            $line = strtr($line, self::CHARACTER_FIXES);
            $line = trim(preg_replace('/\s+/u', ' ', $line));
            if ($line !== '' && ! preg_match(self::PAGE_FOOTER, $line)) {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}
