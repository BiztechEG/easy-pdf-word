<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\Document;
use BiztechEG\EasyPdfWord\EasyPdfWord;
use BiztechEG\EasyPdfWord\Support\Stamp;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * A table cell's "overlay": a stamp over the signature, in PDF (a cell
 * background) and Word (a floating image behind the text).
 */
class CellOverlayTest extends TestCase
{
    private static string $dir;

    private static string $stamp;

    /** @var string[] */
    private array $warnings = [];

    public static function setUpBeforeClass(): void
    {
        self::$dir = sys_get_temp_dir().'/easy-pdf-word-tests/overlay';
        @mkdir(self::$dir, 0777, true);
        self::$stamp = self::$dir.'/stamp.png';
        // 300 x 150 pixels: twice as wide as high.
        $image = imagecreatetruecolor(300, 150);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 60, 200));
        imagepng($image, self::$stamp);
    }

    private function make(string $locale = 'ar'): Document
    {
        return EasyPdfWord::create([
            'images' => ['paths' => [self::$dir]],
            'pdf' => ['drivers' => ['mpdf' => ['temp_dir' => sys_get_temp_dir().'/easy-pdf-word-tests/mpdf']]],
        ], ['warn' => function (string $message) {
            $this->warnings[] = $message;
        }])->make()->locale($locale);
    }

    private function signature(string $locale = 'ar', array $overlay = []): Document
    {
        return $this->make($locale)->table([[
            ['lines' => ['العميل', '', '']],
            ['lines' => ['مدير المبيعات', '', 'عمرو محمد'], 'overlay' => $overlay + ['image' => self::$stamp, 'width' => 30, 'offset' => [10, 2]]],
        ]], ['columns' => [50, 50]]);
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

    public function test_measure_keeps_the_proportions_unless_stretched(): void
    {
        $bytes = (string) file_get_contents(self::$stamp);

        $this->assertSame(['width' => 30.0, 'height' => 15.0, 'start' => 0.0, 'down' => 0.0], Stamp::measure([], $bytes));
        $this->assertSame(['width' => 40.0, 'height' => 20.0, 'start' => 5.0, 'down' => -3.0], Stamp::measure(['width' => 40, 'offset' => [5, -3]], $bytes));
        $this->assertSame(['width' => 20.0, 'height' => 10.0, 'start' => 0.0, 'down' => 0.0], Stamp::measure(['height' => 10], $bytes));
        // Both given: it fits inside them.
        $this->assertSame(['width' => 20.0, 'height' => 10.0, 'start' => 0.0, 'down' => 0.0], Stamp::measure(['width' => 35, 'height' => 10], $bytes));
        $this->assertSame(['width' => 35.0, 'height' => 35.0, 'start' => 0.0, 'down' => 0.0], Stamp::measure(['width' => 35, 'height' => 35, 'stretch' => true], $bytes));
        $this->assertSame(300.0, Stamp::measure(['width' => 99999], $bytes)['width']);
        $this->assertNull(Stamp::measure([], '<svg xmlns="http://www.w3.org/2000/svg"/>'));
    }

    public function test_the_pdf_stamp_carries_its_offset_as_transparent_space(): void
    {
        $bytes = (string) file_get_contents(self::$stamp);
        $box = Stamp::measure(['width' => 30, 'offset' => [10, 2]], $bytes);
        $read = function (array $stamp) {
            $png = base64_decode(substr($stamp['uri'], strlen('data:image/png;base64,')));

            return imagecreatefromstring($png);
        };

        $ltr = Stamp::forPdf($bytes, $box, false);
        $rtl = Stamp::forPdf($bytes, $box, true);

        $this->assertEqualsWithDelta(40.0, $ltr['width'], 0.1);
        $this->assertEqualsWithDelta(17.0, $ltr['height'], 0.1);
        $alpha = fn ($image, int $x, int $y) => (imagecolorat($image, $x, $y) >> 24) & 0x7F;
        // LTR: the space is on the left; RTL: on the right. The space above is transparent in both.
        [$l, $r] = [$read($ltr), $read($rtl)];
        $this->assertSame(127, $alpha($l, 5, 100));
        $this->assertSame(0, $alpha($l, imagesx($l) - 5, 100));
        $this->assertSame(0, $alpha($r, 5, 100));
        $this->assertSame(127, $alpha($r, imagesx($r) - 5, 100));
        $this->assertSame(127, $alpha($r, 5, 5));

        // An offset past the cell's edge cuts the stamp.
        $this->assertEqualsWithDelta(25.0, Stamp::forPdf($bytes, ['start' => -5] + $box, true)['width'], 0.1);
        $this->assertNull(Stamp::forPdf($bytes, ['start' => -40] + $box, true));
    }

    public function test_pdf_cell_background_from_the_start_side(): void
    {
        $ar = $this->signature()->toHtml();
        $en = $this->signature('en')->toHtml();

        $this->assertMatchesRegularExpression("#<td style=\"[^\"]*background-image: url\('data:image/png;base64,[A-Za-z0-9+/=]+'\); background-repeat: no-repeat; background-position: 100% 0; background-image-resolution: 300dpi; background-size: 39.96mm 17.02mm;\">مدير المبيعات#", $ar);
        $this->assertStringContainsString('background-position: 0 0;', $en);
        $this->assertSame(1, substr_count($ar, 'background-image:'));
        $this->assertStringStartsWith('%PDF', $this->signature()->pdf()->content());
    }

    public function test_word_floating_image_behind_the_text_in_the_cell(): void
    {
        $ar = self::parts($this->signature());
        $en = self::parts($this->signature('en'));

        preg_match_all('#<w:tc>.*?</w:tc>#s', $ar['word/document.xml'], $cells);
        $this->assertStringNotContainsString('<v:shape', $cells[0][0]);
        // In the cell's first paragraph, with its text.
        $this->assertMatchesRegularExpression('#<w:p>(?:(?!</w:p>).)*مدير المبيعات(?:(?!</w:p>).)*<v:shape [^>]*style="width:85.04pt; height:42.52pt; margin-left:([\d.]+)pt; margin-top:5.67pt; position:relative; mso-position-horizontal:absolute; mso-position-vertical:absolute; mso-position-horizontal-relative:column; mso-position-vertical-relative:text; z-index:-#s', $cells[0][1]);
        $this->assertNotEmpty(preg_grep('#^word/media/.+\.png$#', array_keys($ar)));

        // From the right in Arabic, from the left in English.
        preg_match('/margin-left:([\d.]+)pt/', $ar['word/document.xml'], $right);
        preg_match('/margin-left:([\d.]+)pt/', $en['word/document.xml'], $left);
        $this->assertSame('28.35', $left[1]);
        $this->assertGreaterThan(100, (float) $right[1]);
    }

    public function test_a_server_path_outside_the_allowed_folders_is_left_out(): void
    {
        $html = $this->signature('ar', ['image' => __FILE__])->toHtml();
        $xml = self::parts($this->signature('ar', ['image' => '/etc/passwd']))['word/document.xml'];

        $this->assertStringNotContainsString('background-image', $html);
        $this->assertStringNotContainsString('<v:shape', $xml);
    }

    public function test_an_svg_overlay_is_left_out_and_reported(): void
    {
        $svg = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>');

        $html = $this->signature('ar', ['image' => $svg])->toHtml();

        $this->assertStringNotContainsString('background-image', $html);
        $this->assertSame(['easy-pdf-word: a cell overlay must be a PNG, JPEG, GIF, WebP or BMP image inside its cell; it was left out.'], $this->warnings);
    }
}
