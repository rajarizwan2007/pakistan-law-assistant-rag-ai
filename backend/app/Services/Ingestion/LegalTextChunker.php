<?php

namespace App\Services\Ingestion;

/**
 * Splits the text of an act into chunks that follow its legal structure.
 *
 * Each numbered section (or article) becomes one chunk, so a citation like
 * "PPC s.379" points at exactly one piece of text. Sections longer than the
 * token budget are split into overlapping windows that keep the same
 * section number and heading.
 *
 * Expected input: one printed line per text line, as produced by DocumentTextExtractor, e.g.
 *
 *     CHAPTER XVII
 *     OF OFFENCES AGAINST PROPERTY
 *     379. Punishment for theft. Whoever commits theft shall be punished ...
 *     either description for a term which may extend to three years ...
 */
class LegalTextChunker
{
    /**
     * "379. ...", "381A. ...", "[302. ...", "[ [ 478. ..." (a leading "[" marks an amended provision).
     * Also "[489A.Counterfeiting", where the source PDF omits the space after the number.
     */
    private const SECTION_START = '/^(?:\[\s*)*(\d{1,3})([A-Z]{0,3})\.(?:\s+(?=[\["“A-Z])|(?=[A-Z])|$)/u';

    /** Words that begin the rule itself; a heading never runs past them. */
    private const RULE_START = '(?:Whoever|Whenever|A person|Any person)\b';

    private const CHAPTER_START = '/^CHAPTER\s+([IVXLC]+[A-Z]?)\b\.?\s*(.*)$/u';

    /** Lines that begin a new paragraph rather than continuing the previous line. */
    private const PARAGRAPH_START = '/^(\(\w{1,4}\)|Explanation|Illustration|Exception|Provided|Note)/u';

    /**
     * A new section number may skip ahead (omitted sections, numbering gaps) but
     * not by more than this. Stops in-text numbers like "...under section\n420." from
     * being mistaken for a new section.
     */
    private const MAX_SECTION_JUMP = 25;

    /** Chunks with fewer words than this (e.g. "13. [Omitted]", sub-headings) are dropped. */
    private const MIN_WORDS = 8;

    private const CHARS_PER_TOKEN = 4;

    public function __construct(
        private readonly int $maxTokens = 500,
        private readonly int $overlapTokens = 50,
    ) {}

    /**
     * @return list<TextChunk>
     */
    public function chunk(string $text): array
    {
        $sections = $this->splitIntoSections($text);
        $chunks = [];

        foreach ($sections as $section) {
            $content = $this->joinLines($section['lines']);

            if (str_word_count($content) < self::MIN_WORDS) {
                continue;
            }

            $heading = $section['ref'] !== null ? $this->extractHeading($content) : $section['heading'];

            foreach ($this->window($content) as $part) {
                $chunks[] = new TextChunk(
                    sectionRef: $section['ref'],
                    heading: $heading,
                    chapter: $section['chapter'],
                    content: $part,
                    tokenCount: self::estimateTokens($part),
                );
            }
        }

        return $chunks;
    }

    public static function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / self::CHARS_PER_TOKEN);
    }

    /**
     * @return list<array{ref: ?string, heading: ?string, chapter: ?string, lines: list<string>}>
     */
    private function splitIntoSections(string $text): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text))));

        $sections = [];
        $current = null;
        $chapter = null;
        $lastNumber = null;
        $lastSuffix = '';

        for ($i = 0; $i < count($lines); $i++) {
            $line = $lines[$i];

            if (preg_match(self::CHAPTER_START, $line, $m)) {
                $title = $m[2];
                // The chapter title is usually on the next, all-caps line.
                if ($title === '' && isset($lines[$i + 1]) && mb_strtoupper($lines[$i + 1]) === $lines[$i + 1]) {
                    $title = $lines[++$i];
                }
                $chapter = trim("CHAPTER {$m[1]}".($title !== '' ? " — {$title}" : ''));

                if ($current !== null) {
                    $sections[] = $current;
                }
                // Text between a chapter heading and its first section (sub-headings like
                // "Of Theft") collects here; MIN_WORDS drops it when it is only a heading.
                $current = ['ref' => null, 'heading' => $chapter, 'chapter' => $chapter, 'lines' => []];

                continue;
            }

            if (preg_match(self::SECTION_START, $line, $m)
                && $this->isNextSection((int) $m[1], $m[2], $lastNumber, $lastSuffix)) {
                if ($current !== null) {
                    $sections[] = $current;
                }
                $current = ['ref' => $m[1].$m[2], 'heading' => null, 'chapter' => $chapter, 'lines' => [$line]];
                $lastNumber = (int) $m[1];
                $lastSuffix = $m[2];

                continue;
            }

            // Text before the first section (title, preamble) becomes an unnumbered chunk.
            $current ??= ['ref' => null, 'heading' => 'Preamble', 'chapter' => $chapter, 'lines' => []];
            $current['lines'][] = $line;
        }

        if ($current !== null) {
            $sections[] = $current;
        }

        return $sections;
    }

    private function isNextSection(int $number, string $suffix, ?int $lastNumber, string $lastSuffix): bool
    {
        if ($lastNumber === null) {
            return true;
        }

        if ($number === $lastNumber) {
            // 381 -> 381A -> 381B
            return strcmp($suffix, $lastSuffix) > 0;
        }

        return $number > $lastNumber && $number - $lastNumber <= self::MAX_SECTION_JUMP;
    }

    /**
     * "379. Punishment for theft. Whoever ..." -> "Punishment for theft".
     *
     * Headings end with "." or ";" (optionally inside a closing quote, as in
     * definitions: “Oath.”), followed by a separator such as "__", "-", "—" or "––".
     * A few headings have no punctuation at all; those end where the rule
     * itself starts ("Whoever ...", "Whenever ...", "A person ...").
     */
    private function extractHeading(string $content): ?string
    {
        $withoutNumber = preg_replace('/^(?:\[\s*)*\d{1,3}[A-Z]{0,3}\.\s*\[?/u', '', $content);

        $heading = null;
        if (preg_match('/^(.{3,200}?)[.;][”"]?(?:_+|[-—–]+)?(?:\s|$)/us', $withoutNumber, $m)) {
            $heading = $m[1];
        }

        // "375. Rape A person is said to commit rape if ..." has no full stop after the
        // heading, so the match above runs into the rule text; cut where the rule starts.
        if (preg_match('/^(.{3,200}?)\s+(?='.self::RULE_START.')/us', $heading ?? $withoutNumber, $m)) {
            $heading = $m[1];
        }

        return $heading === null ? null : trim(preg_replace('/\s+/u', ' ', $heading), " \t\n“”\"");
    }

    /**
     * Rejoin printed lines: wrapped lines continue the same paragraph, while
     * sub-clauses, explanations and illustrations start new ones.
     *
     * @param  list<string>  $lines
     */
    private function joinLines(array $lines): string
    {
        $text = '';

        foreach ($lines as $line) {
            if ($text === '') {
                $text = $line;
            } elseif (preg_match(self::PARAGRAPH_START, $line)) {
                $text .= "\n".$line;
            } else {
                $text .= ' '.$line;
            }
        }

        return $text;
    }

    /**
     * Split text that exceeds the token budget into overlapping windows on word boundaries.
     *
     * @return list<string>
     */
    private function window(string $content): array
    {
        if (self::estimateTokens($content) <= $this->maxTokens) {
            return [$content];
        }

        $maxChars = $this->maxTokens * self::CHARS_PER_TOKEN;
        $overlapChars = $this->overlapTokens * self::CHARS_PER_TOKEN;
        // Split on spaces but keep newlines attached, so paragraph breaks survive.
        $words = preg_split('/ +/u', $content);

        $windows = [];
        $start = 0;

        while ($start < count($words)) {
            $length = 0;
            $end = $start;

            while ($end < count($words) && ($length === 0 || $length + mb_strlen($words[$end]) + 1 <= $maxChars)) {
                $length += mb_strlen($words[$end]) + 1;
                $end++;
            }

            $windows[] = implode(' ', array_slice($words, $start, $end - $start));

            if ($end >= count($words)) {
                break;
            }

            // Step back so the next window repeats roughly overlapChars of context.
            $back = 0;
            $next = $end;
            while ($next > $start + 1 && $back < $overlapChars) {
                $next--;
                $back += mb_strlen($words[$next]) + 1;
            }
            $start = $next;
        }

        return $windows;
    }
}
