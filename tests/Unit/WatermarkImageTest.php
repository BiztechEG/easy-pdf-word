<?php

namespace BiztechEG\EasyPdfWord\Tests\Unit;

use BiztechEG\EasyPdfWord\Document;
use BiztechEG\EasyPdfWord\EasyPdfWord;
use BiztechEG\EasyPdfWord\Pdf\PdfOptions;
use BiztechEG\EasyPdfWord\Pdf\Watermark;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * watermarkImage(): an image in the middle of every page, in PDF (mPDF and
 * the Chromium HTML) and in Word (a header watermark, faded with GD).
 */
class WatermarkImageTest extends TestCase
{
    private static string $dir;

    private static string $image;

    /** @var string[] */
    private array $warnings = [];

    public static function setUpBeforeClass(): void
    {
        self::$dir = sys_get_temp_dir().'/easy-pdf-word-tests/watermark';
        @mkdir(self::$dir, 0777, true);
        self::$image = self::$dir.'/draft.png';
        // 400 x 100 pixels, opaque red.
        $image = imagecreatetruecolor(400, 100);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 30, 30));
        imagepng($image, self::$image);
    }

    private function make(): Document
    {
        return EasyPdfWord::create([
            'images' => ['paths' => [self::$dir]],
            'pdf' => ['drivers' => ['mpdf' => ['temp_dir' => sys_get_temp_dir().'/easy-pdf-word-tests/mpdf']]],
        ], ['warn' => function (string $message) {
            $this->warnings[] = $message;
        }])->make()->locale('ar')->paragraph('نص العرض');
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

    /** @return array<string, string> the headers that carry a watermark shape */
    private static function marked(array $parts): array
    {
        return array_filter($parts, fn (string $xml, string $name) => str_starts_with($name, 'word/header')
            && str_contains($xml, 'mso-position-horizontal:center') && str_contains($xml, '<v:imagedata'), ARRAY_FILTER_USE_BOTH);
    }

    public function test_options_carry_the_image_as_a_data_uri(): void
    {
        $options = $this->make()->watermarkImage(self::$image, ['opacity' => 0.3, 'width' => 120])->options();

        $this->assertStringStartsWith('data:image/png;base64,', $options->watermarkImage['src']);
        $this->assertSame(0.3, $options->watermarkImage['opacity']);
        $this->assertSame(120.0, $options->watermarkImage['width']);

        $defaults = $this->make()->watermarkImage(self::$image)->options()->watermarkImage;
        $this->assertSame([0.15, 140.0], [$defaults['opacity'], $defaults['width']]);
        $clamped = $this->make()->watermarkImage(self::$image, ['opacity' => 5, 'width' => 1])->options()->watermarkImage;
        $this->assertSame([1.0, 10.0], [$clamped['opacity'], $clamped['width']]);
    }

    public function test_an_image_outside_the_allowed_folders_is_left_out(): void
    {
        $this->assertNull($this->make()->watermarkImage(__FILE__)->options()->watermarkImage);
        $this->assertNull($this->make()->options()->watermarkImage);
    }

    public function test_an_empty_source_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->make()->watermarkImage(' ');
    }

    public function test_the_height_follows_the_proportions(): void
    {
        $png = 'data:image/png;base64,'.base64_encode((string) file_get_contents(self::$image));
        $svg = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 150"/>');

        $this->assertSame([140.0, 35.0], Watermark::imageSize(['src' => $png, 'width' => 140]));
        $this->assertSame([100.0, 50.0], Watermark::imageSize(['src' => $svg, 'width' => 100]));
    }

    public function test_chromium_gets_a_fixed_image_in_the_middle(): void
    {
        $options = $this->make()->watermarkImage(self::$image, ['opacity' => 0.3, 'width' => 120])->options();
        $html = Watermark::inject('<html><body><p>x</p></body></html>', $options);

        $this->assertMatchesRegularExpression('#<p>x</p><div aria-hidden="true" style="position: fixed;[^"]*justify-content: center;[^"]*"><img src="data:image/png;base64,[^"]+" alt="" style="width: 120mm; height: 30mm; opacity: 0.3;"></div></body>#', $html);
        $this->assertSame('<p>x</p>', Watermark::inject('<p>x</p>', new PdfOptions));
    }

    public function test_mpdf_draws_the_image_at_its_opacity(): void
    {
        $pdf = $this->make()->watermarkImage(self::$image, ['opacity' => 0.3])->pdf()->content();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertMatchesRegularExpression('#/ca 0\.3#', $pdf);
        $this->assertMatchesRegularExpression('#/Subtype /Image#', $pdf);
    }

    public function test_word_puts_a_faded_image_in_a_new_header(): void
    {
        $parts = self::parts($this->make()->watermarkImage(self::$image, ['opacity' => 0.3, 'width' => 120]));

        $marked = self::marked($parts);
        $this->assertCount(1, $marked);
        $this->assertStringContainsString('width:340.15pt; height:85.04pt;', reset($marked));
        $this->assertStringContainsString('mso-position-horizontal-relative:page', reset($marked));
        $this->assertStringContainsString('mso-position-vertical:center', reset($marked));

        $media = array_values(array_filter($parts, fn ($name) => str_starts_with($name, 'word/media/'), ARRAY_FILTER_USE_KEY));
        $this->assertCount(1, $media);
        $image = imagecreatefromstring($media[0]);
        // 120 mm at 150 dpi, at most the source's 400 pixels; an opaque pixel keeps 30% of its alpha.
        $this->assertSame(400, imagesx($image));
        $this->assertSame(127 - (int) round(127 * 0.3), (imagecolorat($image, 10, 10) >> 24) & 0x7F);
    }

    public function test_word_marks_the_first_page_header_too(): void
    {
        $parts = self::parts($this->make()
            ->header(fn ($h) => $h->paragraph('ترويسة'))
            ->firstPageHeader(fn ($h) => $h->paragraph('ترويسة الصفحة الأولى'))
            ->watermarkImage(self::$image));

        $marked = self::marked($parts);
        $this->assertCount(2, $marked);
        $this->assertCount(1, array_filter($marked, fn ($xml) => str_contains($xml, 'ترويسة الصفحة الأولى')));
    }

    public function test_an_svg_watermark_is_left_out_of_word_and_reported(): void
    {
        $svg = 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>');

        $parts = self::parts($this->make()->watermarkImage($svg));

        $this->assertSame([], self::marked($parts));
        $this->assertSame(['easy-pdf-word: the watermark image must be a PNG, JPEG, GIF, WebP or BMP image for Word files; the Word file is made without it.'], $this->warnings);
    }
}
