<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\Document;
use BiztechEG\EasyPdfWord\DocumentFactory;
use BiztechEG\EasyPdfWord\EasyPdfWord;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * The richText() builder block: editor HTML, sanitised, in the PDF (through
 * its HTML) and, as plain text for now, in Word.
 */
class RichTextTest extends TestCase
{
    /** @var string[] */
    private array $warnings = [];

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private function docs(array $config = []): DocumentFactory
    {
        return EasyPdfWord::create(array_replace_recursive([
            'pdf' => ['drivers' => ['mpdf' => ['temp_dir' => sys_get_temp_dir().'/easy-pdf-word-tests/mpdf']]],
        ], $config), ['warn' => function (string $message) {
            $this->warnings[] = $message;
        }]);
    }

    private function body(Document $document): string
    {
        $html = $document->toHtml();

        return trim(substr($html, strpos($html, '<body>') + 6, strpos($html, '</body>') - strpos($html, '<body>') - 6));
    }

    private static function wordXml(Document $document): string
    {
        $file = tempnam(sys_get_temp_dir(), 'easy-docx-test');
        file_put_contents($file, $document->word()->content());
        $zip = new ZipArchive;
        $zip->open($file);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($file);

        return $xml;
    }

    public function test_renders_sanitised_html_in_the_document_direction(): void
    {
        $body = $this->body($this->docs()->make()->locale('ar')->richText(
            '<p onclick="x()" class="a">السعر <strong>شامل</strong> <span style="text-decoration: underline; color: #DC2626">الضريبة</span></p>'
            .'<script>alert(1)</script><a href="javascript:alert(1)">رابط</a> <a href="https://biztech.example" target="_blank">موقع</a>'
        ));

        $this->assertSame(
            '<div dir="rtl" style="direction: rtl; text-align: right;">'
            .'<p>السعر <strong>شامل</strong> <span style="text-decoration: underline; color: #DC2626">الضريبة</span></p>'
            .'رابط <a href="https://biztech.example">موقع</a></div>',
            $body,
        );
    }

    public function test_left_to_right_documents_and_the_font_size(): void
    {
        $body = $this->body($this->docs()->make()->locale('en')->richText('<p>Terms</p>', ['font_size' => 9.5]));

        $this->assertSame('<div dir="ltr" style="direction: ltr; text-align: left; font-size: 9.5pt;"><p>Terms</p></div>', $body);
    }

    public function test_headings_take_the_builder_sizes_up_to_h4(): void
    {
        $body = $this->body($this->docs()->make()->locale('ar')->richText('<h1>أ</h1><h2>ب</h2><h3 style="text-align: center">ج</h3><h4>د</h4><h6>هـ</h6>'));

        $this->assertStringContainsString('<h1 style="font-weight: bold; margin: 0 0 3mm 0; page-break-after: avoid; font-size: 18pt; color: #0F766E;">أ</h1>', $body);
        $this->assertStringContainsString('<h2 style="font-weight: bold; margin: 0 0 3mm 0; page-break-after: avoid; font-size: 14pt;">ب</h2>', $body);
        // The HTML's own style comes last, so it wins.
        $this->assertStringContainsString('<h3 style="font-weight: bold; margin: 0 0 3mm 0; page-break-after: avoid; font-size: 12pt; text-align: center">ج</h3>', $body);
        $this->assertStringContainsString('<h4 style="font-weight: bold; margin: 0 0 3mm 0; page-break-after: avoid; font-size: 11pt;">د</h4>', $body);
        $this->assertStringContainsString('font-size: 11pt;">هـ</h4>', $body);
    }

    public function test_free_tables_take_the_builder_look_with_a_grid(): void
    {
        $body = $this->body($this->docs()->make()->locale('ar')->theme(['border' => '#D1D5DB'])->richText(
            '<table><tr><th>البند</th><th>ملاحظة</th></tr><tr><td colspan="2" style="text-align: center; width: 50%">عام</td><td></td></tr></table>'
        ));

        $this->assertStringContainsString('<table style="width: 100%; border-collapse: collapse; margin-bottom: 3mm;"><tr>', $body);
        $this->assertStringContainsString('<th style="padding: 2mm; vertical-align: top; text-align: right; border: 1px solid #D1D5DB; font-weight: bold; color: #FFFFFF; background-color: #0F766E;">البند</th>', $body);
        $this->assertStringContainsString('<td style="padding: 2mm; vertical-align: top; text-align: right; border: 1px solid #D1D5DB; text-align: center; width: 50%" colspan="2">عام</td>', $body);
        // An empty cell stays a pair of tags.
        $this->assertStringContainsString('<td style="padding: 2mm; vertical-align: top; text-align: right; border: 1px solid #D1D5DB;"></td>', $body);
    }

    public function test_quotes_lists_and_line_breaks(): void
    {
        $body = $this->body($this->docs()->make()->locale('ar')->richText(
            '<blockquote><p>سطر<br>ثانٍ</p></blockquote><ul><li><p>أول</p></li><li><p>ثانٍ</p><ol><li>فرعي</li></ol></li></ul><hr>'
        ));

        $this->assertStringContainsString('<blockquote style="margin: 0 0 3mm 0; padding-right: 4mm; border-right: 2px solid #E5E7EB;"><p>سطر<br/>ثانٍ</p></blockquote>', $body);
        $this->assertStringContainsString('<ul><li>أول</li><li>ثانٍ<ol><li>فرعي</li></ol></li></ul><hr/>', $body);
        $this->assertStringNotContainsString('</br>', $body);
    }

    public function test_converts_the_text_to_arabic_numerals_but_not_links(): void
    {
        $body = $this->body($this->docs()->make()->locale('ar')->numerals('arabic')->richText('<p>المدة 30 يوماً <a href="https://example.com/30">الرابط 15</a></p>'));

        $this->assertStringContainsString('المدة ٣٠ يوماً <a href="https://example.com/30">الرابط ١٥</a>', $body);
    }

    public function test_images_follow_the_document_image_rules(): void
    {
        $body = $this->body($this->docs()->make()->locale('ar')->richText(
            '<p><img src="data:image/png;base64,'.self::PNG.'" width="2000" height="1000"></p>'
            .'<p><img src="https://example.com/a.png"><img src="data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg"><image href="file:///etc/passwd"/></svg>').'"></p>'
        ));

        // Capped at the text width of an A4 page with 15 mm margins: 180 mm is 680 px.
        $this->assertStringContainsString('<img src="data:image/png;base64,'.self::PNG.'" width="680" height="340"/>', $body);
        $this->assertSame(1, substr_count($body, '<img'));
        $this->assertStringContainsString('images.remote', $this->warnings[0]);
        $this->assertStringStartsWith('easy-pdf-word: rich text: an image was dropped [https://example.com/a.png]', $this->warnings[1]);
        $this->assertStringStartsWith('easy-pdf-word: rich text: an image was dropped [data:image/svg+xml', end($this->warnings));
    }

    public function test_images_on_the_server_are_refused_even_in_allowed_folders(): void
    {
        $folder = sys_get_temp_dir().'/easy-pdf-word-tests/rich-text-images';
        @mkdir($folder, 0777, true);
        file_put_contents($folder.'/stamp.png', base64_decode(self::PNG));
        $docs = $this->docs(['images' => ['paths' => [$folder]]]);

        $this->assertStringContainsString('<img', $this->body($docs->make()->image($folder.'/stamp.png')));
        $body = $this->body($docs->make()->richText('<p>نص<img src="'.$folder.'/stamp.png"><img src="file://'.$folder.'/stamp.png"></p>'));

        $this->assertStringNotContainsString('<img', $body);
        $this->assertCount(2, $this->warnings);
        $this->assertStringContainsString('rich text: an image was dropped', $this->warnings[0]);
    }

    public function test_images_are_capped_at_the_page_text_width(): void
    {
        $body = $this->body($this->docs()->make()->locale('ar')->paper('A5')->margins(10)->richText(
            '<img src="data:image/png;base64,'.self::PNG.'" width="2000">'
        ));

        // A5 is 148 mm wide; less two 10 mm margins, 128 mm is 483 px.
        $this->assertStringContainsString('width="483"', $body);
    }

    public function test_empty_html_adds_nothing(): void
    {
        $this->assertSame('<p style="">قبل</p>', $this->body($this->docs()->make()->locale('ar')->richText('<script>x</script>  ')->paragraph('قبل')));
    }

    public function test_reports_cuts_through_warn(): void
    {
        $rows = str_repeat('<tr><td>x</td></tr>', 201);
        $this->body($this->docs()->make()->richText('<table>'.$rows.'</table>'));

        $this->assertCount(1, $this->warnings);
        $this->assertStringStartsWith('easy-pdf-word: rich text was cut:', $this->warnings[0]);
    }

    public function test_renders_a_pdf_with_mpdf(): void
    {
        $pdf = $this->docs()->make()->locale('ar')
            ->richText('<h2>الشروط</h2><ol><li><p>الدفع <b>مقدماً</b></p></li></ol><table><tr><th>أ</th></tr><tr><td>ب</td></tr></table>')
            ->pdf();

        $this->assertStringStartsWith('%PDF', $pdf->content());
    }

    public function test_word_gets_the_text_as_paragraphs(): void
    {
        $xml = self::wordXml($this->docs()->make()->locale('ar')->richText(
            '<h2>الشروط</h2><p>السعر <b>شامل</b> &lt;script&gt;<br>سطر ثانٍ</p><ul><li>أول</li><li>ثانٍ</li></ul>'
            .'<table><tr><td>أ</td><td>ب</td></tr></table><script>alert(1)</script><img src="data:image/png;base64,'.self::PNG.'">',
            ['font_size' => 10],
        ));

        preg_match_all('/<w:p>.*?<\/w:p>/s', $xml, $paragraphs);
        $texts = array_map(fn ($p) => implode('|', array_map('html_entity_decode', preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $p, $m) ? $m[1] : [])), $paragraphs[0]);

        $this->assertSame(['الشروط', 'السعر شامل <script>|سطر ثانٍ', 'أول', 'ثانٍ', 'أ ب'], array_values(array_filter($texts)));
        $this->assertStringContainsString('<w:sz w:val="20"/>', $xml);
        $this->assertStringNotContainsString('alert', $xml);
        $this->assertStringNotContainsString('<w:drawing>', $xml);
    }
}
