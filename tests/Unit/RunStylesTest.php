<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\Document;
use BiztechEG\EasyPdfWord\DocumentFactory;
use BiztechEG\EasyPdfWord\EasyPdfWord;
use BiztechEG\EasyPdfWord\Support\Color;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Run styles in builder text: underline, strike, links, backgrounds, colour
 * names and fonts, in PDF (the HTML) and in Word (document.xml).
 */
class RunStylesTest extends TestCase
{
    /** @var string[] */
    private array $warnings = [];

    private function docs(array $config = []): DocumentFactory
    {
        return EasyPdfWord::create($config + [
            'pdf' => ['drivers' => ['mpdf' => ['temp_dir' => sys_get_temp_dir().'/easy-pdf-word-tests/mpdf']]],
        ], ['warn' => function (string $message) {
            $this->warnings[] = $message;
        }]);
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

    /** The <w:r> run holding the text. */
    private static function wordRun(string $xml, string $text): string
    {
        preg_match('#<w:r>(?:(?!</w:r>).)*?'.preg_quote($text, '#').'</w:t></w:r>#s', $xml, $m);

        return $m[0] ?? '';
    }

    public function test_colour_names_become_hex_for_word(): void
    {
        $this->assertSame('FF0000', Color::hex('red'));
        $this->assertSame('FF0000', Color::hex(' Red '));
        $this->assertSame('663399', Color::hex('rebeccapurple'));
        $this->assertSame('808080', Color::hex('grey'));
        $this->assertNull(Color::hex('reddish'));
        $this->assertNull(Color::hex('inherit'));
        $this->assertSame('#8080C0', Color::tint('navy', 0.5));
    }

    public function test_underline_strike_and_background_in_pdf(): void
    {
        $html = $this->docs()->make()->paragraph([
            ['text' => 'مسطر', 'underline' => true],
            ['text' => 'مشطوب', 'strike' => true],
            ['text' => 'الاثنان', 'underline' => true, 'strike' => true],
            ['text' => 'مظلل', 'background' => 'yellow'],
        ])->locale('ar')->toHtml();

        $this->assertStringContainsString('<span style="text-decoration: underline;">مسطر</span>', $html);
        $this->assertStringContainsString('<span style="text-decoration: line-through;">مشطوب</span>', $html);
        $this->assertStringContainsString('<span style="text-decoration: underline line-through;">الاثنان</span>', $html);
        $this->assertStringContainsString('<span style="background-color: yellow;">مظلل</span>', $html);
    }

    public function test_links_in_pdf(): void
    {
        $html = $this->docs()->make()->paragraph([
            ['text' => 'الموقع', 'link' => 'https://biztech.example/a?b=1&c=2'],
            ['text' => 'بريد', 'link' => 'mailto:sales@biztech.example', 'color' => 'green', 'bold' => true],
            ['text' => 'خطر', 'link' => 'javascript:alert(1)'],
            ['text' => 'ملف', 'link' => '/etc/passwd'],
        ])->table([[['text' => 'خلية', 'link' => 'https://biztech.example/cell']]])->locale('ar')->toHtml();

        $this->assertStringContainsString('<a href="https://biztech.example/a?b=1&amp;c=2" style="color: #0563C1; text-decoration: underline;">الموقع</a>', $html);
        $this->assertStringContainsString('<a href="mailto:sales@biztech.example" style="font-weight: bold; color: green; text-decoration: underline;">بريد</a>', $html);
        $this->assertStringContainsString('خطر', $html);
        $this->assertStringNotContainsString('javascript', $html);
        $this->assertStringNotContainsString('/etc/passwd', $html);
        $this->assertStringContainsString('<a href="https://biztech.example/cell"', $html);
    }

    public function test_underline_strike_background_and_links_in_word(): void
    {
        $parts = self::parts($this->docs()->make()->paragraph([
            ['text' => 'مسطر', 'underline' => true],
            ['text' => 'مشطوب', 'strike' => true],
            ['text' => 'مظلل', 'background' => 'yellow'],
            ['text' => 'أحمر', 'color' => 'red'],
            ['text' => 'الموقع', 'link' => 'https://biztech.example/a?b=1&c=2'],
            ['text' => 'خطر', 'link' => 'javascript:alert(1)'],
        ])->table([[['text' => 'خلية', 'link' => 'https://biztech.example/cell', 'background' => '#EEEEEE']]])->locale('ar'));
        $xml = $parts['word/document.xml'];
        $rels = $parts['word/_rels/document.xml.rels'];

        $this->assertStringContainsString('<w:u w:val="single"/>', self::wordRun($xml, 'مسطر'));
        $this->assertStringContainsString('<w:strike w:val="1"/>', self::wordRun($xml, 'مشطوب'));
        $this->assertMatchesRegularExpression('/<w:shd [^>]*w:fill="FFFF00"/', self::wordRun($xml, 'مظلل'));
        $this->assertStringContainsString('<w:color w:val="FF0000"/>', self::wordRun($xml, 'أحمر'));

        $this->assertMatchesRegularExpression('#<w:hyperlink r:id="(rId\d+)"[^>]*>(?:(?!</w:hyperlink>).)*الموقع#s', $xml);
        $this->assertStringContainsString('Target="https://biztech.example/a?b=1&amp;c=2" TargetMode="External"', $rels);
        $this->assertStringContainsString('Target="https://biztech.example/cell"', $rels);
        $this->assertStringNotContainsString('javascript', $rels.$xml);
        $this->assertStringContainsString('خطر', $xml);

        // The cell is shaded, not its text.
        $this->assertMatchesRegularExpression('/<w:tcPr>.*?w:fill="EEEEEE"/s', $xml);
        $this->assertDoesNotMatchRegularExpression('/<w:rPr>(?:(?!<\/w:rPr>).)*w:fill="EEEEEE"/s', $xml);
    }

    public function test_a_link_is_underlined_in_the_link_blue_in_word(): void
    {
        $xml = self::parts($this->docs()->make()->paragraph([
            ['text' => 'الموقع', 'link' => 'https://biztech.example'],
            ['text' => 'بريد', 'link' => 'mailto:a@biztech.example', 'color' => 'green'],
        ])->locale('ar'))['word/document.xml'];

        preg_match_all('#<w:hyperlink\b.*?</w:hyperlink>#s', $xml, $links);
        $this->assertCount(2, $links[0]);
        $this->assertStringContainsString('<w:color w:val="0563C1"/>', $links[0][0]);
        $this->assertStringContainsString('<w:u w:val="single"/>', $links[0][0]);
        $this->assertStringContainsString('<w:color w:val="008000"/>', $links[0][1]);
    }

    public function test_a_registered_font_in_pdf_and_an_unregistered_one_is_reported(): void
    {
        $html = $this->docs()->make()->paragraph([
            ['text' => 'نسخ', 'font' => 'Naskh'],
            ['text' => 'غير مسجل', 'font' => 'Tahoma'],
            ['text' => 'مرة ثانية', 'font' => 'tahoma'],
        ])->locale('ar')->toHtml();

        $this->assertStringContainsString("<span style=\"font-family: 'naskh';\">نسخ</span>", $html);
        $this->assertStringNotContainsString('Tahoma', $html);
        $this->assertSame(["easy-pdf-word: the font [Tahoma] is not registered, so the text is in the document's font."], $this->warnings);
    }

    public function test_a_registered_font_takes_its_word_name_or_the_word_font(): void
    {
        $docs = $this->docs([
            'word' => ['font' => 'Tahoma'],
            'fonts' => ['custom' => ['amiri' => ['regular' => __FILE__, 'word' => 'Amiri']]],
        ]);
        $xml = self::parts($docs->make()->paragraph([
            ['text' => 'نسخ', 'font' => 'naskh'],
            ['text' => 'أميري', 'font' => 'amiri'],
            ['text' => 'مثبت', 'font' => 'Simplified Arabic'],
        ])->locale('ar'))['word/document.xml'];

        $this->assertStringContainsString('w:ascii="Tahoma"', self::wordRun($xml, 'نسخ'));
        $this->assertStringContainsString('w:ascii="Amiri"', self::wordRun($xml, 'أميري'));
        $this->assertStringContainsString('w:ascii="Simplified Arabic"', self::wordRun($xml, 'مثبت'));
        $this->assertSame([], $this->warnings);
    }
}
