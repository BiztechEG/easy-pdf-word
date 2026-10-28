<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Document;
use BiztechEG\EasyPdfWord\EasyPdfWord;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * The list() builder block, in PDF (the HTML) and in Word (document.xml and
 * numbering.xml).
 */
class ListBlockTest extends TestCase
{
    private static function make(): Document
    {
        return EasyPdfWord::create([
            'pdf' => ['drivers' => ['mpdf' => ['temp_dir' => sys_get_temp_dir().'/easy-pdf-word-tests/mpdf']]],
        ])->make()->locale('ar');
    }

    /** @return array<string, string> the Word file's parts by name */
    private static function parts(Document $document): array
    {
        $file = tempnam(sys_get_temp_dir(), 'easy-docx-test');
        file_put_contents($file, $document->word()->content());
        $zip = new ZipArchive;
        $zip->open($file);
        $parts = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $parts[$zip->getNameIndex($i)] = (string) $zip->getFromIndex($i);
        }

        $zip->close();
        unlink($file);

        return $parts;
    }

    /** @return string[] the list paragraphs of document.xml */
    private static function listParagraphs(string $xml): array
    {
        preg_match_all('#<w:p>(?:(?!</w:p>).)*?<w:numPr>.*?</w:p>#s', $xml, $m);

        return $m[0];
    }

    public function test_reads_strings_runs_and_sub_lists(): void
    {
        $blocks = (new DocumentBuilder)->list([
            'أول',
            ['text' => 'ثانٍ', 'bold' => true],
            [['text' => 'الدفع: ', 'bold' => true], '50%'],
            ['التسليم في:', ['الموقع أ', ['الموقع ب', ['داخلي']]]],
        ], ['ordered' => true])->blocks();

        $this->assertSame([
            ['runs' => [['text' => 'أول']], 'items' => []],
            ['runs' => [['text' => 'ثانٍ', 'bold' => true]], 'items' => []],
            ['runs' => [['text' => 'الدفع: ', 'bold' => true], ['text' => '50%']], 'items' => []],
            ['runs' => [['text' => 'التسليم في:']], 'items' => [
                ['runs' => [['text' => 'الموقع أ']], 'items' => []],
                ['runs' => [['text' => 'الموقع ب']], 'items' => [
                    ['runs' => [['text' => 'داخلي']], 'items' => []],
                ]],
            ]],
        ], $blocks[0]['items']);
        $this->assertSame(['ordered' => true], $blocks[0]['options']);
    }

    public function test_pdf_numbered_list_with_a_sub_list(): void
    {
        $html = self::make()->list(['أول', ['التسليم في:', ['الموقع أ', 'الموقع ب']]], ['ordered' => true])->toHtml();

        $this->assertStringContainsString(
            '<ol style="list-style-type: decimal; margin: 0 0 2mm 0;"><li>أول</li><li>التسليم في:<ol style="list-style-type: decimal; margin: 0;"><li>الموقع أ</li><li>الموقع ب</li></ol></li></ol>',
            $html,
        );
    }

    public function test_pdf_bullets_start_styles_and_arabic_digits(): void
    {
        $bullets = self::make()->list(['أ', ['ب', ['ج']]], ['color' => 'teal', 'size' => 9, 'space_after' => 4])->toHtml();
        $start = self::make()->list(['خمسة'], ['ordered' => true, 'start' => 5])->toHtml();
        $arabic = self::make()->numerals('arabic')->list(['أ'], ['ordered' => true])->toHtml();

        $this->assertStringContainsString('<ul style="list-style-type: disc; margin: 0 0 4mm 0; font-size: 9pt; color: teal;"><li>أ</li><li>ب<ul style="list-style-type: circle; margin: 0;"><li>ج</li></ul></li></ul>', $bullets);
        $this->assertStringContainsString('<ol start="5" style="list-style-type: decimal;', $start);
        $this->assertStringContainsString('<ol style="list-style-type: arabic-indic;', $arabic);
    }

    public function test_pdf_escapes_items_and_keeps_run_styles(): void
    {
        $html = self::make()->list(['<script>x</script>', [['text' => 'عريض', 'bold' => true], ['text' => 'رابط', 'link' => 'https://biztech.example']]])->toHtml();

        $this->assertStringContainsString('<li>&lt;script&gt;x&lt;/script&gt;</li>', $html);
        $this->assertStringContainsString('<span style="font-weight: bold;">عريض</span><a href="https://biztech.example"', $html);
    }

    public function test_mpdf_renders_lists(): void
    {
        $pdf = self::make()->numerals('arabic')
            ->list(['أول', ['ثانٍ', ['فرعي', ['أعمق']]]], ['ordered' => true, 'start' => 3])
            ->list(['نقطة'])
            ->pdf()->content();

        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_word_list_paragraphs_with_levels_and_rtl(): void
    {
        $parts = self::parts(self::make()->list(['أول', ['التسليم في:', ['الموقع أ']], 'ثالث'], ['ordered' => true]));
        $paragraphs = self::listParagraphs($parts['word/document.xml']);

        $this->assertCount(4, $paragraphs);
        $levels = array_map(fn ($p) => preg_match('/<w:ilvl w:val="(\d)"\/>/', $p, $m) ? (int) $m[1] : null, $paragraphs);
        $this->assertSame([0, 0, 1, 0], $levels);

        foreach ($paragraphs as $paragraph) {
            $this->assertMatchesRegularExpression('/<w:bidi(?: w:val="1")?\/>/', $paragraph);
        }

        $this->assertStringContainsString('الموقع أ', $paragraphs[2]);
        // The space after the list comes after its last item only.
        $this->assertStringContainsString('<w:spacing w:before="0" w:after="0"/>', $paragraphs[0]);
        $this->assertStringContainsString('w:after="113"', $paragraphs[3]);
    }

    public function test_word_numbering_per_list(): void
    {
        $parts = self::parts(self::make()->numerals('arabic')
            ->list(['أ', 'ب'], ['ordered' => true, 'start' => 5])
            ->list(['ج'], ['ordered' => true])
            ->list(['د']));
        $numbering = $parts['word/numbering.xml'];
        $numIds = array_map(fn ($p) => preg_match('/<w:numId w:val="(\d+)"\/>/', $p, $m) ? $m[1] : null, self::listParagraphs($parts['word/document.xml']));

        // Each list starts its own numbering.
        $this->assertSame($numIds[0], $numIds[1]);
        $this->assertCount(3, array_unique($numIds));
        $this->assertStringContainsString('<w:start w:val="5"/><w:numFmt w:val="hindiNumbers"/>', $numbering);
        $this->assertStringContainsString('<w:start w:val="1"/><w:numFmt w:val="hindiNumbers"/>', $numbering);
        $this->assertStringContainsString('<w:numFmt w:val="bullet"/>', $numbering);
        $this->assertStringContainsString('<w:lvlText w:val="%2."/>', $numbering);
        $this->assertStringContainsString('<w:lvlText w:val="◦"/>', $numbering);
    }

    public function test_word_list_items_keep_run_styles_and_digits(): void
    {
        $xml = self::parts(self::make()->numerals('arabic')->list([[['text' => 'الدفع: ', 'bold' => true], '50%']], ['color' => '#0F766E']))['word/document.xml'];
        $paragraph = self::listParagraphs($xml)[0];

        $this->assertStringContainsString('<w:b w:val="1"/>', $paragraph);
        $this->assertStringContainsString('<w:color w:val="0F766E"/>', $paragraph);
        $this->assertStringContainsString('٥٠%', $paragraph);
    }
}
