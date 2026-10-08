<?php

namespace BiztechEG\EasyPdfWord\Pdf\Drivers;

use BiztechEG\EasyPdfWord\Contracts\PdfDriver;
use BiztechEG\EasyPdfWord\Pdf\PdfOptions;
use BiztechEG\EasyPdfWord\Pdf\Watermark;
use Illuminate\Http\Client\Factory as Http;
use RuntimeException;

/**
 * Chromium running in a Gotenberg container (https://gotenberg.dev),
 * reached over HTTP. Same output quality as Browsershot without Node on
 * the app server.
 */
class GotenbergDriver implements PdfDriver
{
    private const MM_PER_INCH = 25.4;

    public function __construct(
        private Http $http,
        private array $config = [],
    ) {}

    public function isAvailable(): bool
    {
        return ! empty($this->config['url']);
    }

    public function usesCssFonts(): bool
    {
        return true;
    }

    public function render(string $html, PdfOptions $options): string
    {
        [$top, $right, $bottom, $left] = $options->margins;
        [$width, $height] = $options->paperSize();

        $request = $this->http
            ->timeout((int) ($this->config['timeout'] ?? 60))
            ->attach('files', $this->withoutScripts(Watermark::inject($html, $options)), 'index.html');

        foreach (['header' => $options->header, 'footer' => $options->footer] as $name => $part) {
            if ($part) {
                $request->attach('files', $this->chromeTemplate($part, $options), "{$name}.html");
            }
        }

        $response = $request->post(rtrim($this->config['url'], '/').'/forms/chromium/convert/html', [
            'paperWidth' => $this->inches($width),
            'paperHeight' => $this->inches($height),
            'marginTop' => $this->inches($top),
            'marginRight' => $this->inches($right),
            'marginBottom' => $this->inches($bottom),
            'marginLeft' => $this->inches($left),
            'printBackground' => 'true',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("Gotenberg returned HTTP {$response->status()}: ".substr($response->body(), 0, 300));
        }

        // A proxy or a wrong URL can answer 200 with a web page.
        if (! str_starts_with($response->body(), '%PDF-')) {
            throw new RuntimeException('Gotenberg did not return a PDF; check DOC_GOTENBERG_URL. It returned: '.substr(strip_tags($response->body()), 0, 200));
        }

        return $response->body();
    }

    /**
     * Gotenberg has no per request switch for JavaScript, so a policy in the
     * page turns it off, as Browsershot does: HTML that slipped into the data
     * cannot make the browser request other pages or files.
     */
    private function withoutScripts(string $html): string
    {
        if (! empty($this->config['javascript'])) {
            return $html;
        }

        $policy = '<meta http-equiv="Content-Security-Policy" content="script-src \'none\'">';
        $count = 0;
        $html = preg_replace('/<head\b[^>]*>/i', '$0'.$policy, $html, 1, $count) ?? $html;

        return $count === 1 ? $html : $policy.$html;
    }

    private function inches(float $mm): string
    {
        return (string) round($mm / self::MM_PER_INCH, 4);
    }

    private function chromeTemplate(string $html, PdfOptions $options): string
    {
        $html = str_replace(
            ['{page}', '{pages}'],
            ['<span class="pageNumber"></span>', '<span class="totalPages"></span>'],
            $html
        );

        return '<!doctype html><html><head><meta charset="utf-8"></head><body dir="'.$options->direction
            .'" style="font-size:9px;margin:0 '.$options->margins[1].'mm 0 '.$options->margins[3].'mm;">'
            .$html.'</body></html>';
    }
}
