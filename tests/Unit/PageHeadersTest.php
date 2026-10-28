<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\Builder\DocumentBuilder;
use BiztechEG\EasyPdfWord\Document;
use BiztechEG\EasyPdfWord\DocumentFactory;
use BiztechEG\EasyPdfWord\EasyPdfWord;
use BiztechEG\EasyPdfWord\Pdf\Drivers\GotenbergDriver;
use BiztechEG\EasyPdfWord\Pdf\PdfManager;
use BiztechEG\EasyPdfWord\Pdf\PdfOptions;
use BiztechEG\EasyPdfWord\Support\CurlHttpClient;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Page headers and footers built from blocks, and a first page with its
 * own, in PDF and Word.
 */
class PageHeadersTest extends TestCase
{
    /** @var string[] */
    private array $warnings = [];

    private static string $images;

    public static function setUpBeforeClass(): void
    {
        self::$images = sys_get_temp_dir().'/easy-pdf-word-tests/header-images';
        @mkdir(self::$images, 0777, true);
        $image = imagecreatetruecolor(20, 8);
        imagepng($image, self::$images.'/logo.png');
    }

    private function docs(array $config = []): DocumentFactory
    {
        return EasyPdfWord::create(array_replace_recursive([
            'pdf' => ['drivers' => ['mpdf' => ['temp_dir' => sys_get_temp_dir().'/easy-pdf-word-tests/mpdf']]],
            'templates' => ['paths' => [__DIR__.'/../fixtures/plain-templates']],
            'images' => ['paths' => [self::$images]],
        ], $config), ['warn' => function (string $message) {
            $this->warnings[] = $message;
        }]);
    }

    private static function letterhead(DocumentBuilder $h): void
    {
        $h->table([[['image' => self::$images.'/logo.png', 'width' => 30], ['lines' => [['text' => 'شركة بيزتك', 'bold' => true], 'الدقي، الجيزة']]]], ['borders' => false, 'columns' => [30, 70]]);
    }

    /** @return array<string, string> the Word file's parts by name */
    private static function wordParts(Document $document): array
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

    /** The part a reference of this type in document.xml points at: "default" or "first". */
    private static function wordPart(array $parts, string $kind, string $type): ?string
    {
        if (! preg_match('/<w:'.$kind.'Reference w:type="'.$type.'" r:id="(rId\d+)"\/>/', $parts['word/document.xml'], $reference)) {
            return null;
        }

        preg_match('/Id="'.$reference[1].'" Type="[^"]+" Target="([^"]+)"/', $parts['word/_rels/document.xml.rels'], $target);

        return $parts['word/'.$target[1]];
    }

    public function test_string_headers_work_as_before(): void
    {
        $options = $this->docs()->make()->header('<b>Header</b>')->footer('{page} / {pages}')->options();

        $this->assertSame('<b>Header</b>', $options->header);
        $this->assertSame('{page} / {pages}', $options->footer);
        $this->assertNull($options->firstHeader);
        $this->assertFalse($options->hasFirstPage());
        $this->assertFalse($options->fitHeaders);
    }

    public function test_block_headers_become_html_for_every_engine(): void
    {
        $options = $this->docs()->make()->locale('ar')
            ->header(self::letterhead(...))
            ->footer(fn (DocumentBuilder $f) => $f->paragraph('صفحة {page} من {pages}', ['align' => 'center', 'size' => 8]))
            ->options();

        $this->assertStringContainsString('<table', $options->header);
        $this->assertStringContainsString('<img src="data:image/png;base64,', $options->header);
        $this->assertStringContainsString('شركة بيزتك', $options->header);
        $this->assertStringContainsString('صفحة {page} من {pages}</p>', $options->footer);
        $this->assertTrue($options->fitHeaders);
    }

    public function test_a_string_and_blocks_replace_each_other(): void
    {
        $document = $this->docs()->make()->header('<b>HTML</b>')->header(fn (DocumentBuilder $h) => $h->paragraph('Blocks'));
        $this->assertStringContainsString('Blocks', $document->options()->header);

        $document->header('<b>HTML</b>');
        $this->assertSame('<b>HTML</b>', $document->options()->header);
        $this->assertFalse($document->options()->fitHeaders);
    }

    public function test_headers_take_only_page_blocks(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A page header or footer takes paragraph, table, image, line and spacer blocks, not [heading].');

        $this->docs()->make()->header(fn (DocumentBuilder $h) => $h->paragraph('a')->heading('b'));
    }

    public function test_the_first_page_and_distances_reach_the_options(): void
    {
        $options = $this->docs()->make()
            ->header('<b>Every page</b>')
            ->firstPageHeader(fn (DocumentBuilder $h) => $h->paragraph('First page'))
            ->firstPageFooter(null)
            ->headerDistance(6)
            ->footerDistance(4.5)
            ->options();

        $this->assertStringContainsString('First page', $options->firstHeader);
        $this->assertFalse($options->firstFooter);
        $this->assertTrue($options->hasFirstPage());
        $this->assertSame(6.0, $options->headerDistance);
        $this->assertSame(4.5, $options->footerDistance);
    }

    public function test_header_text_follows_the_numerals(): void
    {
        $options = $this->docs()->make()->locale('ar')->numerals('arabic')
            ->header(fn (DocumentBuilder $h) => $h->paragraph('الرقم الضريبي 123'))
            ->options();

        $this->assertStringContainsString('الرقم الضريبي ١٢٣', $options->header);
    }

    public function test_header_images_follow_the_image_rules(): void
    {
        $options = $this->docs()->make()->header(fn (DocumentBuilder $h) => $h->image(__FILE__)->paragraph('Text'))->options();

        $this->assertStringNotContainsString('<img', $options->header);
        $this->assertStringContainsString('Text', $options->header);
        $this->assertCount(1, $this->warnings);
        $this->assertStringContainsString('images.paths', $this->warnings[0]);
    }

    public function test_mpdf_starts_the_body_below_a_tall_block_header(): void
    {
        $pdf = $this->docs()->make()->locale('en')->margins(15)
            ->header(fn (DocumentBuilder $h) => $h->table([[['lines' => array_fill(0, 7, 'Letterhead'), 'background' => '#AA0000']]], ['borders' => false]))
            ->table([[['text' => 'Body', 'background' => '#00AA00']]], ['borders' => false])
            ->pdf()->content();

        $boxes = self::filledBoxes($pdf);
        $header = $boxes['0.667 0.000 0.000'];
        $body = $boxes['0.000 0.667 0.000'];

        // PDF y grows upward: the header's box is taller than the 15 mm margin, and ends above the body's top.
        $this->assertGreaterThan(15 * 72 / 25.4, -$header[3]);
        $this->assertGreaterThan($body[1], $header[1] + $header[3]);
    }

    public function test_mpdf_gives_the_first_page_its_own_header_and_footer(): void
    {
        $document = $this->docs()->make()->locale('en')
            ->header(fn (DocumentBuilder $h) => $h->table([[['text' => 'Every page', 'background' => '#AA0000']]], ['borders' => false]))
            ->footer(fn (DocumentBuilder $f) => $f->table([[['text' => 'Footer', 'background' => '#0000AA']]], ['borders' => false]))
            ->firstPageHeader(fn (DocumentBuilder $h) => $h->table([[['text' => 'First page', 'background' => '#00AA00']]], ['borders' => false]))
            ->firstPageFooter(null)
            ->paragraph('One')->pageBreak()->paragraph('Two');

        $pages = self::pages($document->pdf()->content());

        $this->assertCount(2, $pages);
        $this->assertSame(['0.000 0.667 0.000'], array_keys(self::filledBoxes($pages[0])));
        $this->assertEqualsCanonicalizing(['0.667 0.000 0.000', '0.000 0.000 0.667'], array_keys(self::filledBoxes($pages[1])));
    }

    public function test_mpdf_keeps_the_normal_footer_on_a_first_page_with_its_own_header(): void
    {
        $document = $this->docs()->make()->locale('en')
            ->footer(fn (DocumentBuilder $f) => $f->table([[['text' => 'Footer', 'background' => '#0000AA']]], ['borders' => false]))
            ->firstPageHeader(fn (DocumentBuilder $h) => $h->table([[['text' => 'First page', 'background' => '#00AA00']]], ['borders' => false]))
            ->paragraph('One')->pageBreak()->paragraph('Two');

        $pages = self::pages($document->pdf()->content());

        $this->assertEqualsCanonicalizing(['0.000 0.667 0.000', '0.000 0.000 0.667'], array_keys(self::filledBoxes($pages[0])));
        $this->assertSame(['0.000 0.000 0.667'], array_keys(self::filledBoxes($pages[1])));
    }

    public function test_chromium_engines_report_that_they_have_no_first_page(): void
    {
        $warnings = [];
        $manager = new PdfManager(['pdf' => ['driver' => 'gotenberg', 'drivers' => ['gotenberg' => ['url' => 'http://gotenberg.test']]]], warn: function (string $message) use (&$warnings) {
            $warnings[] = $message;
        });
        $manager->extend('gotenberg', fn () => new class(new CurlHttpClient, ['url' => 'http://gotenberg.test']) extends GotenbergDriver
        {
            public function render(string $html, PdfOptions $options): string
            {
                return '%PDF-fake';
            }
        });

        $manager->render('<p>x</p>', new PdfOptions(firstHeader: false));
        $manager->render('<p>x</p>', new PdfOptions(header: 'x'));

        $this->assertSame(['easy-pdf-word: [gotenberg] cannot give the first page its own header or footer; it gets the normal ones.'], $warnings);
    }

    public function test_word_writes_block_headers_with_their_images_and_page_numbers(): void
    {
        $parts = self::wordParts($this->docs()->make()->locale('ar')
            ->header(self::letterhead(...))
            ->footer(fn (DocumentBuilder $f) => $f->paragraph('صفحة {page} من {pages}'))
            ->paragraph('المتن'));

        $header = self::wordPart($parts, 'header', 'default');
        $this->assertStringContainsString('<w:tbl>', $header);
        $this->assertStringContainsString('<w:bidiVisual w:val="1"/>', $header);
        $this->assertStringContainsString('<v:imagedata r:id="rId1"', $header);
        $this->assertStringContainsString('شركة بيزتك', $header);
        $this->assertStringContainsString('relationships/image', $parts['word/_rels/header1.xml.rels']);

        $footer = self::wordPart($parts, 'footer', 'default');
        $this->assertStringContainsString(' PAGE ', $footer);
        $this->assertStringContainsString(' NUMPAGES ', $footer);
        $this->assertStringNotContainsString('{page}', $footer);

        $this->assertNull(self::wordPart($parts, 'header', 'first'));
        $this->assertStringNotContainsString('<w:titlePg', $parts['word/document.xml']);
    }

    public function test_word_writes_the_first_page_and_repeats_the_part_it_does_not_replace(): void
    {
        $parts = self::wordParts($this->docs()->make()
            ->header(fn (DocumentBuilder $h) => $h->paragraph('Every page'))
            ->footer(fn (DocumentBuilder $f) => $f->paragraph('Footer {page}'))
            ->firstPageHeader(fn (DocumentBuilder $h) => $h->paragraph('First page'))
            ->paragraph('Body'));

        $this->assertStringContainsString('<w:titlePg', $parts['word/document.xml']);
        $this->assertStringContainsString('First page', self::wordPart($parts, 'header', 'first'));
        $this->assertStringContainsString('Every page', self::wordPart($parts, 'header', 'default'));
        // Word takes page 1's footer from the FIRST variant too.
        $this->assertStringContainsString('Footer', self::wordPart($parts, 'footer', 'first'));
    }

    public function test_word_leaves_out_the_first_page_footer_when_asked(): void
    {
        $parts = self::wordParts($this->docs()->make()
            ->footer('<p>Footer</p>')
            ->firstPageFooter(null)
            ->paragraph('Body'));

        $this->assertStringContainsString('Footer', self::wordPart($parts, 'footer', 'default'));
        $this->assertStringNotContainsString('<w:t', self::wordPart($parts, 'footer', 'first'));
        $this->assertStringNotContainsString('<w:t', (string) self::wordPart($parts, 'header', 'first'));
    }

    public function test_word_takes_the_header_and_footer_distances(): void
    {
        $parts = self::wordParts($this->docs()->make()->headerDistance(6)->footerDistance(4)->paragraph('Body'));

        $this->assertStringContainsString('w:header="340"', $parts['word/document.xml']);
        $this->assertStringContainsString('w:footer="227"', $parts['word/document.xml']);
    }

    public function test_word_without_headers_has_no_header_parts(): void
    {
        $parts = self::wordParts($this->docs()->make()->paragraph('Body'));

        $this->assertArrayNotHasKey('word/header1.xml', $parts);
        $this->assertArrayNotHasKey('word/footer1.xml', $parts);
    }

    public function test_templates_take_header_layouts_and_their_html_header_wins_in_pdf(): void
    {
        $document = $this->docs()->template('letterhead', ['company' => 'BizTech'])->locale('en');
        $options = $document->options();

        $this->assertSame('<div>HTML header: BizTech</div>', trim($options->header));
        $this->assertStringContainsString('First page: BizTech', $options->firstHeader);
        $this->assertStringContainsString('Page {page} of {pages}', $options->footer);

        $parts = self::wordParts($document);
        $this->assertStringContainsString('Layout header: BizTech en', self::wordPart($parts, 'header', 'default'));
        $this->assertStringContainsString('First page: BizTech', self::wordPart($parts, 'header', 'first'));
        $this->assertStringContainsString(' PAGE ', self::wordPart($parts, 'footer', 'default'));
    }

    public function test_the_document_header_wins_over_the_template(): void
    {
        $document = $this->docs()->template('letterhead', ['company' => 'BizTech'])->header(fn (DocumentBuilder $h) => $h->paragraph('Own header'));

        $this->assertStringContainsString('Own header', $document->options()->header);
        $this->assertStringContainsString('Own header', self::wordPart(self::wordParts($document), 'header', 'default'));
    }

    public function test_a_copy_does_not_share_the_header_blocks(): void
    {
        $document = $this->docs()->make()->header(fn (DocumentBuilder $h) => $h->paragraph('Header'));
        $copy = clone $document;
        $copy->header(fn (DocumentBuilder $h) => $h->paragraph('Changed'));

        $this->assertStringContainsString('>Header<', $document->options()->header);

        // Plain data, so a queued document carries it as it is.
        $blocks = (new \ReflectionProperty(Document::class, 'headerBlocks'))->getValue($document);
        $this->assertEquals($blocks, unserialize(serialize($blocks)));
    }

    /**
     * Filled rectangles in a PDF's content streams, by fill colour:
     * [x, y, width, height] in points.
     *
     * @return array<string, float[]>
     */
    private static function filledBoxes(string $pdf): array
    {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);
        $boxes = [];

        foreach ($streams[1] as $stream) {
            $content = @gzuncompress($stream) ?: $stream;

            if (preg_match_all('/([\d.]+ [\d.]+ [\d.]+) rg\s+([\d.-]+) ([\d.-]+) ([\d.-]+) ([\d.-]+) re/', $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    if ($match[1] !== '1.000 1.000 1.000') {
                        $boxes[$match[1]] = array_map('floatval', array_slice($match, 2));
                    }
                }
            }
        }

        return $boxes;
    }

    /** @return string[] each page's content streams, as one string per page, in order */
    private static function pages(string $pdf): array
    {
        preg_match_all('/\d+ 0 obj\s*<<\/Type \/Page\b.*?\/Contents (\d+) 0 R/s', $pdf, $pages, PREG_SET_ORDER);
        $contents = [];

        foreach ($pages as $page) {
            preg_match('/(?<!\d)'.$page[1].' 0 obj.*?stream\r?\n.*?\r?\nendstream/s', $pdf, $content);
            $contents[] = $content[0];
        }

        return $contents;
    }
}
