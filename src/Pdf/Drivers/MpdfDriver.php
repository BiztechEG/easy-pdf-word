<?php

namespace BiztechEG\EasyPdfWord\Pdf\Drivers;

use BiztechEG\EasyPdfWord\Arabic\Numerals;
use BiztechEG\EasyPdfWord\Contracts\PdfDriver;
use BiztechEG\EasyPdfWord\Exceptions\DriverNotAvailable;
use BiztechEG\EasyPdfWord\Fonts\FontRegistry;
use BiztechEG\EasyPdfWord\Pdf\PdfOptions;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Mpdf\WatermarkText;

/**
 * Pure PHP engine. Shapes Arabic and applies bidi itself, so it needs no
 * browser and works on shared hosting. CSS support is roughly CSS 2.1:
 * tables and floats, no flexbox or grid.
 */
class MpdfDriver implements PdfDriver
{
    public function __construct(
        private FontRegistry $fonts,
        private array $config = [],
    ) {}

    public function isAvailable(): bool
    {
        return class_exists(Mpdf::class);
    }

    public function usesCssFonts(): bool
    {
        return false;
    }

    public function render(string $html, PdfOptions $options): string
    {
        if (! $this->isAvailable()) {
            throw DriverNotAvailable::missingPackage('mpdf', 'mpdf/mpdf');
        }

        $mpdf = new Mpdf($this->mpdfConfig($options));

        $mpdf->SetDirectionality($options->direction);

        if ($options->title) {
            $mpdf->SetTitle($options->title);
        }

        if ($options->author) {
            $mpdf->SetAuthor($options->author);
        }

        if ($options->header) {
            $mpdf->SetHTMLHeader($this->pageNumbers($options->header));
        }

        if ($options->footer) {
            $mpdf->SetHTMLFooter($this->pageNumbers($options->footer));
        }

        if ($options->watermark) {
            // mPDF shapes the text and fits it to the page diagonal.
            $mpdf->SetWatermarkText(new WatermarkText(
                $options->watermark['text'],
                color: $options->watermark['color'],
                alpha: $options->watermark['opacity'],
                font: strtolower($options->font),
            ));
            $mpdf->showWatermarkText = true;
        }

        if ($options->protection) {
            self::protect($mpdf, $options->protection);
        }

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /** @param  array{user: string, owner: ?string, allow: list<string>}  $protection */
    public static function protect(Mpdf $mpdf, array $protection): void
    {
        // 128-bit RC4, the strongest mPDF offers; a null owner password becomes a random one.
        $mpdf->SetProtection($protection['allow'], $protection['user'], $protection['owner'], 128);
    }

    private function mpdfConfig(PdfOptions $options): array
    {
        [$fontDirs, $fontdata] = $this->fonts->forMpdf((int) ($this->config['use_kashida'] ?? 75));
        [$top, $right, $bottom, $left] = $options->margins;
        $arabicFont = $this->fonts->supportsArabic($options->font) ? strtolower($options->font) : 'cairo';

        $tempDir = $this->config['temp_dir'] ?? null ?: sys_get_temp_dir().'/easy-pdf-word';

        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        return [
            'mode' => 'utf-8',
            // mPDF turns the page itself for "L", so it gets the upright size.
            'format' => [min($options->paperSize()), max($options->paperSize())],
            'orientation' => $options->isLandscape() ? 'L' : 'P',
            'margin_top' => $top,
            'margin_right' => $right,
            'margin_bottom' => $bottom,
            'margin_left' => $left,
            'margin_header' => min(8, $top / 2),
            'margin_footer' => min(8, $bottom / 2),
            'tempDir' => $tempDir,
            'fontDir' => array_merge((new ConfigVariables)->getDefaults()['fontDir'], $fontDirs),
            'fontdata' => (new FontVariables)->getDefaults()['fontdata'] + $fontdata,
            'default_font' => strtolower($options->font),
            'directionality' => $options->direction,
            // {page} and {pages} in the document's digits: ١، ٢، ٣ ...
            'defaultPageNumStyle' => $options->numerals === Numerals::ARABIC ? 'arabic-indic' : '1',
            'autoScriptToLang' => true,
            // Off by default so font-family in the HTML is respected. Turn on
            // for documents mixing Arabic with scripts the main font lacks
            // (Chinese, Hindi, ...); Arabic text then keeps the document font.
            'autoLangToFont' => (bool) ($this->config['auto_lang_to_font'] ?? false),
            'languageToFont' => new ArabicLanguageToFont($arabicFont),
        ];
    }

    private function pageNumbers(string $html): string
    {
        return str_replace(['{page}', '{pages}'], ['{PAGENO}', '{nbpg}'], $html);
    }
}
