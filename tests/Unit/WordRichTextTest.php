<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\Document;
use BiztechEG\EasyPdfWord\DocumentFactory;
use BiztechEG\EasyPdfWord\EasyPdfWord;
use PhpOffice\PhpWord\Shared\Html;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use ZipArchive;

/**
 * The richText() builder block in Word files, read from the .docx parts.
 */
class WordRichTextTest extends TestCase
{
    /** @var string[] */
    private array $warnings = [];

    private function docs(): DocumentFactory
    {
        return EasyPdfWord::create([], ['warn' => function (string $message) {
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

    private function body(string $html, string $locale = 'ar', array $options = []): string
    {
        return self::parts($this->docs()->make()->locale($locale)->richText($html, $options))['word/document.xml'];
    }

    /** @return string[] the paragraphs' XML */
    private static function paragraphs(string $xml): array
    {
        preg_match_all('/<w:p>.*?<\/w:p>/s', $xml, $matches);

        return $matches[0];
    }

    /** The text of a piece of XML, runs joined. */
    private static function text(string $xml): string
    {
        preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $xml, $matches);

        return html_entity_decode(implode('', $matches[1]), ENT_QUOTES | ENT_XML1);
    }

    public function test_alignment_keeps_its_side_in_a_right_to_left_document(): void
    {
        $paragraphs = self::paragraphs($this->body('<p style="text-align: right">يمين</p><p style="text-align: left">يسار</p><p>بدون</p><p style="text-align: center">وسط</p>'));

        $this->assertMatchesRegularExpression('/<w:jc w:val="start"\/>.*<w:bidi\/>|<w:bidi\/>.*<w:jc w:val="start"\/>/s', $paragraphs[0]);
        $this->assertStringContainsString('<w:jc w:val="end"/>', $paragraphs[1]);
        $this->assertStringContainsString('<w:bidi/>', $paragraphs[1]);
        $this->assertStringContainsString('<w:bidi/>', $paragraphs[2]);
        $this->assertStringContainsString('<w:jc w:val="center"/>', $paragraphs[3]);
        $this->assertStringContainsString('<w:rtl/>', $paragraphs[0]);
    }

    public function test_left_to_right_documents_keep_their_direction(): void
    {
        $paragraphs = self::paragraphs($this->body('<p style="text-align: right">Right</p><p>Plain</p>', 'en'));

        // "end" is the right side in a left-to-right paragraph.
        $this->assertStringContainsString('<w:jc w:val="end"/>', $paragraphs[0]);
        $this->assertStringNotContainsString('<w:bidi/>', $paragraphs[1]);
    }

    public function test_arabic_runs_are_right_to_left_with_the_bidi_language(): void
    {
        $xml = $this->body('<p>عربي <b>bold</b></p>', 'ar_EG');

        $this->assertMatchesRegularExpression('/<w:lang w:bidi="ar-EG"\/>.*?<w:rtl\/><\/w:rPr><w:t xml:space="preserve">عربي /s', $xml);
    }

    public function test_spaces_between_runs_and_escaped_text_are_kept(): void
    {
        $xml = $this->body('<p><b>a</b> <i>b</i> &#60;script&#62;alert(1)&#60;/script&#62; &amp; "q"</p>', 'en');

        $this->assertSame('a b <script>alert(1)</script> & "q"', self::text($xml));
        $this->assertStringNotContainsString('<script>', $xml);
    }

    public function test_underline_strike_highlight_and_colour(): void
    {
        $xml = $this->body('<p><u>u</u> <s>s</s> <span style="text-decoration: underline line-through">both <b>bold</b></span> <mark>m</mark> <span style="color: #DC2626">c</span></p>', 'en');

        $this->assertMatchesRegularExpression('/<w:u w:val="single"\/>.*?<w:t xml:space="preserve">u</s', $xml);
        $this->assertMatchesRegularExpression('/<w:strike w:val="1"\/>.*?<w:t xml:space="preserve">s</s', $xml);
        // Both on every piece of text, also inside the bold.
        $this->assertMatchesRegularExpression('/<w:rPr>(?=(?:(?!<\/w:rPr>).)*<w:u w:val="single"\/>)(?=(?:(?!<\/w:rPr>).)*<w:strike w:val="1"\/>)(?:(?!<\/w:rPr>).)*<\/w:rPr><w:t xml:space="preserve">both </s', $xml);
        $this->assertMatchesRegularExpression('/<w:rPr>(?=(?:(?!<\/w:rPr>).)*<w:b w:val="1"\/>)(?=(?:(?!<\/w:rPr>).)*<w:strike w:val="1"\/>)(?:(?!<\/w:rPr>).)*<\/w:rPr><w:t xml:space="preserve">bold</s', $xml);
        $this->assertMatchesRegularExpression('/<w:shd w:val="clear" w:fill="FFFF00"\/><\/w:rPr><w:t xml:space="preserve">m</s', $xml);
        $this->assertMatchesRegularExpression('/<w:color w:val="DC2626"\/>.*?<w:t xml:space="preserve">c</s', $xml);
    }

    public function test_headings_use_registered_styles(): void
    {
        $parts = self::parts($this->docs()->make()->locale('ar')->richText('<h1>أ</h1><h2>ب</h2><h4>د</h4>'));

        $this->assertStringContainsString('<w:pStyle w:val="Heading1"/>', $parts['word/document.xml']);
        $this->assertStringContainsString('<w:pStyle w:val="Heading4"/>', $parts['word/document.xml']);
        $this->assertMatchesRegularExpression('/<w:style w:type="paragraph" w:styleId="Heading2">(?:(?!<\/w:style>).)*<w:keepNext w:val="1"\/>(?:(?!<\/w:style>).)*<w:sz w:val="28"\/>(?:(?!<\/w:style>).)*<w:b w:val="1"\/>/s', $parts['word/styles.xml']);
        $this->assertMatchesRegularExpression('/w:styleId="Heading1">(?:(?!<\/w:style>).)*<w:color w:val="0F766E"\/>/s', $parts['word/styles.xml']);
    }

    public function test_tiptap_nested_lists_keep_their_kinds_and_direction(): void
    {
        $parts = self::parts($this->docs()->make()->locale('ar')->richText(
            '<ol><li><p>أول</p></li><li><p>ثانٍ</p><ul><li><p>فرعي</p></li></ul></li></ol>'
        ));
        $paragraphs = self::paragraphs($parts['word/document.xml']);

        $this->assertCount(3, $paragraphs);
        $this->assertStringContainsString('<w:ilvl w:val="0"/>', $paragraphs[0]);
        $this->assertStringContainsString('<w:ilvl w:val="1"/>', $paragraphs[2]);
        $this->assertSame('فرعي', self::text($paragraphs[2]));

        foreach ($paragraphs as $paragraph) {
            $this->assertStringContainsString('<w:bidi/>', $paragraph);
        }

        // The numbered list's second level is bulleted, as the nested list is.
        $this->assertMatchesRegularExpression('/<w:lvl w:ilvl="0">.*?<w:numFmt w:val="decimal"\/>.*?<w:lvl w:ilvl="1">.*?<w:numFmt w:val="bullet"\/>/s', $parts['word/numbering.xml']);
    }

    public function test_a_table_inside_a_list_item_follows_the_list(): void
    {
        $xml = $this->body('<ul><li>بند<table><tr><td>خلية</td></tr></table></li></ul>');

        $this->assertMatchesRegularExpression('/<w:numPr>.*?بند.*?<w:tbl>.*?خلية/s', $xml);
    }

    public function test_free_tables_with_colspan_rowspan_and_the_grid_look(): void
    {
        $xml = $this->body('<table><tr><th>أ</th><th>ب</th><th>ج</th></tr><tr><td rowspan="2">حفر</td><td>نقل</td><td>5</td></tr><tr><td>ردم</td><td>3</td></tr><tr><td colspan="3">عام</td></tr></table>');

        preg_match_all('/<w:tr>.*?<\/w:tr>/s', $xml, $rows);
        $this->assertCount(4, $rows[0]);
        $this->assertStringContainsString('<w:bidiVisual w:val="1"/>', $xml);
        $this->assertStringContainsString('<w:vMerge w:val="restart"/>', $rows[0][1]);
        $this->assertStringContainsString('<w:vMerge w:val="continue"/>', $rows[0][2]);
        $this->assertSame(3, substr_count($rows[0][2], '<w:tc>'));
        $this->assertSame('ردم3', self::text($rows[0][2]));
        $this->assertStringContainsString('<w:gridSpan w:val="3"/>', $rows[0][3]);
        // th cells are the header: coloured and bold.
        $this->assertStringContainsString('w:fill="0F766E"', $rows[0][0]);
        $this->assertStringContainsString('<w:color w:val="FFFFFF"/>', $rows[0][0]);
        $this->assertStringContainsString('<w:top w:val="single" w:sz="', $rows[0][1]);
    }

    public function test_a_rowspan_in_the_last_column_gets_its_continuation_at_the_end(): void
    {
        $xml = $this->body('<table><tr><td>أ</td><td rowspan="2">ب</td></tr><tr><td>ج</td></tr></table>');

        preg_match_all('/<w:tr>.*?<\/w:tr>/s', $xml, $rows);
        $this->assertSame(2, substr_count($rows[0][1], '<w:tc>'));
        $this->assertMatchesRegularExpression('/ج.*<w:vMerge w:val="continue"\/>/s', $rows[0][1]);
    }

    public function test_quotes_are_indented(): void
    {
        $paragraphs = self::paragraphs($this->body('<p>قبل</p><blockquote><p>اقتباس</p><blockquote><p>داخل</p></blockquote></blockquote>'));

        $this->assertStringNotContainsString('<w:ind', $paragraphs[0]);
        $this->assertStringContainsString('<w:ind w:left="567"', $paragraphs[1]);
        $this->assertSame('اقتباس', self::text($paragraphs[1]));
        $this->assertStringContainsString('<w:ind w:left="1134"', $paragraphs[2]);
    }

    public function test_text_beside_blocks_stays_in_one_paragraph(): void
    {
        $paragraphs = self::paragraphs($this->body('نص <b>عريض</b> <a href="https://example.com">رابط</a><p>فقرة</p>'));

        $this->assertCount(2, $paragraphs);
        $this->assertSame('نص عريض رابط', self::text($paragraphs[0]));
        $this->assertStringContainsString('<w:hyperlink', $paragraphs[0]);
    }

    public function test_numerals_and_font_size_apply_to_body_text_only(): void
    {
        $xml = self::parts($this->docs()->make()->locale('ar')->numerals('arabic')->richText('<h2>بند 1</h2><p>المدة 30 يوماً، الخصم -2.5</p>', ['font_size' => 9]))['word/document.xml'];
        $paragraphs = self::paragraphs($xml);

        $this->assertSame('بند ١', self::text($paragraphs[0]));
        $this->assertStringNotContainsString('<w:sz w:val="18"/>', $paragraphs[0]);
        $this->assertSame("المدة ٣٠ يوماً، الخصم \u{202D}-٢.٥\u{202C}", self::text($paragraphs[1]));
        $this->assertStringContainsString('<w:sz w:val="18"/>', $paragraphs[1]);
    }

    public function test_images_go_through_word_image_and_leave_nothing_in_the_temp_folder(): void
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagewebp($image);
        $webp = base64_encode((string) ob_get_clean());
        $svg = base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="4" height="4"><rect width="4" height="4"/></svg>');
        $before = scandir(sys_get_temp_dir());

        $parts = self::parts($this->docs()->make()->locale('ar')->richText(
            '<p>قبل <img src="data:image/webp;base64,'.$webp.'" width="400"> بعد</p><p><img src="data:image/svg+xml;base64,'.$svg.'"></p>'
        ));

        $media = array_values(array_filter(array_keys($parts), fn ($name) => str_starts_with($name, 'word/media/')));
        $this->assertCount(1, $media);
        $this->assertStringEndsWith('.png', $media[0]);
        $this->assertSame(IMAGETYPE_PNG, getimagesizefromstring($parts[$media[0]])[2]);

        // The image sits between the two texts, 400 px wide (300 pt).
        $paragraphs = self::paragraphs($parts['word/document.xml']);
        $this->assertMatchesRegularExpression('/قبل .*?<v:shape [^>]*style="width:300pt;.*? بعد/s', $paragraphs[0]);

        $this->assertSame([], array_values(array_diff(scandir(sys_get_temp_dir()), $before)));
    }

    public function test_images_from_the_server_are_refused(): void
    {
        $xml = $this->body('<p>نص<img src="'.__DIR__.'/../fixtures/plain-templates/note/template.php"></p>');

        $this->assertStringNotContainsString('<v:shape', $xml);
        $this->assertStringContainsString('rich text: an image was dropped', implode("\n", $this->warnings));
    }

    public function test_a_parser_failure_writes_the_text_instead(): void
    {
        $listIndex = new ReflectionProperty(Html::class, 'listIndex');
        $saved = $listIndex->getValue();
        // The parser cannot count lists from here, so the list throws.
        $listIndex->setValue(null, []);

        try {
            $paragraphs = self::paragraphs($this->body('<p>قبل</p><ul><li>بند</li></ul><p>بعد</p>'));
        } finally {
            $listIndex->setValue(null, $saved);
        }

        $this->assertSame(['قبل', 'بند', 'بعد'], array_map(self::text(...), $paragraphs));
        $this->assertCount(1, $this->warnings);
        $this->assertStringStartsWith('easy-pdf-word: rich text could not be written to Word, so its text was written instead:', $this->warnings[0]);
    }
}
