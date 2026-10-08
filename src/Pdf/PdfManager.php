<?php

namespace BiztechEG\EasyPdfWord\Pdf;

use BiztechEG\EasyPdfWord\Contracts\PdfDriver;
use BiztechEG\EasyPdfWord\Exceptions\DriverNotAvailable;
use BiztechEG\EasyPdfWord\Fonts\FontRegistry;
use BiztechEG\EasyPdfWord\Pdf\Drivers\BrowsershotDriver;
use BiztechEG\EasyPdfWord\Pdf\Drivers\GotenbergDriver;
use BiztechEG\EasyPdfWord\Pdf\Drivers\MpdfDriver;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Manager;
use Throwable;

/**
 * Resolves PDF engines by name ("mpdf", "chromium", "gotenberg", or one
 * added with extend()) and falls back to another engine when the chosen
 * one is missing or fails.
 *
 * @method PdfDriver driver(string|null $driver = null)
 */
class PdfManager extends Manager
{
    private const ALIASES = ['chromium' => 'browsershot', 'chrome' => 'browsershot'];

    public function getDefaultDriver(): string
    {
        return $this->config->get('easy-pdf-word.pdf.driver', 'mpdf');
    }

    public function normalize(?string $driver): string
    {
        $driver = strtolower($driver ?? $this->getDefaultDriver());

        return self::ALIASES[$driver] ?? $driver;
    }

    public function driver($driver = null)
    {
        return parent::driver($this->normalize($driver));
    }

    /**
     * Render with the given engine, or the fallback engine if it is not
     * installed or throws.
     *
     * @return array{0: string, 1: string} PDF bytes and the engine that made them
     */
    public function render(string $html, PdfOptions $options, ?string $driver = null, ?callable $htmlFor = null): array
    {
        $name = $this->normalize($driver);
        $fallback = $this->config->get('easy-pdf-word.pdf.fallback');
        $fallback = $fallback ? $this->normalize($fallback) : null;

        try {
            $engine = $this->driver($name);

            if (! $engine->isAvailable()) {
                throw new DriverNotAvailable("The [{$name}] PDF engine is not available.");
            }

            return [$engine->render($htmlFor ? $htmlFor($engine) : $html, $options), $name];
        } catch (Throwable $e) {
            if ($fallback === null || $fallback === $name) {
                throw $e;
            }

            Log::warning("easy-pdf-word: [{$name}] failed, falling back to [{$fallback}]: {$e->getMessage()}");

            $engine = $this->driver($fallback);

            return [$engine->render($htmlFor ? $htmlFor($engine) : $html, $options), $fallback];
        }
    }

    public function engineConfig(string $name): array
    {
        return (array) $this->config->get("easy-pdf-word.pdf.drivers.{$name}", []);
    }

    protected function createMpdfDriver(): PdfDriver
    {
        return new MpdfDriver($this->container->make(FontRegistry::class), $this->engineConfig('mpdf'));
    }

    protected function createBrowsershotDriver(): PdfDriver
    {
        return new BrowsershotDriver($this->engineConfig('browsershot'));
    }

    protected function createGotenbergDriver(): PdfDriver
    {
        return new GotenbergDriver($this->container->make(Http::class), $this->engineConfig('gotenberg'));
    }
}
