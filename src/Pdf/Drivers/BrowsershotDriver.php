<?php

namespace BiztechEG\EasyPdfWord\Pdf\Drivers;

use BiztechEG\EasyPdfWord\Contracts\PdfDriver;
use BiztechEG\EasyPdfWord\Exceptions\DriverNotAvailable;
use BiztechEG\EasyPdfWord\Fonts\FontRegistry;
use BiztechEG\EasyPdfWord\Pdf\PdfOptions;
use BiztechEG\EasyPdfWord\Pdf\Watermark;
use Illuminate\Filesystem\Filesystem;
use Spatie\Browsershot\Browsershot;

/**
 * Chromium through spatie/browsershot (Node + Puppeteer). Best Arabic
 * rendering (HarfBuzz) and full modern CSS.
 */
class BrowsershotDriver implements PdfDriver
{
    public function __construct(
        private array $config = [],
        private ?FontRegistry $fonts = null,
    ) {}

    public function isAvailable(): bool
    {
        return class_exists(Browsershot::class);
    }

    public function usesCssFonts(): bool
    {
        return true;
    }

    public function render(string $html, PdfOptions $options): string
    {
        if (! $this->isAvailable()) {
            throw DriverNotAvailable::missingPackage('browsershot', 'spatie/browsershot');
        }

        [$top, $right, $bottom, $left] = $options->margins;
        [$width, $height] = $options->paperSize();

        $browsershot = Browsershot::html(Watermark::inject($html, $options))
            ->paperSize($width, $height, 'mm')
            ->margins($top, $right, $bottom, $left, 'mm')
            ->showBackground()
            ->timeout((int) ($this->config['timeout'] ?? 60));

        if ($options->header || $options->footer) {
            // The templates carry the embedded font, too big for a command
            // line argument, so the options go to a file.
            $browsershot->showBrowserHeaderAndFooter()
                ->headerHtml($this->chromeTemplate($options->header ?? '', $options))
                ->footerHtml($this->chromeTemplate($options->footer ?? '', $options))
                ->writeOptionsToFile();
        }

        if (! empty($this->config['node_binary'])) {
            $browsershot->setNodeBinary($this->config['node_binary']);
        }

        if (! empty($this->config['npm_binary'])) {
            $browsershot->setNpmBinary($this->config['npm_binary']);
        }

        // Saves running "npm root -g" for every document.
        if (! empty($this->config['node_modules_path'])) {
            $browsershot->setNodeModulePath($this->config['node_modules_path']);
        }

        if (! empty($this->config['chrome_path'])) {
            $browsershot->setChromePath($this->config['chrome_path']);
        }

        if (! empty($this->config['no_sandbox'])) {
            $browsershot->noSandbox();
        }

        // Documents are static; without scripts, HTML that slipped into the
        // data cannot make the browser request other pages or files.
        if (empty($this->config['javascript'])) {
            $browsershot->disableJavascript();
        }

        // Browsershot writes the page to a temp file and removes it only
        // after a successful render; a folder of our own is always removed.
        $tempPath = sys_get_temp_dir().'/easy-pdf-word-chrome-'.bin2hex(random_bytes(8));
        @mkdir($tempPath, 0700);
        $browsershot->setCustomTempPath($tempPath);

        try {
            return $browsershot->pdf();
        } finally {
            (new Filesystem)->deleteDirectory($tempPath);
        }
    }

    /**
     * Chrome renders header/footer templates in their own tiny page with no
     * styles from the document, so they get explicit direction and size.
     */
    private function chromeTemplate(string $html, PdfOptions $options): string
    {
        $html = str_replace(
            ['{page}', '{pages}'],
            ['<span class="pageNumber"></span>', '<span class="totalPages"></span>'],
            $html
        );

        // Chrome draws headers and footers apart from the page, without its
        // fonts: without its own @font-face, Arabic falls back to a system font.
        $fonts = $this->fonts ? '<style>'.$this->fonts->cssFontFaces([$options->font]).'</style>' : '';

        return $fonts.'<div dir="'.$options->direction.'" style="width:100%;font-size:9px;padding:0 '
            .$options->margins[1].'mm 0 '.$options->margins[3].'mm;font-family:\''.$options->font.'\',sans-serif;">'
            .$html.'</div>';
    }
}
