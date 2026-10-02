<?php

namespace Tests\Unit\Ingestion;

use App\Services\Ingestion\DocumentTextExtractor;
use PHPUnit\Framework\TestCase;

class DocumentTextExtractorTest extends TestCase
{
    public function test_it_normalises_look_alike_characters(): void
    {
        $xml = '<pdf2xml><page number="1"><fontspec id="0" size="18"/>'
            ."<text top=\"10\" left=\"0\" width=\"400\" height=\"16\" font=\"0\">qatl shibh\u{00AD}i\u{00AD}amd\u{037E} House\u{00A0}breaking</text>"
            .'</page></pdf2xml>';

        $this->assertSame('qatl shibh-i-amd; House breaking', (new DocumentTextExtractor)->xmlToText($xml));
    }

    public function test_it_keeps_body_text_and_drops_footnotes_markers_and_page_footers(): void
    {
        // Shape of real `pdftohtml -xml` output: body text 18pt, footnotes 12pt, footer 17pt.
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <pdf2xml>
            <page number="1" top="0" left="0" height="1262" width="892">
                <fontspec id="0" size="17" family="Times" color="#000000"/>
                <fontspec id="1" size="18" family="Times" color="#000000"/>
                <fontspec id="2" size="12" family="Times" color="#000000"/>
                <text top="112" left="108" width="36" height="16" font="1">379. </text>
                <text top="112" left="162" width="500" height="16" font="1"><b>Punishment for theft.</b> Whoever commits theft shall be</text>
                <text top="133" left="108" width="300" height="16" font="1">punished as provided for</text>
                <text top="130" left="410" width="6" height="11" font="2">2</text>
                <text top="133" left="416" width="100" height="16" font="1">[Pakistan] law;</text>
                <text top="1089" left="112" width="164" height="11" font="2">2Subs. by Act XXVII of 2018, s.3.</text>
                <text top="1174" left="420" width="36" height="15" font="0">Page 135 of 179</text>
            </page>
            </pdf2xml>
            XML;

        $text = (new DocumentTextExtractor)->xmlToText($xml);

        $this->assertSame(
            "379. Punishment for theft. Whoever commits theft shall be\npunished as provided for [Pakistan] law;",
            $text,
        );
    }
}
