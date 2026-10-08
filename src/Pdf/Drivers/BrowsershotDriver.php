<?php

namespace BiztechEG\EasyPdfWord\Pdf\Drivers;

use BiztechEG\EasyPdfWord\Contracts\PdfDriver;
use BiztechEG\EasyPdfWord\Exceptions\DriverNotAvailable;
use BiztechEG\EasyPdfWord\Pdf\PdfOptions;
use Spatie\Browsershot\Browsershot;

/**
 * Chromium through spatie/browsershot (Node + Puppeteer). Best Arabic
 * rendering (HarfBuzz) and full modern CSS.
 */
class BrowsershotDriver implements PdfDriver
{
    public function __construct(private array $config = []) {}

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

        $browsershot = Browsershot::html($html)
            ->paperSize($width, $height, 'mm')
            ->margins($top, $right, $bottom, $left, 'mm')
            ->showBackground()
            ->timeout((int) ($this->config['timeout'] ?? 60));

        if ($options->header || $options->footer) {
            $browsershot->showBrowserHeaderAndFooter()
                ->headerHtml($this->chromeTemplate($options->header ?? '', $options))
                ->footerHtml($this->chromeTemplate($options->footer ?? '', $options));
        }

        if (! empty($this->config['node_binary'])) {
            $browsershot->setNodeBinary($this->config['node_binary']);
        }

        if (! empty($this->config['npm_binary'])) {
            $browsershot->setNpmBinary($this->config['npm_binary']);
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

        return $browsershot->pdf();
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

        return '<div dir="'.$options->direction.'" style="width:100%;font-size:9px;padding:0 '
            .$options->margins[1].'mm 0 '.$options->margins[3].'mm;font-family:'.$options->font.',sans-serif;">'
            .$html.'</div>';
    }
}
