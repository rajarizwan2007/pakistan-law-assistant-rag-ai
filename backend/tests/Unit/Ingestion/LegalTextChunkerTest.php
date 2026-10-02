<?php

namespace Tests\Unit\Ingestion;

use App\Services\Ingestion\LegalTextChunker;
use App\Services\Ingestion\TextChunk;
use PHPUnit\Framework\TestCase;

class LegalTextChunkerTest extends TestCase
{
    private const SAMPLE = <<<'TXT'
        THE PAKISTAN PENAL CODE
        Act No. XLV OF 1860
        CHAPTER XVII
        OF OFFENCES AGAINST PROPERTY
        Of Theft
        378. Theft. Whoever, intending to take dishonestly any moveable property out of the
        possession of any person without that person's consent, moves that property in order to such taking, is
        said to commit theft.
        Explanation 1.__ A thing so long as it is attached to the earth, not being moveable property, is
        not the subject of theft.
        379. Punishment for theft. Whoever commits theft shall be punished with imprisonment of
        either description for a term which may extend to three years, or with fine or with both.
        380. [* * * * *]
        [381A. Theft of a car or other motor vehicles.__ Whoever commits theft of a car or any other
        motor vehicle shall be punished with imprisonment which may extend to seven years.]
        TXT;

    /** @return list<TextChunk> */
    private function chunks(string $text = self::SAMPLE, int $maxTokens = 500, int $overlap = 50): array
    {
        return (new LegalTextChunker($maxTokens, $overlap))->chunk($text);
    }

    public function test_each_section_becomes_one_chunk_with_reference_and_heading(): void
    {
        $sections = array_values(array_filter($this->chunks(), fn (TextChunk $c) => $c->sectionRef !== null));

        $this->assertSame(['378', '379', '381A'], array_map(fn ($c) => $c->sectionRef, $sections));
        $this->assertSame(
            ['Theft', 'Punishment for theft', 'Theft of a car or other motor vehicles'],
            array_map(fn ($c) => $c->heading, $sections),
        );
    }

    public function test_sections_record_their_chapter(): void
    {
        $theft = $this->chunks()[1];

        $this->assertSame('CHAPTER XVII — OF OFFENCES AGAINST PROPERTY', $theft->chapter);
    }

    public function test_wrapped_lines_are_joined_and_explanations_start_a_new_paragraph(): void
    {
        $theft = $this->chunks()[1];

        $this->assertStringContainsString('out of the possession of any person', $theft->content);
        $this->assertStringContainsString("to commit theft.\nExplanation 1.", $theft->content);
    }

    public function test_text_before_the_first_section_becomes_a_preamble_chunk(): void
    {
        $text = "Preamble. WHEREAS it is expedient to provide a general Penal Code for Pakistan; It is enacted as follows:\n"
            .'1. Title and extent. This Act shall be called the Pakistan Penal Code and applies everywhere.';

        $chunks = $this->chunks($text);

        $this->assertNull($chunks[0]->sectionRef);
        $this->assertSame('Preamble', $chunks[0]->heading);
        $this->assertSame('1', $chunks[1]->sectionRef);
    }

    public function test_repealed_sections_and_bare_sub_headings_are_dropped(): void
    {
        $contents = array_map(fn (TextChunk $c) => $c->content, $this->chunks());

        $this->assertNotContains('380. [* * * * *]', $contents);
        $this->assertNotContains('Of Theft', $contents);
    }

    public function test_numbers_inside_the_text_are_not_mistaken_for_sections(): void
    {
        $text = "302. Punishment of qatl-i-amd. Whoever commits qatl-i-amd shall be punished as provided in section\n"
            ."2. Of the Code and the rules made under it, with death or imprisonment for life.\n"
            .'303. Qatl committed under compulsion. Whoever commits qatl under compulsion shall be punished.';

        $refs = array_map(fn (TextChunk $c) => $c->sectionRef, $this->chunks($text));

        $this->assertSame(['302', '303'], $refs);
    }

    public function test_headings_ending_with_a_semicolon_or_quote_are_extracted(): void
    {
        $text = "51. “Oath.” The word “oath” includes a solemn affirmation substituted by law for an oath.\n"
            .'52. Abetment of offence punishable with death if offence not committed; Whoever abets the commission of an offence shall be punished.';

        $headings = array_map(fn (TextChunk $c) => $c->heading, $this->chunks($text));

        $this->assertSame(['Oath', 'Abetment of offence punishable with death if offence not committed'], $headings);
    }

    public function test_long_sections_are_split_into_overlapping_windows(): void
    {
        $body = implode(' ', array_map(fn ($i) => "word{$i}", range(1, 400)));
        $chunks = $this->chunks("10. Long section. {$body}", maxTokens: 200, overlap: 20);

        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertSame('10', $chunk->sectionRef);
            $this->assertSame('Long section', $chunk->heading);
            $this->assertLessThanOrEqual(200, $chunk->tokenCount);
        }

        // The start of each window repeats the end of the previous one.
        $lastWordOfFirst = last(explode(' ', $chunks[0]->content));
        $this->assertStringContainsString($lastWordOfFirst, $chunks[1]->content);
    }
}
